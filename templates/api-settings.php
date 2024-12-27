<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
?>

<div class="settings-section">
    <h3><?php _e('API Settings', 'nextjs-groundhogg'); ?></h3>
    <form method="post" action="options.php">
        <?php
        settings_fields('nextjs_gh_api_settings');
        do_settings_sections('nextjs_gh_api_settings');
        
        $api_settings = get_option('nextjs_gh_api_settings', [
            'cache_duration' => 300,
            'rate_limit_enabled' => true,
            'rate_limit' => 60
        ]);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Cache Duration', 'nextjs-groundhogg'); ?>
                </th>
                <td>
                    <input type="number"
                        name="nextjs_gh_api_settings[cache_duration]"
                        value="<?php echo esc_attr($api_settings['cache_duration']); ?>"
                        class="small-text"
                        min="0"
                        step="1">
                    <?php _e('seconds', 'nextjs-groundhogg'); ?>
                    <p class="description">
                        <?php _e('How long to cache API responses. Set to 0 to disable caching.', 'nextjs-groundhogg'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('Rate Limiting', 'nextjs-groundhogg'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox"
                            name="nextjs_gh_api_settings[rate_limit_enabled]"
                            value="1"
                            <?php checked($api_settings['rate_limit_enabled']); ?>>
                        <?php _e('Enable rate limiting', 'nextjs-groundhogg'); ?>
                    </label>
                    <br>
                    <input type="number"
                        name="nextjs_gh_api_settings[rate_limit]"
                        value="<?php echo esc_attr($api_settings['rate_limit']); ?>"
                        class="small-text"
                        min="1"
                        step="1">
                    <?php _e('requests per minute', 'nextjs-groundhogg'); ?>
                    <p class="description">
                        <?php _e('Maximum number of API requests allowed per minute per IP address.', 'nextjs-groundhogg'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>

<style>
.settings-section {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-top: 20px;
}
</style> 