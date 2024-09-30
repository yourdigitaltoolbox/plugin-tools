<?php

namespace YDTBWP\Commands;

use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\Builder\SplitItemBuilder;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Style\CheckboxStyle;
use YDTBWP\Utils\Requests;

class MultiPluginMenu
{

    function __construct()
    {
        $this->remote_plugins = Requests::getRemotePlugins();
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

            if ($item->getChecked()) {

                $tracked = json_decode(get_option('ydtbwp_push_plugins', []));
                if (isset($tracked->{$item->getText()})) {
                    $vendor = $tracked->{$item->getText()};
                }
                // if there is a remote vendor then we should use that.
                if (isset($this->remote_plugins->$selection)) {
                    $vendor = $this->remote_plugins->$selection->vendor;
                    echo "Vendor Set Remotely: " . $vendor;
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

                $this->selectedPlugins[$selection] = $vendor;

                // update the menu item with the vendor name
                $menuItems = $menu->getItems();

                foreach ($menuItems as $item) {
                    echo "running through menu items";
                    if ($item instanceof \PhpSchool\CliMenu\MenuItem\SplitItem) {

                        $splitItems = $item->getItems();
                        $firstItem = $splitItems[0];
                        if ($firstItem->getText() == $selection) {
                            $splitItems[1]->setText($vendor);
                        }
                    }
                }
                $menu->redraw();

            } else {
                unset($this->selectedPlugins[$selection]);
            }
        };

        $all_plugins = get_plugins();
        $tracked = json_decode(get_option('ydtbwp_push_plugins', []));
        $all_slugs = array_map(function ($key) {
            return explode("/", $key)[0];
        }, array_keys($all_plugins));

        $menu = (new CliMenuBuilder)
            ->setTitle('Choose Plugins To Push')
            ->modifyCheckboxStyle(function (CheckboxStyle $style) {
                $style->setUncheckedMarker('[○] ')
                    ->setCheckedMarker('[●] ');
            })
            ->addStaticItem('Check the plugins that should be pushed to the tracking system')
            ->addStaticItem(' ');

        for ($i = 0; $i < count($all_slugs); $i++) {
            $item = $all_slugs[$i] ?? "";

            $trackedItem = null;

            if (isset($tracked->{$item})) {
                $trackedItem = $tracked->{$item};
            }

            if (isset($this->remote_plugins->$item)) {
                $trackedItem = $this->remote_plugins->$item->vendor;
            }

            if (!isset($trackedItem)) {
                $trackedItem = "** Set Vendor **";
            }

            echo $trackedItem . "\n";

            $menu->addSplitItem(function (SplitItemBuilder $b) use ($item, $updateTracked, $trackedItem) {
                $b->setGutter(5)
                    ->addCheckboxItem($item, $updateTracked)
                    ->addStaticItem($trackedItem);
            });

        }

        $menu
            ->addStaticItem(' ')
            ->addLineBreak('-');
        $menu = $menu->build();

        foreach ($menu->getItems() as $item) {

            if ($item instanceof \PhpSchool\CliMenu\MenuItem\SplitItem) {
                $splitItems = $item->getItems();
                $firstItem = $splitItems[0];
                if (isset($tracked->{$firstItem->getText()})) {
                    $firstItem->setChecked(true);
                    $this->selectedPlugins[$firstItem->getText()] = $tracked->{$firstItem->getText()};
                }
            } elseif ($item instanceof \PhpSchool\CliMenu\MenuItem\CheckboxItem) {
                if (isset($tracked->{$item->getText()})) {
                    $item->setChecked(true);
                    $this->selectedPlugins[$item->getText()] = $tracked->{$item->getText()};
                }
            }
        }

        $menu->open();
    }
}
