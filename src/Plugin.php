<?php

namespace YDTBWP;

use YDTBWP\Providers\CommandServiceProvider;
use YDTBWP\Providers\ApiServiceProvider;
use YDTBWP\Action\UpdateAction;
use YDTBWP\Interfaces\Provider;
use YDTBWP\Utils\Config;
use YDTBWP\Utils\Cron;
use YDTBWP\Utils\Updater;

class Plugin implements Provider
{

    public function __construct()
    {

        // setup cron to activate and deactivate with the plugin
        $cron = new Cron;

        register_activation_hook(
            Config::get(key: 'plugin_file'),
            [$cron, 'setup_cron_schedule']
        );

        register_deactivation_hook(
            Config::get(key: 'plugin_file'),
            [$cron, 'clear_cron_schedule']
        );
    }

    protected function providers()
    {
        return [
            ApiServiceProvider::class,
            CommandServiceProvider::class,
            new UpdateAction('plugin'),
            new UpdateAction('theme'),
            Updater::class
        ];
    }

    public function register()
    {
        foreach ($this->providers() as $service) {
            if (is_string(value: $service)) {
                (new $service)->register();
            } else {
                $service->register();
            }
        }
    }
}
