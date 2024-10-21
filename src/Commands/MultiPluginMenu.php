<?php

namespace YDTBWP\Commands;

use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\Builder\SplitItemBuilder;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\MenuItem\CheckboxItem;
use PhpSchool\CliMenu\MenuItem\SplitItem;
use PhpSchool\CliMenu\Style\CheckboxStyle;
use YDTBWP\Utils\Requests;

class MultiPluginMenu
{

    function __construct()
    {
        $this->remote_plugins = Requests::getRemoteData('plugins');
    }

    private $remote_plugins = [];

    private $selectedPlugins = [];

    public function getSelectedPlugins()
    {
        return $this->selectedPlugins;
    }

    public function build()
    {
        $updateTracked = function (CliMenu $menu) {

            $item = $menu->getSelectedItem();
            $selection = $item->getText();
            $all_plugins = get_plugins();

            $plugin_slug = null;
            foreach ($all_plugins as $key => $plugin) {
                if ($plugin['Name'] === $selection) {
                    $plugin_slug = explode("/", $key)[0];
                    break;
                }
            }

            if (!isset($plugin_slug)) {
                echo "Plugin slug not found \n";
                return;
            }

            if ($item->getChecked()) {

                $tracked = json_decode(get_option('ydtbwp_push_plugins', json_encode([])));

                if (isset($tracked->{$plugin_slug})) {
                    $vendor = $tracked->{$plugin_slug};
                }
                // if there is a remote vendor then we should use that.
                if (isset($this->remote_plugins->$plugin_slug)) {
                    $vendor = $this->remote_plugins->$plugin_slug->vendor;
                }

                // if the plugin is not in the remote list then we need to collect the vendor.
                if (!isset($vendor)) {
                    echo "Plugin not found in remote list, please enter the vendor name\n";
                    $result = $menu->askText()
                        ->setPromptText('Enter The Vendor Name')
                        ->setPlaceholderText('')
                        ->setValidationFailedText('Please enter at least 3 characters')
                        ->setValidator(function ($input) {
                            return $input !== '' && strlen($input) > 2;
                        })
                        ->ask();
                    $vendor = $result->fetch();
                }

                $this->selectedPlugins[$plugin_slug] = $vendor;

                // update the menu item with the vendor name
                $menuItems = $menu->getItems();

                foreach ($menuItems as $item) {
                    if ($item instanceof SplitItem) {

                        $splitItems = $item->getItems();
                        $firstItem = $splitItems[0];
                        if ($firstItem->getText() == $selection) {
                            $splitItems[1]->setText($vendor);
                        }
                    }
                }
                $menu->redraw();

            } else {
                unset($this->selectedPlugins[$plugin_slug]);
            }
        };

        $all_plugins = get_plugins();
        $tracked = json_decode(get_option('ydtbwp_push_plugins', json_encode([])));

        $all_slugs = [];
        foreach ($all_plugins as $key => $plugin) {
            $slug = explode("/", $key)[0];
            $all_slugs[$slug] = $plugin['Name'];
        }

        $menu = (new CliMenuBuilder)
            ->setTitle('Choose Plugins To Push')
            ->modifyCheckboxStyle(function (CheckboxStyle $style) {
                $style->setUncheckedMarker('[○] ')
                    ->setCheckedMarker('[●] ');
            })
            ->addStaticItem('Check the plugins that should be pushed to the tracking system')
            ->addStaticItem(' ');

        for ($i = 0; $i < count($all_slugs); $i++) {

            $slug = array_keys($all_slugs)[$i];

            $vendorName = null;

            if (isset($tracked->{$slug})) {
                $vendorName = $tracked->{$slug};
            }

            if (isset($this->remote_plugins->$slug)) {
                $vendorName = $this->remote_plugins->$slug->vendor;
            }

            if (!isset($vendorName)) {
                $vendorName = "** Set Vendor **";
            }

            echo $vendorName . "\n";
            $name = $all_slugs[$slug];
            $menu->addSplitItem(function (SplitItemBuilder $b) use ($name, $updateTracked, $vendorName) {
                $b->setGutter(5)
                    ->addCheckboxItem($name, $updateTracked)
                    ->addStaticItem($vendorName);
            });

        }

        $menu
            ->addStaticItem(' ')
            ->addLineBreak('-');
        $menu = $menu->build();

        function getKeyByValue(array $array, $value)
        {
            foreach ($array as $key => $val) {
                if ($val === $value) {
                    return $key;
                }
            }
            return null;
        }

        $key = getKeyByValue($all_slugs, $name);

        foreach ($menu->getItems() as $item) {

            if ($item instanceof SplitItem) {
                $splitItems = $item->getItems();
                $firstItem = $splitItems[0];
                $pluginName = $firstItem->getText();
                $pluginSlug = getKeyByValue($all_slugs, $pluginName);
                if ($pluginSlug && isset($tracked->{$pluginSlug})) {
                    $firstItem->setChecked(true);
                    $this->selectedPlugins[$pluginSlug] = $tracked->{$pluginSlug};
                }
            } elseif ($item instanceof CheckboxItem) {
                $pluginName = $item->getText();
                $pluginSlug = getKeyByValue($all_slugs, $themeName);
                if ($themeSlug && isset($tracked->{$pluginSlug})) {
                    $item->setChecked(true);
                    $this->selectedPlugins[$pluginSlug] = $tracked->{$pluginSlug};
                }
            }
        }

        $menu->open();
    }
}
