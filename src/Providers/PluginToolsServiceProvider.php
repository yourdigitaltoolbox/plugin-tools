<?php

namespace YDTBWP\Providers;

use YDTBWP\Action\UpdateAction;
use YDTBWP\Providers\ApiServiceProvider;
use YDTBWP\Providers\CommandServiceProvider;

class PluginToolsServiceProvider implements Provider
{
    protected function providers()
    {
        return [
            ApiServiceProvider::class,
            CommandServiceProvider::class,
            new UpdateAction('plugin'),
            new UpdateAction('theme'),
        ];
    }

    public function register()
    {
        foreach ($this->providers() as $service) {
            if (is_string($service)) {
                (new $service)->register();
            } else {
                $service->register();
            }
        }
    }

    public function boot()
    {
        //
    }
}
