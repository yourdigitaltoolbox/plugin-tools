<?php

namespace YDTBWP\Utils;
class Config
{
    public static function get($key = '', $all = false): array|string
    {
        $plugin_folder = trailingslashit(dirname(path: __FILE__, levels: 3));

        $plugin_file = $plugin_folder . 'plugin-tools.php';

        $config = [
            'plugin_file' => $plugin_file,
            'plugin_slug' => plugin_basename(file: $plugin_folder),
            'plugin_path' => plugin_dir_path(file: $plugin_folder),
            'plugin_url' => plugin_dir_url(file: $plugin_folder),
            'update_url' => 'https://yourdigitaltoolbox.github.io/plugin-tools/manifest.json',
            'version' => get_file_data($plugin_file, array('Version'), 'plugin')[0]
        ];

        if ($all) {
            return $config;
        }

        return $config[$key] ?? null;
    }
}

