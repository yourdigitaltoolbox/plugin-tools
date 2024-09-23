<?php

namespace YDTBWP\Providers;

use YDTBWP\Commands\PluginNameCommand;

class CommandServiceProvider implements Provider
{
    public function register()
    {
        if (! defined('WP_CLI') || ! WP_CLI) {
            return;
        }

        \WP_CLI::add_command('plugin-name', PluginNameCommand::class);
    }
}
