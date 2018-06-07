<?php

/**
 * @file
 * Contains \WP\Console\Core\EventSubscriber\SendStatisticsListener.
 */

namespace WP\Console\Core\EventSubscriber;

use WP\Console\Core\Style\WPStyle;
use WP\Console\Core\Utils\ConfigurationManager;
use GuzzleHttp\Client;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use WP\Console\Core\Utils\TranslatorManager;

/**
 * Class SendStatisticsListener
 *
 * @package WP\Console\Core\EventSubscriber
 */
class SendStatisticsListener implements EventSubscriberInterface
{

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * FileSystem $fs
     */
    protected $fs;

    /**
     * string $homeDirectory
     */
    protected $homeDirectory;

    /**
     * @var TranslatorManager
     */
    protected $translator;

    /**
     * SaveStatisticsListener constructor.
     *
     * @param ConfigurationManager $configurationManager
     * @param TranslatorManager $translator
     */
    public function __construct(
        ConfigurationManager $configurationManager,
        TranslatorManager $translator
    ) {
        $this->configurationManager = $configurationManager;
        $this->translator = $translator;
        $this->fs = new Filesystem();
    }

    /**
     * @param ConsoleTerminateEvent $event
     */
    public function calculateStatistics(ConsoleTerminateEvent $event)
    {
        if ($event->getExitCode() != 0) {
            return;
        }

        $date = date('Y-m-d');
        $configGlobalAsArray = $this->configurationManager->getConfigGlobalAsArray();

        //Validate if the config is enable.
        if (is_null($configGlobalAsArray) || !$configGlobalAsArray['application']['statistics']['enabled']) {
            return;
        }

        //Validate if the times attempted is 10
        if ($configGlobalAsArray['application']['statistics']['times-attempted'] >= 10) {
            /* @var WPStyle $io */
            $io = new WPStyle($event->getInput(), $event->getOutput());
            $io->error($this->translator->trans('application.errors.statistics-failed'));

            $this->configurationManager->updateConfigGlobalParameter('statistics.enabled', false);
            return;
        }

        //Validate if the last attempted was today
        if ($configGlobalAsArray['application']['statistics']['last-attempted'] === $date) {
            return;
        }

        $path = sprintf(
            '%s/.wp-console/stats',
            $this->configurationManager->getHomeDirectory()
        );

        //Find all statistics with pending status from other days.
        $finder = new Finder();
        $finder
            ->files()
            ->name('*.csv')
            ->notName($date.'.csv')
            ->in($path);

        //Validate if finder in not null
        if ($finder->count() == 0) {
            return;
        }

        $statisticsKeys = ['command', 'language', 'linesOfCode'];
        $commands = [];
        $languages = [];
        $filePathToDelete = [];

        foreach ($finder as $file) {
            if (($handle = fopen($file->getPathname(), "r")) !== false) {
                while (($content = fgetcsv($handle, 0, ',')) !== false) {

                    /**
                     * If the command doesn't have linesOfCode,
                     * we add a null value at the end to combine with statistics keys.
                     */
                    if (count($content) === 2) {
                        array_push($content, 0);
                    }

                    $commands = $this->getCommandStatisticsAsArray($commands, array_combine($statisticsKeys, $content));
                    $languages = $this->getLanguageStatisticsAsArray($languages, array_combine($statisticsKeys, $content));
                }

                fclose($handle);

                //Save file path to delete if the response is success.
                array_push($filePathToDelete, $file->getPathname());
            }
        }

        try {
            $client = new Client();
            $response = $client->post(
                'http://wordpressconsole.com/statistics?_format=json',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'json' => ['commands' => $commands, 'languages' => $languages]
                ]
            );

            if ($response->getStatusCode() === 200) {
                $this->fs->remove($filePathToDelete);

                //Reset the times attempted to 0.
                $this->configurationManager->updateConfigGlobalParameter('statistics.times-attempted', 0);
            }
        } catch (\Exception $exception) {
            //Increase the times attempted in global config.
            $timesAttempted = $configGlobalAsArray['application']['statistics']['times-attempted'] + 1;
            $this->configurationManager->updateConfigGlobalParameter('statistics.times-attempted', $timesAttempted);
        }

        //Update last attempted in global config.
        $this->configurationManager->updateConfigGlobalParameter('statistics.last-attempted', $date);
    }

    /**
     * Build the statistics by command.
     *
     * @param  $commands
     * @param  $content
     * @return array
     */
    private function getCommandStatisticsAsArray($commands, $content)
    {
        //Check if in $commands with the $content['command'] key with the value 'executed' have value to sum.
        $executed = $commands[$content['command']]['executed'] + 1;
        $linesOfCode = $commands[$content['command']]['linesOfCode'] + $content['linesOfCode'];

        $commands[$content['command']] = ["executed" => $executed, "linesOfCode" => $linesOfCode];

        return $commands;
    }

    /**
     * Update the languages by command.
     *
     * @param  $languages
     * @param  $content
     * @return array
     */
    private function getLanguageStatisticsAsArray($languages, $content)
    {
        //Check if in $commands with the $content['language'] key have value to sum.
        $languages[$content['language']] = $languages[$content['language']] + 1;

        return $languages;
    }

    /**
     * @{@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [ConsoleEvents::TERMINATE => 'calculateStatistics'];
    }
}
