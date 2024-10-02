<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit18f207ada0852e05a50ff33b702912a9
{
    public static $files = array (
        'a4ecaeafb8cfb009ad0e052c90355e98' => __DIR__ . '/..' . '/beberlei/assert/lib/Assert/functions.php',
        '3107fc387871a28a226cdc8c598a0adb' => __DIR__ . '/..' . '/php-school/cli-menu/src/Util/ArrayUtils.php',
        'f7d21e6fd393cad643af238be50763f2' => __DIR__ . '/../..' . '/src/helpers.php',
    );

    public static $prefixLengthsPsr4 = array (
        'Y' => 
        array (
            'YDTBWP\\Tests\\' => 13,
            'YDTBWP\\' => 7,
        ),
        'P' => 
        array (
            'PhpSchool\\Terminal\\' => 19,
            'PhpSchool\\CliMenu\\' => 18,
        ),
        'D' => 
        array (
            'Diversen\\' => 9,
            'Database\\Seeders\\' => 17,
            'Database\\Factories\\' => 19,
        ),
        'A' => 
        array (
            'Assert\\' => 7,
            'App\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'YDTBWP\\Tests\\' => 
        array (
            0 => __DIR__ . '/../..' . '/tests',
        ),
        'YDTBWP\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
        'PhpSchool\\Terminal\\' => 
        array (
            0 => __DIR__ . '/..' . '/php-school/terminal/src',
        ),
        'PhpSchool\\CliMenu\\' => 
        array (
            0 => __DIR__ . '/..' . '/php-school/cli-menu/src',
        ),
        'Diversen\\' => 
        array (
            0 => __DIR__ . '/..' . '/diversen/php-cli-spinners/src',
        ),
        'Database\\Seeders\\' => 
        array (
            0 => __DIR__ . '/..' . '/laravel/pint/database/seeders',
        ),
        'Database\\Factories\\' => 
        array (
            0 => __DIR__ . '/..' . '/laravel/pint/database/factories',
        ),
        'Assert\\' => 
        array (
            0 => __DIR__ . '/..' . '/beberlei/assert/lib/Assert',
        ),
        'App\\' => 
        array (
            0 => __DIR__ . '/..' . '/laravel/pint/app',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit18f207ada0852e05a50ff33b702912a9::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit18f207ada0852e05a50ff33b702912a9::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit18f207ada0852e05a50ff33b702912a9::$classMap;

        }, null, ClassLoader::class);
    }
}
