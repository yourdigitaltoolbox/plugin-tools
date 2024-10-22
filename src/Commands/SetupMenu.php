<?php

namespace YDTBWP\Commands;

use PhpSchool\CliMenu\Action\GoBackAction;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use PhpSchool\CliMenu\Builder\SplitItemBuilder;
use PhpSchool\CliMenu\CliMenu;
use YDTBWP\Utils\Encryption;

class SetupMenu
{

    function __construct()
    {
        $data_encryption = new Encryption();
        $api_token = get_option('ydtbwp_github_token', '');
        $this->github_token = $api_token ? $data_encryption->decrypt($api_token) : '';
        $this->update_workflow_url = get_option('ydtbwp_workflow_url', '');
        $this->fetchURL = get_option('ydtbwp_fetch_host', '');
        $this->automatic_updates = get_option('ydtbwp_plugin_auto_update', false);
        $this->push_strategy = get_option('ydtbwp_update_strategy', 'remote');

    }

    private $github_token = '';
    private $update_workflow_url = '';
    private $fetchURL = '';
    private $automatic_updates = false;
    private $push_strategy = '';
    private $push_methods = ['Remote', 'Local', 'Simple'];

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

            $data_encryption = new Encryption();
            $api_token_encrypted = $data_encryption->encrypt($this->github_token);

