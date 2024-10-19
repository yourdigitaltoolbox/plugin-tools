<?php

namespace YDTBWP\Commands;

use PhpSchool\CliMenu\Action\GoBackAction;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\Builder\SplitItemBuilder;
use PhpSchool\CliMenu\CliMenu;

class SetupMenu
{

    function __construct()
    {
        $this->github_token = get_option('ydtbwp_github_token', '');
        $this->update_workflow_url = get_option('ydtbwp_workflow_url', '');
        $this->fetchURL = get_option('ydtbwp_fetch_host', '');
        $this->automatic_updates = get_option('ydtbwp_plugin_auto_update', false);

    }

    private $github_token = '';
    private $update_workflow_url = '';
    private $fetchURL = '';
    private $automatic_updates = false;

    private $setupDidFinish = null;

    public function setupPage(): void
    {
        $createPrompt = function (string $promptText, string $validationFailedText, string $property, string $staticItemPrefix) {
            return function (CliMenu $menu) use ($promptText, $validationFailedText, $property, $staticItemPrefix) {
                $result = $menu->askText()
                    ->setPromptText($promptText)
                    ->setPlaceholderText('')
                    ->setValidationFailedText($validationFailedText)
                    ->ask();

                if ($result->fetch() === '') {
                    return;
                }
                $this->$property = $result->fetch();

                foreach ($menu->getItems() as $item) {
                    if ($item instanceof \PhpSchool\CliMenu\MenuItem\StaticItem  && strpos($item->getText(), $staticItemPrefix) === 0) {
                        $item->setText($staticItemPrefix . ($this->$property ? $this->$property : 'Not Set'));
                    }
                }
                $menu->redraw();
            };
        };

        $github_token_prompt = $createPrompt('Enter Personal Access Token', 'Please Enter A Token', 'github_token', 'Current Token: ');
        $update_workflow_url_prompt = $createPrompt('Enter Update Workflow URL', 'Please Enter A Valid URL', 'update_workflow_url', 'Update Workflow URL:');
        $fetch_url_prompt = $createPrompt('Enter Data Fetch URL', 'Please Enter A Valid URL', 'fetchURL', 'Data Fetch URL: ');

        $save_items = function (CliMenu $menu) {
            echo "Saving...\n";
            update_option('ydtbwp_github_token', $this->github_token);
            update_option('ydtbwp_workflow_url', $this->update_workflow_url);
            update_option('ydtbwp_fetch_host', $this->fetchURL);
            update_option('ydtbwp_plugin_auto_update', $this->automatic_updates);
            $menu->close();
        };

        $exit_no_save = function (CliMenu $menu) {
            $menu->close();
        };

        $menu = (new CliMenuBuilder)
            ->setExitButtonText("Next")
            ->setWidth(120)
            ->setTitle('Setup Plugin-Tools Package Tracking')
            ->addStaticItem('')
            ->addSplitItem(function (SplitItemBuilder $b) use ($github_token_prompt) {
                $b->setGutter(5)
                    ->addItem('Set Token', $github_token_prompt)
                    ->addSubMenu('Additional Token Instructions', function (CliMenuBuilder $b) {
                        $b->disableDefaultItems()
                            ->setTitle('Github Token Information')
                            ->addStaticItem('You can create one here:')
                            ->addStaticItem('https://github.com/settings/personal-access-tokens/new')
                            ->addStaticItem('') // add a blank line
                            ->addStaticItem('It only needs access to trigger workflows on the main repository')
                            ->addStaticItem('') // add a blank line
                            ->addItem('Back', new GoBackAction); // add a go back button
                    });
            })
            ->addStaticItem('Current Token: ' . ($this->github_token ? '******' : 'Not Set'))
            ->addStaticItem('')

            ->addSplitItem(function (SplitItemBuilder $b) use ($update_workflow_url_prompt) {
                $b->setGutter(5)
                    ->addItem('Set Update Workflow URL', $update_workflow_url_prompt)
                    ->addSubMenu('Additional Info Update Workflow URL', function (CliMenuBuilder $b) {
                        $b->disableDefaultItems()
                            ->setTitle('Update Workflow URL Information')
                            ->addStaticItem('Please copy the URL from the Github Workflow Dispatch for automatic updates')
                            ->addStaticItem('It should look like this:')
                            ->addStaticItem('') // add a blank line
                            ->addStaticItem('https://api.github.com/repos/<YourORG>/<YourORG>.github.io/actions/workflows/report-update-list.yml/dispatches')
                            ->addStaticItem('') // add a blank line
                            ->addItem('Back', new GoBackAction); // add a go back button
                    });
            })
            ->addStaticItem('Update Workflow URL: ' . ($this->update_workflow_url ? $this->update_workflow_url : 'Not Set'))
            ->addStaticItem('')

            ->addSplitItem(function (SplitItemBuilder $b) use ($fetch_url_prompt) {
                $b->setGutter(5)
                    ->addItem('Set the Database.json URL', $fetch_url_prompt)
                    ->addSubMenu('Additional URL Instructions', function (CliMenuBuilder $b) {
                        $b->disableDefaultItems()
                            ->setTitle('Database URL Endpoint Information')
                            ->addStaticItem('Please copy the URL from the database.json file in your repository')
                            ->addStaticItem('It should look like this:')
                            ->addStaticItem('') // add a blank line
                            ->addStaticItem('https://<YourOrg>.github.io/database.json')
                            ->addStaticItem('') // add a blank line
                            ->addItem('Back', new GoBackAction); // add a go back button
                    });
            })
            ->addStaticItem('Data Fetch URL: ' . ($this->fetchURL ? $this->fetchURL : 'Not Set'))
            ->addStaticItem('')

            ->addSplitItem(function (SplitItemBuilder $b) {
                $b->setGutter(5)
                    ->addCheckboxItem('Enable Automatic Updates', function (CliMenu $menu) {
                        $this->automatic_updates = !$this->automatic_updates;
                        foreach ($menu->getItems() as $item) {
                            if ($item instanceof \PhpSchool\CliMenu\MenuItem\StaticItem  && strpos($item->getText(), 'Current Status:') === 0) {
                                $item->setText('Current Status: ' . ($this->automatic_updates ? 'Enabled' : 'Disabled'));
                            }
                        }
                        $menu->redraw();
                    })
                    ->addSubMenu('Additional Info', function (CliMenuBuilder $b) {
                        $b->disableDefaultItems()
                            ->setTitle('Automatic Updates Information')
                            ->addStaticItem('') // add a blank line
                            ->addStaticItem('If enabled, the plugin will automatically update the selected plugins')
                            ->addStaticItem('when a new version is available after capturing the new version in GitHub.')
                            ->addStaticItem('') // add a blank line
                            ->addItem('Back', new GoBackAction); // add a go back button
                    });
            })
            ->addStaticItem('Current Status: ' . ($this->automatic_updates ? 'Enabled' : 'Disabled'))

            ->addStaticItem('')

            ->addLineBreak('-')
            ->addSplitItem(function (SplitItemBuilder $b) use ($save_items, $exit_no_save) {
                $b->setGutter(5)
                    ->addItem('Save & Exit', $save_items)
                    ->addItem('Exit Without Saving', $exit_no_save);
            })
            ->disableDefaultItems();

        $menu = $menu->build();

        foreach ($menu->getItems() as $item) {
            if ($item instanceof \PhpSchool\CliMenu\MenuItem\SplitItem) {
                foreach ($item->getItems() as $subItem) {
                    if ($subItem instanceof \PhpSchool\CliMenu\MenuItem\CheckboxItem  && $subItem->getText() === 'Enable Automatic Updates') {
                        if ($this->automatic_updates) {
                            $subItem->setChecked();
                        }
                    }
                }
            }
        }
        $menu->open();
    }
}
