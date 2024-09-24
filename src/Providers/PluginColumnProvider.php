<?php
namespace YDTBWP\Providers;

class PluginColumnProvider implements Provider
{
    public function register()
    {
        // Add columns
        add_filter('manage_plugins_columns', [$this, 'add_custom_plugin_column']);
        add_action('manage_plugins_custom_column', [$this, 'display_custom_plugin_column_content'], 10, 3);

        // Handle the form submission when saving the plugin checkbox state
        add_action('admin_init', [$this, 'save_plugin_checkbox_state']);

        // Add save button in the plugin page footer
        add_action('admin_footer-plugins.php', [$this, 'add_save_button']);
    }

    // Add custom columns
    public function add_custom_plugin_column($columns)
    {
        // Add new column with a unique key
        $columns['custom_checkbox'] = __('Push Plugin', 'textdomain');
        $columns['custom_vendor'] = __('Vendor', 'textdomain');
        return $columns;
    }

    // Display the content for the custom columns
    public function display_custom_plugin_column_content($column_name, $plugin_file, $plugin_data)
    {
        $plugin_slug = explode('/', $plugin_file)[0];

        // Output checkbox for the 'Push Plugin' column
        if ($column_name === 'custom_checkbox') {

            $checked_plugins = get_option('ydtb_push_plugins', []);
            echo var_dump($checked_plugins);

            $checked = in_array($plugin_file, $checked_plugins) ? 'checked' : '';
            echo '<input type="checkbox" name="plugin_checkbox_' . $plugin_slug . '" value="' . $checked . '>';
        }

        // Output a text field for the 'Vendor' column (can be further enhanced)
        if ($column_name === 'custom_vendor') {
            echo '<input type="text" name="" value="">';
        }
    }

    // Save the state of the checkbox
    public function save_plugin_checkbox_state()
    {
        // Check if the form was submitted and the plugin page is being displayed
        if (isset($_POST['plugin_checkbox']) && current_user_can('manage_options')) {
            echo var_dump($_POST);
            // Sanitize and save the checked plugins in the options table
            // $checked_plugins = array_map('sanitize_text_field', $_POST['plugin_checkbox']);
            // update_option('ydtb_push_plugins', $checked_plugins);
        } elseif (current_user_can('manage_options')) {
            // If no checkboxes are checked, clear the option
            update_option('ydtb_push_plugins', []);
        }
    }

    // Add a Save button to the Plugins page
    public function add_save_button()
    {
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                // Add Save button to the plugin page form (inside the form)
                var form = document.getElementById('bulk-action-form'); // Form on the plugins page

                if (form) {
                    var saveButton = document.createElement('button');
                    saveButton.type = 'submit';
                    saveButton.className = 'button button-primary';
                    saveButton.name = 'save_plugin_checkbox_state'; // Add a name to identify submission
                    saveButton.innerHTML = '<?php echo esc_html__('Save Plugin Selections', 'textdomain'); ?>';

                    // Append the button to the form (e.g., before the table)
                    form.appendChild(saveButton);
                }
            });
        </script>
        <?php
}
}
