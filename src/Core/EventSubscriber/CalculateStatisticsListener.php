<?php

/**
 * @file
 * Contains \Drupal\Console\Core\EventSubscriber\CalculateStatisticsListener.
 */

namespace Drupal\Console\Core\EventSubscriber;

use Drupal\Console\Core\Command\Chain\ChainCustomCommand;
use Drupal\Console\Core\Utils\ConfigurationManager;
use GuzzleHttp\Client;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class CalculateStatisticsListener
 *
 * @package Drupal\Console\Core\EventSubscriber
 */
class CalculateStatisticsListener implements EventSubscriberInterface
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
     * SaveStatisticsListener constructor.
     *
     * @param ConfigurationManager $configurationManager
     */
    public function __construct(
        ConfigurationManager $configurationManager
    ) {
        $this->configurationManager = $configurationManager;
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

        $globalConfig = $this->configurationManager->getGlobalConfig();

        //Validate if the config is enable.
        if (is_null($globalConfig) || !$globalConfig['application']['share']['statistics']) {
            return;
        }

        //Check that the namespace starts with 'Drupal\Console'.
        $class = new \ReflectionClass($event->getCommand());
        if (strpos($class->getNamespaceName(), "Drupal\Console") !== 0) {
            return;
        }

        //Validate if the command is not a custom chain command.
        if ($event->getCommand() instanceof ChainCustomCommand) {
            return;
        }

        $path = sprintf(
            '%s/.console/stats',
            $this->configurationManager->getHomeDirectory()
        );

        //Find all statistics with pending status from other days.
        $finder = new Finder();
        $finder
            ->files()
            ->name('*-pending.csv')
            ->notName(date('Y-m-d').'-pending.csv')
            ->in($path);

        if ($finder->count() > 0) {
            $statisticsKeys = ['command', 'language', 'linesOfCode'];
            $commands = [];
            $languages = [];
            $filePathToDelete = [];

            foreach ($finder as $file) {
                if (($handle = fopen($file->getPathname(), "r")) !== false) {
                    while (($content = fgetcsv($handle, 0, ';')) !== false) {

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

            $client = new Client();

            $response = $client->post(
                'http://127.0.0.1:8088/statistics?_format=json',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'json' => ['commands' => $commands, 'languages' => $languages]
                ]
            );

            if ($response->getStatusCode() === 200) {
                $this->fs->remove($filePathToDelete);
            }
        }
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
