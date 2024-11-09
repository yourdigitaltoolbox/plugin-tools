<?php

namespace YDTBWP\Providers;

use YDTBWP\Commands\PluginToolsCommand;
use YDTBWP\Interfaces\Provider;

class CommandServiceProvider implements Provider
{
    public function register()
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        \WP_CLI::add_command('pt', PluginToolsCommand::class);
    }
}
