<?php

/**
 * @file
 * Contains \Drupal\Console\Command\Create\RolesCommand.
 */

namespace WP\Console\Command\Create;

use WP\Console\Utils\Create\RoleData;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WP\Console\Core\Command\Command;
use WP\Console\Core\Style\WPStyle;

/**
 * Class RolesCommand
 *
 * @package WP\Console\Command\Create
 */
class RolesCommand extends Command
{
    /**
     * @var RoleData
     */
    protected $createRoleData;

    /**
     * RolesCommand constructor.
     *
     * @param RoleData $createRoleData
     */
    public function __construct(
        RoleData $createRoleData
    ) {
        $this->createRoleData = $createRoleData;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('create:roles')
            ->setDescription($this->trans('commands.create.roles.description'))
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.create.roles.options.limit')
            )
            ->setAliases(['crr']);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new WPStyle($input, $output);

        $limit = $input->getOption('limit');
        if (!$limit) {
            $limit = $io->ask(
                $this->trans('commands.create.roles.questions.limit'),
                5
            );
            $input->setOption('limit', $limit);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new WPStyle($input, $output);

        $limit = $input->getOption('limit')?:5;

        $roles = $this->createRoleData->create(
            $limit
        );

        $tableHeader = [
            $this->trans('commands.create.roles.messages.role-id'),
            $this->trans('commands.create.roles.messages.role-name'),
        ];

        if ($roles['success']) {
            $io->table($tableHeader, $roles['success']);

            $io->success(
                sprintf(
                    $this->trans('commands.create.roles.messages.created-roles'),
                    $limit
                )
            );
        }

        return 0;
    }
}
