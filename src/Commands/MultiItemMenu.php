<?php

namespace YDTBWP\Commands;

use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\Builder\SplitItemBuilder;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\MenuItem\CheckboxItem;
use PhpSchool\CliMenu\MenuItem\SplitItem;
use PhpSchool\CliMenu\Style\CheckboxStyle;
use YDTBWP\Utils\Requests;

class MultiItemMenu
{
    private $type;
    private $remote_items = [];
    private $selectedItems = [];
    private $all_items = [];
    private $tracked;

    public function __construct($type)
    {
        $this->type = $type;
        $this->remote_items = Requests::getRemoteData($type . 's');
        $this->all_items = $this->type === 'theme' ? wp_get_themes() : get_plugins();
        $this->tracked = json_decode(get_option('ydtbwp_push_' . $this->type . 's', json_encode([])));
    }

    public function getSelectedItems()
    {
        return $this->selectedItems;
    }

    public function build()
    {
        $updateTracked = function (CliMenu $menu) {
            $item = $menu->getSelectedItem();
            $selection = $item->getText();

            $item_slug = null;
            foreach ($this->all_items as $key => $item_data) {
                if ($item_data['Name'] === $selection) {
                    $item_slug = $this->type === 'theme' ? $key : explode("/", $key)[0];
                    break;
                }
            }

            if ($item->getChecked()) {

                if (isset($this->tracked->{$item_slug})) {
                    $vendor = $this->tracked->{$item_slug};
                }

                if (isset($this->remote_items->$item_slug)) {
                    $vendor = $this->remote_items->$item_slug->vendor;
                    echo "Vendor Set Remotely: " . $vendor;
                }

                if (!isset($vendor)) {
                    echo ucfirst($this->type) . " not found in remote list, please enter the vendor name\n";
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

                $this->selectedItems[$item_slug] = $vendor;

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
                unset($this->selectedItems[$item_slug]);
            }
        };

        $all_slugs = [];
        foreach ($this->all_items as $key => $item) {
            $slug = explode("/", $key)[0];
            $all_slugs[$slug] = $item['Name'];
        }

        $menu = (new CliMenuBuilder)
            ->setTitle('Choose ' . ucfirst($this->type) . 's To Push')
            ->modifyCheckboxStyle(function (CheckboxStyle $style) {
                $style->setUncheckedMarker('[○] ')
                    ->setCheckedMarker('[●] ');
            })
            ->addStaticItem('Check the ' . $this->type . 's that should be pushed to the tracking system')
            ->addStaticItem(' ');

        for ($i = 0; $i < count($all_slugs); $i++) {

            $slug = array_keys($all_slugs)[$i];

            $vendorName = null;

            if (isset($this->tracked->{$slug})) {
                $vendorName = $this->tracked->{$slug};
            }

            if (isset($this->remote_items->$slug)) {
                $vendorName = $this->remote_items->$slug->vendor;
            }
            if (!isset($vendorName)) {
                $vendorName = "** Set Vendor **";
            }

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
                $itemName = $firstItem->getText();
                $itemSlug = getKeyByValue($all_slugs, $itemName);

                if ($itemSlug && isset($this->tracked->{$itemSlug})) {
                    $firstItem->setChecked(true);
                    $this->selectedItems[$itemSlug] = $this->tracked->{$itemSlug};
                }
            } elseif ($item instanceof CheckboxItem) {
                $itemName = $item->getText();
                $itemSlug = getKeyByValue($all_slugs, $itemName);
                if ($itemSlug && isset($this->tracked->{$itemSlug})) {
                    $item->setChecked(true);
                    $this->selectedItems[$itemSlug] = $this->tracked->{$itemSlug};

                }
            }
        }

        $menu->open();
    }
}
