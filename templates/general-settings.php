<?php
if (!defined('ABSPATH')) exit;

try {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Basic Groundhogg check
    $groundhogg_active = false;
    $has_api_keys = false;
    
    // Hidden debug section
    echo '<div class="debug-section" style="display: none;">';
    
    if (class_exists('\Groundhogg\Plugin')) {
        $groundhogg_active = true;
        
        // Check for API keys in user meta
        global $wpdb;
        $users_with_keys = $wpdb->get_results(
            "SELECT user_id, meta_value as public_key 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'wpgh_user_public_key'"
        );
        
        if ($users_with_keys) {
            foreach ($users_with_keys as $user) {
                $secret_key = get_user_meta($user->user_id, 'wpgh_user_secret_key', true);
                if ($secret_key) {
                    $has_api_keys = true;
                    $user_info = get_userdata($user->user_id);
                    // Store the key info for env section
                    $api_user = $user_info->user_login;
                    $public_key = $user->public_key;
                    $token = hash('md5', $secret_key . $public_key);
                    break;
                }
            }
        }
    }
    echo '</div>';
    ?>

    <div class="settings-section">
        <?php if (!$groundhogg_active): ?>
            <div class="notice notice-error">
                <p><?php _e('Groundhogg CRM must be installed and activated to use the NextJS integration.', 'nextjs-groundhogg'); ?></p>
            </div>
        <?php else: ?>
            <?php if (!$has_api_keys): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('No active API keys found! You need to generate API keys in Groundhogg to use the NextJS integration.', 'nextjs-groundhogg'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gh_settings&tab=api_tab')); ?>" class="button button-secondary">
                            <?php _e('Generate API Keys', 'nextjs-groundhogg'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p>
                        <?php _e('API keys are configured and ready to use.', 'nextjs-groundhogg'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gh_settings&tab=api_tab')); ?>" class="button button-secondary">
                            <?php _e('Manage API Keys', 'nextjs-groundhogg'); ?>
                        </a>
                    </p>
                </div>

                <div class="env-section">
                    <h3><?php _e('Environment Variables', 'nextjs-groundhogg'); ?></h3>
                    <p class="description">
                        <?php _e('Copy these environment variables to your NextJS app\'s .env.local file:', 'nextjs-groundhogg'); ?>
                    </p>
                    <div class="env-content">
                        <code># Groundhogg API Configuration
NEXT_PUBLIC_GROUNDHOGG_API_ENDPOINT=<?php echo esc_html(rest_url('groundhogg/v4')); ?>

# Optional: Override API version (v3 or v4)
NEXT_PUBLIC_GROUNDHOGG_API_VERSION=v4

# Groundhogg Authentication
NEXT_PUBLIC_GROUNDHOGG_PUBLIC_KEY=<?php echo esc_html($public_key); ?>

NEXT_PUBLIC_GROUNDHOGG_TOKEN=<?php echo esc_html($token); ?></code>
                        <button type="button" class="button copy-env" data-clipboard-target=".env-content code">
                            <?php _e('Copy to Clipboard', 'nextjs-groundhogg'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e('Note: The SDK uses Groundhogg API v4 by default. Set NEXT_PUBLIC_GROUNDHOGG_API_VERSION=v3 for backwards compatibility if needed.', 'nextjs-groundhogg'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <h3><?php _e('CORS Settings', 'nextjs-groundhogg'); ?></h3>
            <form method="post" action="options.php">
                <?php
                settings_fields('nextjs_gh_settings');
                do_settings_sections('nextjs_gh_settings');
                
                $allowed_origins = get_option('nextjs_gh_allowed_origins', []);
                if (!is_array($allowed_origins)) {
                    $allowed_origins = [];
                }
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nextjs_gh_allowed_origins">
                                <?php _e('Allowed Origins', 'nextjs-groundhogg'); ?>
                            </label>
                        </th>
                        <td>
                            <div id="allowed-origins">
                                <?php if (!empty($allowed_origins)): ?>
                                    <?php foreach ($allowed_origins as $origin): ?>
                                        <div class="origin-row">
                                            <input type="url" 
                                                name="nextjs_gh_allowed_origins[]" 
                                                value="<?php echo esc_attr($origin); ?>"
                                                class="regular-text"
                                                placeholder="https://your-nextjs-app.com">
                                            <button type="button" class="button remove-origin">
                                                <?php _e('Remove', 'nextjs-groundhogg'); ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="origin-row">
                                    <input type="url" 
                                        name="nextjs_gh_allowed_origins[]" 
                                        value=""
                                        class="regular-text"
                                        placeholder="https://your-nextjs-app.com">
                                    <button type="button" class="button remove-origin">
                                        <?php _e('Remove', 'nextjs-groundhogg'); ?>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="button" id="add-origin">
                                <?php _e('Add Another Origin', 'nextjs-groundhogg'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Enter the URLs of your NextJS applications that need to access the Groundhogg API.', 'nextjs-groundhogg'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
    jQuery(document).ready(function($) {
        // Initialize clipboard.js
        new ClipboardJS('.copy-env');
        
        // Add success message when copied
        $('.copy-env').on('click', function() {
            var $button = $(this);
            var originalText = $button.text();
            $button.text('Copied!');
            setTimeout(function() {
                $button.text(originalText);
            }, 2000);
        });

        $('#add-origin').on('click', function() {
            var $lastRow = $('.origin-row:last');
            if ($lastRow.find('input').val().trim() !== '') {
                var $row = $lastRow.clone();
                $row.find('input').val('');
                $('#allowed-origins').append($row);
            }
        });

        $('#allowed-origins').on('click', '.remove-origin', function() {
            var $rows = $('.origin-row');
            if ($rows.length > 1) {
                $(this).closest('.origin-row').remove();
            } else {
                $(this).prev('input').val('');
            }
        });

        $('form').on('submit', function(e) {
            $('.origin-row input').each(function() {
                if ($(this).val().trim() === '') {
                    $(this).closest('.origin-row').remove();
                }
            });
            
            if ($('.origin-row').length === 0) {
                var $row = $('<div class="origin-row"><input type="url" name="nextjs_gh_allowed_origins[]" value="" class="regular-text" placeholder="https://your-nextjs-app.com"><button type="button" class="button remove-origin"><?php echo esc_js(__("Remove", "nextjs-groundhogg")); ?></button></div>');
                $('#allowed-origins').append($row);
            }
        });
    });
    </script>

    <style>
    .origin-row {
        margin-bottom: 10px;
    }
    .origin-row .button {
        margin-left: 10px;
    }
    #add-origin {
        margin-bottom: 10px;
    }
    .settings-section {
        background: #fff;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-top: 20px;
    }
    .env-section {
        margin: 20px 0;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 5px;
    }
    .env-content {
        background: #2c3338;
        color: #fff;
        padding: 15px;
        border-radius: 5px;
        margin: 10px 0;
        position: relative;
    }
    .env-content code {
        display: block;
        background: none;
        color: #fff;
        padding: 0;
        white-space: pre;
        font-family: monospace;
    }
    .copy-env {
        position: absolute;
        top: 10px;
        right: 10px;
    }
    </style>
    <?php
} catch (Exception $e) {
    error_log('NextJS Groundhogg Integration - General Settings Error: ' . $e->getMessage());
    ?>
    <div class="notice notice-error">
        <p><?php echo esc_html('Error loading general settings: ' . $e->getMessage()); ?></p>
    </div>
    <?php
} 