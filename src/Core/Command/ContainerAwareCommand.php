<?php

/**
 * @file
 * Contains \WP\Console\Core\Command\ContainerAwareCommand.
 */

namespace WP\Console\Core\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use WP\Console\Core\Command\Shared\ContainerAwareCommandTrait;

/**
 * Class ContainerAwareCommand
 *
 * @package WP\Console\Core\Command
 */
abstract class ContainerAwareCommand extends BaseCommand
{
    use ContainerAwareCommandTrait;
}
