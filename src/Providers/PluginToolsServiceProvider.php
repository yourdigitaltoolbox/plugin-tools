<?php

namespace YDTBWP\Providers;

use YDTBWP\Action\PluginUpdateAction;
use YDTBWP\Action\ThemeUpdateAction;

class PluginToolsServiceProvider implements Provider
{
    protected function providers()
    {
        return [
            ApiServiceProvider::class,
            CommandServiceProvider::class,
            PluginUpdateAction::class,
            ThemeUpdateAction::class,
            // PluginColumnProvider::class,
        ];
    }

    public function register()
    {
        foreach ($this->providers() as $service) {
            (new $service)->register();
        }
    }

    public function boot()
    {
        //
    }
}
