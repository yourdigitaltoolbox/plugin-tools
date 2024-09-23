<?php

namespace YDTBWP\Providers;

class ApiServiceProvider implements Provider
{
    public function register()
    {
        add_action('rest_api_init', function () {
            register_rest_route('plugin-name/v1', '/howdy', [
                'methods' => 'GET',
                'callback' => function () {
                    return [
                        'message' => 'Howdy!',
                    ];
                },
                'permission_callback' => '__return_true',
            ]);
        });
    }
}
