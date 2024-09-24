<?php

namespace YDTBWP\Providers;

class PluginToolsServiceProvider implements Provider
{
    protected function providers()
    {
        return [
            ApiServiceProvider::class,
            BlockServiceProvider::class,
            CommandServiceProvider::class,
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