            echo "Saving...\n";
            update_option('ydtbwp_github_token', $api_token_encrypted);
            update_option('ydtbwp_workflow_url', $this->update_workflow_url);
            update_option('ydtbwp_fetch_host', $this->fetchURL);
            update_option('ydtbwp_plugin_auto_update', $this->automatic_updates);
            $menu->close();
        };

        $exit_no_save = function (CliMenu $menu) {
            $menu->close();
        };

        $push_strategy_cb = function (CliMenu $menu) {
            $selected = $menu->getSelectedItem()->getText();
            update_option('ydtbwp_update_strategy', strtolower($selected));
            foreach ($menu->getItems() as $item) {
                if ($item instanceof \PhpSchool\CliMenu\MenuItem\SplitItem) {
                    foreach ($item->getItems() as $subItem) {
                        if ($subItem instanceof \PhpSchool\CliMenu\MenuItem\StaticItem  && strpos($subItem->getText(), 'Current Push Strategy:') === 0) {
                            $subItem->setText('Current Push Strategy: ' . $selected);
                        }
                    }
                }
            }
            $menu->redraw();
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
                    ->addItem('Set GitHub Update Workflow Dispatch URL', $update_workflow_url_prompt)
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
            ->addStaticItem('URL: ' . ($this->update_workflow_url ? $this->update_workflow_url : 'Not Set'))
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
                    ->addCheckboxItem('Enable Automatic Updates After Version Capture', function (CliMenu $menu) {
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

            ->addSplitItem(function (SplitItemBuilder $b) {
                $b->setGutter(5)
                    ->addStaticItem('Current Push Strategy: ' . (ucFirst($this->push_strategy)))
                    ->addSubMenu('Push Strategy Information', function (CliMenuBuilder $b) {
                        $b->disableDefaultItems()
                            ->setTitle('Push Strategy Information')
                            ->addStaticItem('') // add a blank line
                            ->addStaticItem('Here are the 3 push methods: Remote, Local, Simple')

                            ->addStaticItem('') // add a blank line
                            ->addLineBreak('-')
                            ->addStaticItem('Remote:')
                            ->addStaticItem('This method will download the file from the remote server and upload it to the S3 bucket.')
                            ->addStaticItem('Note: There is additional setup required to use this method.')
                            ->addStaticItem('') // add a blank line
                            ->addStaticItem('Local:')
                            ->addStaticItem('This method will download the file from the remote server and store it locally.')
                            ->addStaticItem('Note: The Requirement here is that the website be publically avaialbe so that the remote')
                            ->addStaticItem('server can download the file from this site.')
                            ->addStaticItem('') // add a blank line
                            ->addStaticItem('Simple:')
                            ->addStaticItem('This method doesn\'t process the data at all, It just sends the data to the server.')
                            ->addStaticItem('Note: Doing this will likely expose your plugin license keys if the remote workflow is public.')
                            ->addStaticItem('Because of this vulnerability, we recommend using one of the other strategies. ')

                            ->addStaticItem('') // add a blank line
                            ->addItem('Back', new GoBackAction); // add a go back button
                    });
            })
            ->addStaticItem('-----------------------------');

        foreach ($this->push_methods as $method) {
            $menu->addRadioItem(ucFirst($method), $push_strategy_cb);
        };

        $menu->addStaticItem('');

        $menu->addSubMenu('Set S3 Connection Information', function (CliMenuBuilder $b) {

            $s3 = new \YDTBWP\Utils\AwsS3();
            $s3->loadS3DataFromOptions();
            $data = $s3->getData();

            $createS3Prompt = function (string $promptText, string $validationFailedText, string $configKey, string $staticItemPrefix) use ($s3) {
                return function (CliMenu $menu) use ($promptText, $validationFailedText, $configKey, $staticItemPrefix, $s3) {
                    $result = $menu->askText()
                        ->setPromptText($promptText)
                        ->setPlaceholderText('')
                        ->setValidationFailedText($validationFailedText)
                        ->ask();

                    if ($result->fetch() === '') {
                        return;
                    }
                    $config = [$configKey => $result->fetch()];
                    $s3->updateS3Config($config);

                    foreach ($menu->getItems() as $item) {
                        if ($item instanceof \PhpSchool\CliMenu\MenuItem\SplitItem) {
                            foreach ($item->getItems() as $subItem) {
                                if ($subItem instanceof \PhpSchool\CliMenu\MenuItem\StaticItem  && strpos($subItem->getText(), $staticItemPrefix) === 0) {
                                    $subItem->setText($staticItemPrefix . $config[$configKey]);
                                }
                            }
                        }
                    }
                    $menu->redraw();
                };
            };

            $s3_key_prompt = $createS3Prompt('Enter S3 Key', 'Please Enter A Valid Key', 'keyID', 'Current S3 Key: ');
            $s3_secret_prompt = $createS3Prompt('Enter S3 Secret', 'Please Enter A Valid Secret', 'secretKey', 'Current S3 Secret: ');
            $s3_bucket_prompt = $createS3Prompt('Enter S3 Bucket', 'Please Enter A Valid Bucket', 'bucket', 'Current S3 Bucket: ');
            $s3_region_prompt = $createS3Prompt('Enter S3 Region', 'Please Enter A Valid Region', 'region', 'Current S3 Region: ');

            $b->setTitle('S3 Information')
                ->addStaticItem('');
            $s3_prompts = [
                'keyID' => ['prompt' => $s3_key_prompt, 'label' => 'S3 Key', 'data' => $data['accessKeyID']],
                'secretKey' => ['prompt' => $s3_secret_prompt, 'label' => 'S3 Secret', 'data' => $data['secretAccessKey']],
                'bucket' => ['prompt' => $s3_bucket_prompt, 'label' => 'S3 Bucket', 'data' => $data['bucketName']],
                'region' => ['prompt' => $s3_region_prompt, 'label' => 'S3 Region', 'data' => $data['region']],
            ];

            foreach ($s3_prompts as $configKey => $config) {
                $b->addSplitItem(function (SplitItemBuilder $b) use ($config) {
                    $b->setGutter(5)
                        ->addStaticItem('Current ' . $config['label'] . ': ' . $config['data'])
                        ->addItem('Set ' . $config['label'], $config['prompt']);
                });
            }

            $b->addStaticItem('')
                ->addLineBreak('-')
                ->setExitButtonText("Exit Plugin Tools Setup");
        });

        $menu->addStaticItem('')

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

            echo get_class($item) . "\n";

            if (get_class($item) == "PhpSchool\CliMenu\MenuItem\RadioItem") {
                $update_strategy = get_option('ydtbwp_update_strategy', 'remote');
                $strategies = ['Remote', 'Local', 'Simple'];

                foreach ($strategies as $strategy) {
                    echo $strategy . ' ' . $update_strategy . "\n";
                    if ($item->getText() === $strategy && strtolower($strategy) === $update_strategy) {
                        $item->setChecked();
                    }
                }
            }
        }
        $menu->open();
    }
}
