<?php

return [
    'assets' => [
        /*
        |--------------------------------------------------------------------------
        | Assets Directory URL
        |--------------------------------------------------------------------------
        |
        | The asset manifest contains relative paths to your assets. This URL will
        | be prepended when using Clover's asset management system. Change this if
        | you are pushing to a CDN.
        |
        */

        'uri' => plugins_url('public', __FILE__),

        /*
        |--------------------------------------------------------------------------
        | Assets Directory Path
        |--------------------------------------------------------------------------
        |
        | The asset manifest contains relative paths to your assets. This path will
        | be prepended when using Clover's asset management system.
        |
        */

        'path' => dirname(__DIR__).'/public',

        /*
        |--------------------------------------------------------------------------
        | Assets Manifest
        |--------------------------------------------------------------------------
        |
        | Your asset manifest is used by Clover to assist WordPress and your views
        | with rendering the correct URLs for your assets. This is especially
        | useful for statically referencing assets with dynamically changing names
        | as in the case of cache-busting.
        |
        */

        'manifest' => dirname(__DIR__).'/public/manifest.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | View Storage Path
    |--------------------------------------------------------------------------
    |
    | Most template systems load templates from disk. Here you may specify
    | the location on your disk where your views are located.
    |
    */

    'views' => dirname(__DIR__).'/resources/views',
];
