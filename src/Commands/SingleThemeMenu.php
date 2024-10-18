<?php

namespace YDTBWP\Commands;

use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\CliMenu;

class SinglePluginMenu
{

    function __construct($plugins_to_push, $remote_plugins)
    {
        $this->plugins_to_push = $plugins_to_push;
        $this->remote_plugins = $remote_plugins;
    }
    private $plugins_to_push = [];
    private $remote_plugins = [];

    private $selected = '';
    private $vendor = '';

    public function setItem(CliMenu $menu)
    {
        $selection = $menu->getSelectedItem()->getText();
        // if the plugin is not in the remote list then we need to collect the vendor.

        if (!isset($this->remote_plugins->$selection)) {
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
            if (!$vendor == '') {
                $this->vendor = $vendor;
                $menu->close();
            }
        }

        $this->selected = $selection;

    }

    public function getItem()
    {
        return $this->selected;
    }

    public function getVendor()
    {
        return $this->vendor;
    }

    public function buildMenu(): void
    {
        $menu = (new CliMenuBuilder)
            ->setExitButtonText("Next")
            ->setTitle('Choose Single Plugin To Push')

            ->addStaticItem('Check the plugin that should be pushed to the tracking system')
            ->addStaticItem('Note: If the site is not public, the download from the remote repo will fail')
            ->addStaticItem(' ');

        foreach ($this->plugins_to_push as $key => $item) {
            $menu->addRadioItem($key, [$this, 'setItem']);
        }

        $menu
            ->addStaticItem(' ')
            ->addLineBreak('-');
        $menu = $menu->build();
        $menu->open();
    }
}
