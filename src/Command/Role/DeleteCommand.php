<?php
/**
 * @file
 * Contains \WP\Console\Command\Role\DeleteCommand.
 */

namespace WP\Console\Command\Role;

use Symfony\Component\Console\Input\InputOption;
use WP\Console\Core\Utils\StringConverter;
use WP\Console\Utils\Site;
use WP\Console\Utils\Validator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use WP\Console\Core\Command\Command;
use WP\Console\Utils\WordpressApi;
use WP\Console\Command\Shared\ConfirmationTrait;
use WP\Console\Core\Style\WPStyle;

class DeleteCommand extends Command
{
    use ConfirmationTrait;

    /**
     * @var WordpressApi
     */
    protected $wordpressApi;

    /**
     * @var Site
     */
    protected $site;

    /**
     * DeleteCommand constructor.
     *
     * @param WordpressApi $wordpressApi
     * @param Site         $site
     */
    public function __construct(
        WordpressApi $wordpressApi,
        Site $site
    ) {
        $this->wordpressApi = $wordpressApi;
        $this->site = $site;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('role:delete')
            ->setDescription($this->trans('commands.role.delete.description'))
            ->setHelp($this->trans('commands.role.delete.help'))
            ->addArgument(
                'roles',
                InputArgument::IS_ARRAY,
                $this->trans('commands.role.delete.argument.roles')
            )->setAliases(['rd']);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new WPStyle($input, $output);

        $roles = $input->getArgument('roles');

        $role = $this->deleteRole(
            $roles
        );

        $tableHeader = [
            $this->trans('commands.role.delete.messages.role-id'),
            $this->trans('commands.role.delete.messages.role-name'),
        ];

        if ($role['success']) {
            $io->success(
                sprintf(
                    $this->trans('commands.role.delete.messages.role-created')
                )
            );

            $io->table($tableHeader, $role['success']);

            return 0;
        }

        if ($role['error']) {
            $io->error($role['error']['error']);

            return 1;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new WPStyle($input, $output);

        $rolename = $input->getArgument('roles');
        if (!$rolename) {
            $roles_collection = [];
            $roles = array_keys($this->wordpressApi->getRoles());
            $io->writeln($this->trans('commands.common.questions.roles.message'));
            while (true) {
                $role = $io->choiceNoList(
                    $this->trans('commands.common.questions.roles.name'),
                    $roles,
                    null,
                    true
                );
                $role = trim($role);
                if (empty($role)) {
                    break;
                }

                array_push($roles_collection, $role);
                $role_key = array_search($role, $roles, true);
                if ($role_key >= 0) {
                    unset($roles[$role_key]);
                }
            }

            $input->setArgument('roles', $roles_collection);
        }
    }

    /**
     * Remove and returns an array of deleted roles
     *
     * @param $roles
     *
     * @return $array
     */
    private function deleteRole($roles)
    {
        $this->site->loadLegacyFile('wp-includes/capabilities.php');

        $result = [];

        try {
            foreach ($roles as $value) {
                if (get_role($value)) {
                    remove_role($value);

                    $result['success'][] = [
                        'role-id' => $value,
                        'role-name' => $value
                    ];
                }
            }
        } catch (\Exception $e) {
            $result['error'] = [
                'error' => 'Error: ' . get_class($e) . ', code: ' . $e->getCode() . ', message: ' . $e->getMessage()
            ];
        }

        return $result;
    }
}
