<?php

namespace YDTBWP\Commands;

class PluginNameCommand extends \WP_CLI_Command
{
    public function __construct()
    {
        parent::__construct();
    }

    public function howdy()
    {
        \WP_CLI::success('Howdy!');
    }
}
