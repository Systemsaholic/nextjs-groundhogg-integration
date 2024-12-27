<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
?>

<div class="settings-section">
    <h3><?php _e('Phone Number Settings', 'nextjs-groundhogg'); ?></h3>
    <form method="post" action="options.php">
        <?php
        settings_fields('nextjs_gh_phone_settings');
        do_settings_sections('nextjs_gh_phone_settings');
        
        $phone_settings = get_option('nextjs_gh_phone_settings', [
            'require_country_code' => true,
            'default_country_code' => '1',
            'format' => 'international'
        ]);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php _e('Phone Number Format', 'nextjs-groundhogg'); ?>
                </th>
                <td>
                    <select name="nextjs_gh_phone_settings[format]">
                        <option value="international" <?php selected($phone_settings['format'], 'international'); ?>>
                            <?php _e('International (+1-555-123-4567)', 'nextjs-groundhogg'); ?>
                        </option>
                        <option value="national" <?php selected($phone_settings['format'], 'national'); ?>>
                            <?php _e('National (555-123-4567)', 'nextjs-groundhogg'); ?>
                        </option>
                        <option value="raw" <?php selected($phone_settings['format'], 'raw'); ?>>
                            <?php _e('Raw (5551234567)', 'nextjs-groundhogg'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('Country Code', 'nextjs-groundhogg'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox"
                            name="nextjs_gh_phone_settings[require_country_code]"
                            value="1"
                            <?php checked($phone_settings['require_country_code']); ?>>
                        <?php _e('Require country code', 'nextjs-groundhogg'); ?>
                    </label>
                    <br>
                    <input type="text"
                        name="nextjs_gh_phone_settings[default_country_code]"
                        value="<?php echo esc_attr($phone_settings['default_country_code']); ?>"
                        class="small-text"
                        placeholder="1">
                    <p class="description">
                        <?php _e('Default country code to use when none is provided (e.g., "1" for US/Canada).', 'nextjs-groundhogg'); ?>
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