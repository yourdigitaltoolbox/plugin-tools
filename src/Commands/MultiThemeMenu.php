<?php

namespace YDTBWP\Commands;

use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\Builder\SplitItemBuilder;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Style\CheckboxStyle;
use YDTBWP\Utils\Requests;

class MultiThemeMenu
{

    function __construct()
    {
        $this->remote_themes = Requests::getRemoteData();
    }

    private $remote_themes = [];

    private $selectedThemes = [];

    public function getSelectedThemes()
    {
        return $this->selectedThemes;
    }

    public function build()
    {
        $updateTracked = function (CliMenu $menu) {

            echo "Selection Made\n";

            $item = $menu->getSelectedItem();
            $selection = $item->getText();

            $all_themes = wp_get_themes();

            $theme_slug = null;
            foreach ($all_themes as $key => $theme) {
                if ($theme['Name'] === $selection) {
                    $theme_slug = $key;
                    break;
                }
            }

            if (!isset($theme_slug)) {
                echo "Theme not found in local themes\n";
                return;
            }

            echo "Theme Slug: " . $theme_slug;

            if ($item->getChecked()) {

                $tracked = json_decode(get_option('ydtbwp_push_themes', json_encode([])));

                if (isset($tracked->{$theme_slug})) {
                    $vendor = $tracked->{$theme_slug};
                }
                // if there is a remote vendor then we should use that.
                if (isset($this->remote_themes->$selection)) {
                    $vendor = $this->remote_themes->$selection->vendor;
                    echo "Vendor Set Remotely: " . $vendor;
                }

                // if the theme is not in the remote list then we need to collect the vendor.
                if (!isset($vendor)) {
                    echo "Theme not found in remote list, please enter the vendor name\n";
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

                $this->selectedThemes[$theme_slug] = $vendor;

                // update the menu item with the vendor name
                $menuItems = $menu->getItems();

                foreach ($menuItems as $item) {
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
                unset($this->selectedThemes[$theme_slug]);
            }
        };

        $all_themes = wp_get_themes();
        $tracked = json_decode(get_option('ydtbwp_push_themes', json_encode([])));
        $all_slugs = array_map(function ($theme) {
            return $theme["Name"];
        }, $all_themes);
        $menu = (new CliMenuBuilder)
            ->setTitle('Choose Themes To Push')
            ->modifyCheckboxStyle(function (CheckboxStyle $style) {
                $style->setUncheckedMarker('[○] ')
                    ->setCheckedMarker('[●] ');
            })
            ->addStaticItem('Check the themes that should be pushed to the tracking system')
            ->addStaticItem(' ');

        for ($i = 0; $i < count($all_slugs); $i++) {

            $slug = array_keys($all_slugs)[$i];
            $name = $all_themes[$slug]->Name;

            $vendorName = null;
            if (isset($tracked->$slug)) {
                $vendorName = $tracked->$slug;
            }

            if (isset($this->remote_themes->$slug)) {
                $vendorName = $this->remote_themes->$slug->vendor;
            }

            if (!isset($vendorName)) {
                $vendorName = "** Set Vendor **";
            }

            echo $vendorName . "\n";

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

        foreach ($menu->getItems() as $item) {

            if ($item instanceof \PhpSchool\CliMenu\MenuItem\SplitItem) {
                $splitItems = $item->getItems();
                $firstItem = $splitItems[0];
                if (isset($tracked->{$firstItem->getText()})) {
                    $firstItem->setChecked(true);
                    $this->selectedThemes[$firstItem->getText()] = $tracked->{$firstItem->getText()};
                }
            } elseif ($item instanceof \PhpSchool\CliMenu\MenuItem\CheckboxItem) {
                if (isset($tracked->{$item->getText()})) {
                    $item->setChecked(true);
                    $this->selectedThemes[$item->getText()] = $tracked->{$item->getText()};
                }
            }
        }

        $menu->open();
    }
}
