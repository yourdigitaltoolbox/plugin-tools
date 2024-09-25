<?php
// info i got actual working info from https://wp-kama.com/function/wp_schedule_event

namespace YDTBWP\Utils;

class Cron
{
    public function __construct()
    {
        add_action("ydtb_check_update_cron", [$this, "runner"]);
        add_filter('cron_schedules', [$this, 'set_schedules']);
    }

    public function set_schedules($schedules)
    {
        $schedules['15min'] = array(
            'interval' => 60 * 15,
            'display' => __('Quarter Hour'),
        );
        return $schedules;
    }

    public function setup_cron_schedule()
    {
        if (!wp_next_scheduled('ydtbwp_cron')) {
            wp_schedule_event(time(), '15min', 'ydtb_check_update_cron');
        }
    }

    public function clear_cron_schedule()
    {
        wp_clear_scheduled_hook('ydtb_check_update_cron');
    }

    public function runner()
    {

        $file = fopen('./cronjob.txt', 'a');
        fwrite($file, sprintf('Hello World [%d]', time()));
        fclose($file);
    }
}
