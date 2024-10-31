<?php

namespace YDTBWP\Commands;

use PhpSchool\CliMenu\Action\ExitAction;
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
    private $all_slugs = [];
    private $currentPage = 1;
    private $itemsPerPage = 10;

    public function __construct($type)
    {
        $this->type = $type;
        $this->remote_items = Requests::getRemoteData($type . 's');
        $this->all_items = $this->type === 'theme' ? wp_get_themes() : get_plugins();
        $this->tracked = json_decode(get_option('ydtbwp_push_' . $this->type . 's', json_encode([])));
        $this->setAllSlugs();
    }

    private function setAllSlugs()
    {
        foreach ($this->all_items as $key => $item) {
            $slug = explode("/", $key)[0];
            $this->all_slugs[$slug] = $item['Name'];
        }
    }

    public function getSelectedItems()
    {
        return $this->selectedItems;
    }

    public function build()
    {
        $menu = (new CliMenuBuilder)
            ->enableAutoShortcuts()
            ->disableDefaultItems()
            ->setTitle('Choose ' . ucfirst($this->type) . 's To Push')
            ->modifyCheckboxStyle(function (CheckboxStyle $style) {
                $style->setUncheckedMarker('[○] ')
                    ->setCheckedMarker('[●] ');
            })
            ->addStaticItem('Check the ' . $this->type . 's that should be pushed to the tracking system')
            ->addStaticItem(' ');

        $this->addNavItemsToMenu($menu);

        $start = ($this->currentPage - 1) * $this->itemsPerPage;
        $end = $start + $this->itemsPerPage;
        $items = array_slice($this->all_slugs, $start, $this->itemsPerPage, true);

        $this->addItemsToMenu($menu, $items);

        $menu
            ->addStaticItem(' ')
            ->addLineBreak('-')
            ->addItem('[x] Exit ', new ExitAction); //add an exit button;
        $menu = $menu->build();

        $this->setTrackedMenuItems($menu);

        $menu->open();
    }

    private function addNavItemsToMenu(CliMenuBuilder $menu)
    {
        if (count($this->all_slugs) > $this->itemsPerPage) {
            $menu->addSplitItem(function (SplitItemBuilder $b) {
                $b->setGutter(5)
                    ->addStaticItem('Page ' . $this->currentPage);
                if ($this->currentPage > 1) {
                    $b->addItem('Previous [<]', function (CliMenu $menu) {
                        $this->currentPage--;
                        $menu->close();
                        $this->build();
                    });
                } else {
                    $b->addStaticItem(' ');
                }
                if ($this->currentPage * $this->itemsPerPage < count($this->all_slugs)) {
                    $b->addItem('Next [>]', function (CliMenu $menu) {
                        $this->currentPage++;
                        $menu->close();
                        $this->build();
                    });
                } else {
                    $b->addStaticItem(' ');
                }
            });
            $menu->addLineBreak('-');
        }
    }

    private function addItemsToMenu(CliMenuBuilder $menu, $items)
    {
        foreach ($items as $slug => $name) {
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

            $menu->addSplitItem(function (SplitItemBuilder $b) use ($name, $slug, $vendorName, $i) {
                $b->setGutter(5)
                    ->addCheckboxItem($name, function (CliMenu $menu) use ($slug) {
                        $this->updateTrackedCallback($menu, $slug);
                    })
                    ->addStaticItem($vendorName);
            });
        }
    }

    private function setTrackedMenuItems(CliMenu $menu)
    {
        $getKeyByValue = function (array $array, $value) {
            foreach ($array as $key => $val) {
                if ($val === $value) {
                    return $key;
                }
            }
            return null;
        };

        $itemTracked = function ($slug) {
            foreach ($this->tracked as $key => $value) {
                if ($key === $slug) {
                    return true;
                }
            }
            return false;
        };

        foreach ($menu->getItems() as $item) {
            if ($item instanceof SplitItem) {
                $splitItems = $item->getItems();
                $firstItem = $splitItems[0];
                $itemName = $firstItem->getText();
                $itemSlug = $getKeyByValue($this->all_slugs, $itemName);

                if ($itemSlug && $itemTracked($itemSlug)) {
                    $firstItem->setChecked(true);
                    $this->selectedItems[$itemSlug] = $this->tracked->{$itemSlug};
                }
            } elseif ($item instanceof CheckboxItem) {
                $itemName = $item->getText();
                $itemSlug = $getKeyByValue($this->all_slugs, $itemName);
                if ($itemSlug && isset($this->tracked->{$itemSlug})) {
                    $item->setChecked(true);
                    $this->selectedItems[$itemSlug] = $this->tracked->{$itemSlug};
                }
            }
        }
    }

    private function updateTrackedCallback(CliMenu $menu, $slug)
    {
        $item = $menu->getSelectedItem();
        $selection = $item->getText();

        if ($item->getChecked()) {

            if (isset($this->tracked->{$slug})) {
                $vendor = $this->tracked->{$slug};
            }

            if (isset($this->remote_items->{$slug})) {
                $vendor = $this->remote_items->{$slug}->vendor;
            }

            if (!isset($vendor)) {
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

            if (empty($vendor)) {
                $menu->flash("Vendor name cannot be empty");
                return;
            }

            if (!isset($this->remote_items->{$slug})) {
                $shouldPush = $menu->cancellableConfirm('Do you want to push local files now?')
                    ->display('Yes', 'No');

                if ($shouldPush) {
                    $pushItem = new \stdClass();
                    $pushItem->name = $selection;
                    $pushItem->vendor = $vendor;
                    $pushItem->type = $this->type;
                    $pushItem->slug = $slug;

                    do_action("ydtbwp_push_local_{$this->type}", $pushItem);
                    sleep(5);
                } else {
                    $menu->flash('Local files will not be pushed');
                }
            }

            $this->selectedItems[$slug] = $vendor;

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
            unset($this->selectedItems[$slug]);
        }
        update_option('ydtbwp_push_' . $this->type . 's', json_encode($this->selectedItems));
        $this->tracked = $this->selectedItems;
    }
}
