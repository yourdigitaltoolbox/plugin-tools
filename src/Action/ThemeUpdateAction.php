<?php

namespace YDTBWP\Action;

use YDTBWP\Providers\Provider;
use \YDTBWP\Utils\Requests;
use \YDTBWP\Utils\ZipDirectory;

class ThemeUpdateAction implements Provider
{

    public function register()
    {
        add_action('ydtbwp_update_themes', [$this, 'update_themes']);
        add_action('ydtbwp_push_single_theme', [$this, 'push_single_theme']);
    }

    public function out(string $arg1): void
    {
        if (!$this->quiet) {
            echo $arg1;
        }
    }

    private $quiet = true;

    public function update_themes($quiet = true)
    {
        $out = [$this, 'out'];
        $this->quiet = $quiet;
        $checked_themes = json_decode(get_option('ydtbwp_push_themes', []));
        $all_themes = wp_get_themes();
        $upgrade_themes = array();
        $current = get_site_transient('update_themes');

        foreach ((array) $all_themes as $theme_file => $theme_data) {
            if (isset($current->response[$theme_file])) {
                echo "Checking $theme_file\n";

                if ($current->response[$theme_file]["package"] == "") {
                    echo "No update URL found for $theme_file\n";
                    continue;
                }

                $themeData = [
                    'theme_name' => $theme_data['Name'],
                    'theme_version' => $theme_data['Version'],
                    'theme_update_version' => $current->response[$theme_file]["new_version"],
                    'theme_update_url' => $current->response[$theme_file]["package"],
                    'theme_slug' => \explode('/', $theme_file)[0],
                    'theme_file' => $theme_file,
                ];

                $upgrade_themes[] = $themeData;
            }
        }
        $out("\n");
        $out("This Site has | " . count($upgrade_themes) . " | themes with pending updates... ");
        $out("\n\n");

        // We need to check if the theme has been whitelisted to be pushed to the remote repo.
        foreach ($upgrade_themes as $key => $theme) {
            $slug = $theme['theme_slug'];
            if (!isset($checked_themes->$slug)) {
                echo "\t-- theme {$theme['theme_name']} is not whitelisted, removing from possible push list... \n";
                unset($upgrade_themes[$key]);
                continue;
            }
            // set the theme vendor
            $upgrade_themes[$key]['theme_vendor'] = $checked_themes->$slug;
        }

        // if there are no themes to update then we can return early
        if (empty($upgrade_themes)) {
            $out("No updates for whitelisted themes Available to push \n");
            return;
        }

        echo "\nThis Site has | " . count($upgrade_themes) . " | themes with pending updates that are whitelisted to be pushed to the remote repo.  \n\n";

        // We need to check if the theme version has been pushed to the remote repo. to do that we need to make a request to the remote repo to get the theme versions that are currently there.
        $Remotethemes = Requests::getRemoteData();

        $remoteThemeArray = [];
        foreach ($Remotethemes as $property => $value) {
            $remoteThemeArray[$value->slug] = $value->tags;
        }

        $auto_update = get_option('ydtbwp_theme_auto_update', false);
        if ($auto_update) {
            echo "\t** Auto Update is enabled, themes will be updated automatically **\n";
            include_once ABSPATH . 'wp-admin/includes/file.php';
            include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            include_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . "wp-admin/includes/theme.php";
        } else {
            echo "\t** Auto Update is disabled, themes will not be updated automatically ** \n\n";
        }

        foreach ($upgrade_themes as $key => $theme) {
            if (isset($remoteThemeArray[$theme['theme_slug']]) && in_array($theme['theme_update_version'], $remoteThemeArray[$theme['theme_slug']])) {
                unset($upgrade_themes[$key]);

                echo "\t-- theme $theme[theme_name] - $theme[theme_update_version] is already pushed to the remote repo, removing from possible push list... \n";

                if ($auto_update) {

                    $skin = new \Automatic_Upgrader_Skin();
                    $upgrader = new \Theme_Upgrader($skin);
                    $result = $upgrader->upgrade($theme['theme_file']);

                    // Check if the update was successful
                    if ($result) {
                        echo " \t   >> Local theme Updated Successfully \n";
                    } else {
                        echo "\t   >> Local theme Update Failed \n";
                    }

                    // for some reason programically updating the theme deactivates it, lets just reactivate it here.
                    activate_theme($theme['theme_file']);
                }
            }
        }

        echo "\nThis Site has | " . count($upgrade_themes) . " | themes with pending updates, that are whitelisted, and are not already pushed remotely.  \n\n";

        if (empty($upgrade_themes)) {
            echo ("Good News! All theme updates are already pushed so, No themes to update \n");
            return;
        }

        $body = new \stdClass();
        $body->ref = "main";
        $body->inputs = new \stdClass();
        $body->inputs->json = \json_encode(array_values($upgrade_themes));

        echo "\n------ Generated theme Update Info ------\n\n";

        var_dump($body);

        echo "\n\n";
        Requests::updateRequest(json_encode($body));
    }

    /**
     * Push a single theme to the remote repo
     * This does not proxy a theme update from another source, it is used to zip a local theme and push it to the remote repo.
     */

    public function push_single_theme($theme)
    {
        echo "----- Pushing single theme ----- \n";

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/ydtbwp';
        $temp_url = $upload_dir['baseurl'] . '/ydtbwp';

        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0777, true);
        }

        $targetDir = WP_theme_DIR . "/" . $theme['slug'];
        $outputPath = $temp_dir . "/" . $theme['slug'] . ".zip";
        $outputURL = $temp_url . "/" . $theme['slug'] . "." . $theme['Version'] . ".zip";

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        $zipPath = (new ZipDirectory($targetDir, $outputPath, $theme['slug']))->make();

        $newZipPath = $temp_dir . "/" . $theme['slug'] . "." . $theme['Version'] . ".zip";
        rename($zipPath, $newZipPath);
        $zipPath = $newZipPath;

        echo "$theme[Name] - $theme[Version] has been zipped to: \n";
        echo $outputURL . "\n";

        $body = new \stdClass();
        $body->ref = "main";
        $body->inputs = new \stdClass();
        $body->inputs->json = \json_encode([
            [
                'theme_name' => $theme['Name'],
                'theme_version' => $theme['Version'],
                'theme_update_version' => $theme['Version'],
                'theme_update_url' => $outputURL,
                'theme_slug' => $theme['slug'],
                'theme_file' => $theme['file_path'],
                'theme_vendor' => $theme['vendor'],
            ],
        ]);

        var_dump($body);

        Requests::updateRequest(json_encode($body));
    }
}
