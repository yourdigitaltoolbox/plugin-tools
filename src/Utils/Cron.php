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
        $schedules['5min'] = array(
            'interval' => 60 * 5,
            'display' => __('5 Minutes'),
        );
        $schedules['2min'] = array(
            'interval' => 60 * 2,
            'display' => __('2 Minutes'),
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
        echo "Cron Started\n";
        ob_start();
        do_action('ydtbwp_update_plugins', false);
        $output = ob_get_contents();
        ob_end_clean();

        $currentDateTime = new \DateTime('now');
        $currentDateTimeString = $currentDateTime->format('Y-m-d_H:i:s');

        if (!file_exists('./logs/')) {
            mkdir('./logs/', 0777, true);
        }

        echo "Logging to ./logs/log-$currentDateTimeString.txt\n";

        $file = fopen("./logs/log-$currentDateTimeString.txt", 'w');
        fwrite($file, "Cron ran at: " . $currentDateTimeString . "\n");
        fwrite($file, $output);
        fclose($file);
        echo $output;
    }
}
