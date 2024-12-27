<?php
/**
 * Plugin Name: NextJS Groundhogg Integration
 * Plugin URI: https://github.com/Systemsaholic/nextjs-groundhogg-integration
 * Description: Integrate Groundhogg with NextJS applications
 * Version: 0.1.3-beta
 * Author: Al Guertin
 * Author URI: https://systemsaholic.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin update checker
if (!class_exists('Puc_v4_Factory')) {
    require_once plugin_dir_path(__FILE__) . 'includes/plugin-update-checker/plugin-update-checker.php';
    
    // Set up the update checker
    $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
        'https://raw.githubusercontent.com/Systemsaholic/nextjs-groundhogg-integration/master/plugin.json',
        __FILE__,
        'nextjs-groundhogg-integration'
    );

    // Optional: Set authentication for private repos
    $github_token = get_option('nextjs_gh_github_token');
    if (!empty($github_token)) {
        $myUpdateChecker->setAuthentication($github_token);
    }
}

class NextJS_Groundhogg_Integration {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Only initialize if Groundhogg is active
        if (!$this->check_groundhogg()) {
            return;
        }

        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'admin_init']);
        // Add AJAX handlers
        add_action('wp_ajax_nextjs_gh_test_webhook', [$this, 'handle_webhook_test']);
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Initialize webhook logging
        require_once plugin_dir_path(__FILE__) . 'includes/class-webhook-logs.php';
        $webhook_logs = NextJS_Groundhogg_Webhook_Logs::instance();
        register_activation_hook(__FILE__, [$webhook_logs, 'create_table']);

        // Add cleanup schedule
        if (!wp_next_scheduled('nextjs_gh_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'nextjs_gh_cleanup_logs');
        }
        add_action('nextjs_gh_cleanup_logs', [$webhook_logs, 'clear_old_logs']);

        // Contact Events
        add_action('groundhogg/contact/after_update', [$this, 'handle_contact_update'], 10, 2);
        add_action('groundhogg/contact/after_create', [$this, 'handle_contact_create'], 10, 2);
        add_action('groundhogg/contact/after_delete', [$this, 'handle_contact_delete'], 10, 1);

        // Tag Events
        add_action('groundhogg/contact/tag_applied', [$this, 'handle_tag_applied'], 10, 2);
        add_action('groundhogg/contact/tag_removed', [$this, 'handle_tag_removed'], 10, 2);

        // Form Events
        add_action('groundhogg/form/after_submission', [$this, 'handle_form_submission'], 10, 3);

        // Note Events
        add_action('groundhogg/contact/note_added', [$this, 'handle_note_added'], 10, 3);

        // Activity Events
        add_action('groundhogg/contact/activity_added', [$this, 'handle_activity_added'], 10, 3);

        // Email Events
        add_action('groundhogg/email/sent', [$this, 'handle_email_sent'], 10, 2);
        add_action('groundhogg/email/opened', [$this, 'handle_email_opened'], 10, 2);
        add_action('groundhogg/email/clicked', [$this, 'handle_email_clicked'], 10, 3);
    }

    private function check_groundhogg() {
        if (!class_exists('\Groundhogg\Plugin')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('NextJS Groundhogg Integration requires Groundhogg CRM plugin to be installed and activated.', 'nextjs-groundhogg'); ?></p>
                </div>
                <?php
            });
            return false;
        }
        return true;
    }

    public function init() {
        try {
            // Initialize API
            if (file_exists(plugin_dir_path(__FILE__) . 'includes/api.php')) {
                require_once plugin_dir_path(__FILE__) . 'includes/api.php';
                NextJS_Groundhogg_API::instance();
            } else {
                throw new Exception('API file not found');
            }

            // Add CORS support
            add_action('rest_api_init', [$this, 'add_cors_support'], 15);
        } catch (Exception $e) {
            error_log('NextJS Groundhogg Integration Error: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html('NextJS Groundhogg Integration Error: ' . $e->getMessage()); ?></p>
                </div>
                <?php
            });
        }
    }

    public function admin_init() {
        $this->register_settings();
    }

    public function add_cors_support() {
        add_filter('rest_pre_serve_request', function($served, $result, $request) {
            if (isset($_SERVER['HTTP_ORIGIN'])) {
                $allowed_origins = get_option('nextjs_gh_allowed_origins', ['http://localhost:10058']);
                $origin = $_SERVER['HTTP_ORIGIN'];

                if (in_array($origin, $allowed_origins)) {
                    header('Access-Control-Allow-Origin: ' . $origin);
                    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                    header('Access-Control-Allow-Credentials: true');
                    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-GH-API-KEY');
                }
            }
            return $served;
        }, 10, 3);
    }

    public function add_admin_menu() {
        add_menu_page(
            'NextJS Groundhogg Integration', // Page title
            'NextJS CRM', // Menu title
            'manage_options', // Capability
            'nextjs-groundhogg', // Menu slug
            [$this, 'render_admin_page'], // Callback function
            'dashicons-rest-api', // Icon
            30 // Position
        );
    }

    public function register_settings() {
        // General settings
        register_setting('nextjs_gh_settings', 'nextjs_gh_allowed_origins', [
            'type' => 'array',
            'sanitize_callback' => function($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_filter(array_map('esc_url_raw', $value), function($url) {
                    return !empty($url);
                });
            },
            'default' => ['http://localhost:3000']
        ]);

        // Phone settings
        register_setting('nextjs_gh_phone_settings', 'nextjs_gh_phone_settings', [
            'type' => 'array',
            'default' => [
                'require_country_code' => true,
                'default_country_code' => '1',
                'format' => 'international'
            ]
        ]);

        // API settings
        register_setting('nextjs_gh_api_settings', 'nextjs_gh_api_settings', [
            'type' => 'array',
            'default' => [
                'cache_duration' => 300,
                'rate_limit_enabled' => true,
                'rate_limit' => 60
            ]
        ]);

        // Webhook settings
        register_setting('nextjs_gh_webhook_settings', 'nextjs_gh_webhook_url');
        register_setting('nextjs_gh_webhook_settings', 'nextjs_gh_enabled_events', [
            'type' => 'array',
            'default' => [
                'contact.created' => true,
                'contact.updated' => true,
                'contact.deleted' => true,
                'tag.applied' => true,
                'tag.removed' => true,
                'form.submitted' => true,
                'note.added' => true,
                'activity.added' => true,
                'email.sent' => true,
                'email.opened' => true,
                'email.clicked' => true
            ]
        ]);
    }

    public function render_admin_page() {
        try {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }

            $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
            ?>
            <div class="wrap">
                <h1><?php _e('NextJS Groundhogg Integration Settings', 'nextjs-groundhogg'); ?></h1>
                
                <h2 class="nav-tab-wrapper">
                    <a href="?page=nextjs-groundhogg&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('General Settings', 'nextjs-groundhogg'); ?>
                    </a>
                    <a href="?page=nextjs-groundhogg&tab=phone" class="nav-tab <?php echo $active_tab == 'phone' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Phone Settings', 'nextjs-groundhogg'); ?>
                    </a>
                    <a href="?page=nextjs-groundhogg&tab=webhooks" class="nav-tab <?php echo $active_tab == 'webhooks' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Webhooks', 'nextjs-groundhogg'); ?>
                    </a>
                    <a href="?page=nextjs-groundhogg&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Webhook Logs', 'nextjs-groundhogg'); ?>
                    </a>
                    <a href="?page=nextjs-groundhogg&tab=api" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('API Settings', 'nextjs-groundhogg'); ?>
                    </a>
                    <a href="?page=nextjs-groundhogg&tab=generator" class="nav-tab <?php echo $active_tab == 'generator' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('NextJS Generator', 'nextjs-groundhogg'); ?>
                    </a>
                    <a href="?page=nextjs-groundhogg&tab=docs" class="nav-tab <?php echo $active_tab == 'docs' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Documentation', 'nextjs-groundhogg'); ?>
                    </a>
                </h2>

                <?php
                $template_path = dirname(__FILE__) . '/templates/';
                switch ($active_tab) {
                    case 'logs':
                        if (file_exists($template_path . 'webhook-logs.php')) {
                            include $template_path . 'webhook-logs.php';
                        }
                        break;
                    case 'webhooks':
                        if (file_exists($template_path . 'webhook-settings.php')) {
                            include $template_path . 'webhook-settings.php';
                        }
                        break;
                    case 'phone':
                        if (file_exists($template_path . 'phone-settings.php')) {
                            include $template_path . 'phone-settings.php';
                        }
                        break;
                    case 'api':
                        if (file_exists($template_path . 'api-settings.php')) {
                            include $template_path . 'api-settings.php';
                        }
                        break;
                    case 'generator':
                        if (file_exists($template_path . 'nextjs-generator.php')) {
                            include $template_path . 'nextjs-generator.php';
                        }
                        break;
                    case 'docs':
                        if (file_exists($template_path . 'documentation.php')) {
                            include $template_path . 'documentation.php';
                        }
                        break;
                    default:
                        if (file_exists($template_path . 'general-settings.php')) {
                            include $template_path . 'general-settings.php';
                        }
                        break;
                }
                ?>
            </div>
            <?php
        } catch (Exception $e) {
            error_log('NextJS Groundhogg Integration Error: ' . $e->getMessage());
            ?>
            <div class="notice notice-error">
                <p><?php echo esc_html('Error loading settings page: ' . $e->getMessage()); ?></p>
            </div>
            <?php
        }
    }

    public function handle_webhook_test() {
        check_ajax_referer('nextjs_gh_webhook_test');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $webhook_url = get_option('nextjs_gh_webhook_url');
        if (!$webhook_url) {
            wp_send_json_error('No webhook URL configured');
        }

        // Get and validate event type
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'test.event';
        if (empty($event_type)) {
            wp_send_json_error('Event type is required');
        }

        // Prepare test data based on event type
        $test_data = $this->get_test_data_for_event($event_type);

        // Send the webhook
        $response = wp_remote_post($webhook_url, [
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Groundhogg-Event' => $event_type,
                'X-Groundhogg-Site' => get_site_url(),
                'X-Groundhogg-Timestamp' => time(),
                'X-Groundhogg-Test' => 'true'
            ],
            'body' => json_encode($test_data)
        ]);

        // Log the test webhook
        $webhook_logs = NextJS_Groundhogg_Webhook_Logs::instance();
        $webhook_logs->log_webhook($event_type, $webhook_url, $test_data, $response);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => $response->get_error_message(),
                'code' => 'error'
            ]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 200 && $response_code < 300) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Webhook sent successfully (HTTP %d)', 'nextjs-groundhogg'),
                    $response_code
                ),
                'response' => $response_body,
                'code' => $response_code
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('Webhook failed (HTTP %d)', 'nextjs-groundhogg'),
                    $response_code
                ),
                'response' => $response_body,
                'code' => $response_code
            ]);
        }
    }

    private function get_test_data_for_event($event_type) {
        $base_data = [
            'plugin_version' => '1.0.0',
            'site_url' => get_site_url(),
            'event' => $event_type,
            'timestamp' => current_time('mysql'),
            'test' => true
        ];

        switch ($event_type) {
            case 'contact.created':
            case 'contact.updated':
                return array_merge($base_data, [
                    'contact_id' => 12345,
                    'contact_data' => [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john.doe@example.com',
                        'phone' => '+1-555-123-4567',
                        'tags' => [1, 2, 3],
                        'status' => 'active'
                    ]
                ]);

            case 'contact.deleted':
                return array_merge($base_data, [
                    'contact_id' => 12345
                ]);

            case 'tag.applied':
            case 'tag.removed':
                return array_merge($base_data, [
                    'contact_id' => 12345,
                    'tag_id' => 789,
                    'tag_name' => 'Test Tag'
                ]);

            case 'form.submitted':
                return array_merge($base_data, [
                    'contact_id' => 12345,
                    'form_id' => 456,
                    'form_title' => 'Test Form',
                    'submission_data' => [
                        'name' => 'John Doe',
                        'email' => 'john.doe@example.com',
                        'message' => 'This is a test form submission.'
                    ]
                ]);

            case 'note.added':
                return array_merge($base_data, [
                    'contact_id' => 12345,
                    'note_id' => 789,
                    'note_content' => 'This is a test note.',
                    'note_type' => 'general'
                ]);

            case 'activity.added':
                return array_merge($base_data, [
                    'contact_id' => 12345,
                    'activity_id' => 789,
                    'activity_type' => 'email_opened',
                    'activity_data' => [
                        'email_id' => 456,
                        'email_subject' => 'Test Email'
                    ]
                ]);

            case 'email.sent':
            case 'email.opened':
            case 'email.clicked':
                return array_merge($base_data, [
                    'contact_id' => 12345,
                    'email_id' => 456,
                    'email_data' => [
                        'subject' => 'Test Email',
                        'campaign_id' => 789,
                        'link_url' => 'https://example.com/test-link'
                    ]
                ]);

            default:
                return array_merge($base_data, [
                    'message' => 'This is a test webhook event.'
                ]);
        }
    }

    public function handle_contact_update($contact_id, $contact) {
        $changed_fields = $this->get_changed_contact_fields($contact);
        $this->send_webhook('contact.updated', [
            'contact_id' => $contact_id,
            'email' => $contact->get_email(),
            'timestamp' => current_time('mysql'),
            'changed_fields' => $changed_fields,
            'contact_data' => [
                'first_name' => $contact->get_first_name(),
                'last_name' => $contact->get_last_name(),
                'phone' => $contact->get_meta('phone_number'),
                'tags' => $contact->get_tags(),
                'status' => $contact->get_status()
            ]
        ]);
    }

    public function handle_contact_create($contact_id, $contact) {
        $this->send_webhook('contact.created', [
            'contact_id' => $contact_id,
            'email' => $contact->get_email(),
            'timestamp' => current_time('mysql'),
            'contact_data' => [
                'first_name' => $contact->get_first_name(),
                'last_name' => $contact->get_last_name(),
                'phone' => $contact->get_meta('phone_number'),
                'tags' => $contact->get_tags(),
                'status' => $contact->get_status()
            ]
        ]);
    }

    private function get_changed_contact_fields($contact) {
        $changed = [];
        $meta = $contact->get_meta();
        
        foreach ($meta as $key => $value) {
            if ($contact->meta_changed($key)) {
                $changed[] = $key;
            }
        }

        if ($contact->tags_changed()) {
            $changed[] = 'tags';
        }

        return $changed;
    }

    private function send_webhook($event, $data) {
        $webhook_url = get_option('nextjs_gh_webhook_url');
        if (!$webhook_url) {
            return;
        }

        // Check if this event type is enabled
        $enabled_events = get_option('nextjs_gh_enabled_events', []);
        if (!isset($enabled_events[$event]) || !$enabled_events[$event]) {
            error_log(sprintf(
                'NextJS Groundhogg webhook event %s skipped (disabled in settings)',
                $event
            ));
            return;
        }

        // Add common data
        $data['plugin_version'] = '1.0.0';
        $data['site_url'] = get_site_url();
        $data['event'] = $event;

        $response = wp_remote_post($webhook_url, [
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Groundhogg-Event' => $event,
                'X-Groundhogg-Site' => get_site_url(),
                'X-Groundhogg-Timestamp' => time()
            ],
            'body' => json_encode($data)
        ]);

        // Log the webhook
        $webhook_logs = NextJS_Groundhogg_Webhook_Logs::instance();
        $webhook_logs->log_webhook($event, $webhook_url, $data, $response);

        // Log webhook failures for debugging
        if (is_wp_error($response)) {
            error_log(sprintf(
                'NextJS Groundhogg webhook error for event %s: %s',
                $event,
                $response->get_error_message()
            ));
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code < 200 || $response_code >= 300) {
                error_log(sprintf(
                    'NextJS Groundhogg webhook received non-200 response for event %s: %s',
                    $event,
                    $response_code
                ));
            }
        }
    }

    private function get_phone_settings() {
        return get_option('nextjs_gh_phone_settings', [
            'require_country_code' => true,
            'default_country_code' => '1',
            'phone_format' => 'international'
        ]);
    }

    private function get_api_settings() {
        return get_option('nextjs_gh_api_settings', [
            'cache_duration' => 300,
            'enable_rate_limiting' => true,
            'requests_per_minute' => 60
        ]);
    }

    public function handle_contact_delete($contact_id) {
        $this->send_webhook('contact.deleted', [
            'contact_id' => $contact_id,
            'timestamp' => current_time('mysql')
        ]);
    }

    public function handle_tag_applied($contact_id, $tag_id) {
        $contact = \Groundhogg\get_contactdata($contact_id);
        $tag = \Groundhogg\get_db('tags')->get($tag_id);
        
        $this->send_webhook('contact.tag_added', [
            'contact_id' => $contact_id,
            'email' => $contact ? $contact->get_email() : null,
            'tag_id' => $tag_id,
            'tag_name' => $tag ? $tag->tag_name : null,
            'timestamp' => current_time('mysql'),
            'contact_data' => $contact ? [
                'first_name' => $contact->get_first_name(),
                'last_name' => $contact->get_last_name(),
                'tags' => $contact->get_tags()
            ] : null
        ]);
    }

    public function handle_tag_removed($contact_id, $tag_id) {
        $contact = \Groundhogg\get_contactdata($contact_id);
        $tag = \Groundhogg\get_db('tags')->get($tag_id);
        
        $this->send_webhook('contact.tag_removed', [
            'contact_id' => $contact_id,
            'email' => $contact ? $contact->get_email() : null,
            'tag_id' => $tag_id,
            'tag_name' => $tag ? $tag->tag_name : null,
            'timestamp' => current_time('mysql'),
            'contact_data' => $contact ? [
                'first_name' => $contact->get_first_name(),
                'last_name' => $contact->get_last_name(),
                'tags' => $contact->get_tags()
            ] : null
        ]);
    }

    public function handle_form_submission($contact, $form_id, $form_data) {
        $this->send_webhook('form.submitted', [
            'contact_id' => $contact->get_id(),
            'email' => $contact->get_email(),
            'form_id' => $form_id,
            'form_data' => $form_data,
            'timestamp' => current_time('mysql'),
            'contact_data' => [
                'first_name' => $contact->get_first_name(),
                'last_name' => $contact->get_last_name(),
                'tags' => $contact->get_tags()
            ]
        ]);
    }

    public function handle_note_added($note_id, $contact_id, $note_data) {
        $contact = \Groundhogg\get_contactdata($contact_id);
        
        $this->send_webhook('contact.note_added', [
            'contact_id' => $contact_id,
            'email' => $contact ? $contact->get_email() : null,
            'note_id' => $note_id,
            'note_data' => $note_data,
            'timestamp' => current_time('mysql'),
            'contact_data' => $contact ? [
                'first_name' => $contact->get_first_name(),
                'last_name' => $contact->get_last_name()
            ] : null
        ]);
    }

    public function handle_activity_added($activity_id, $contact_id, $activity_data) {
        $contact = \Groundhogg\get_contactdata($contact_id);
        
        $this->send_webhook('contact.activity_added', [
            'contact_id' => $contact_id,
            'email' => $contact ? $contact->get_email() : null,
            'activity_id' => $activity_id,
            'activity_data' => $activity_data,
            'timestamp' => current_time('mysql')
        ]);
    }

    public function handle_email_sent($email_id, $contact_id) {
        $contact = \Groundhogg\get_contactdata($contact_id);
        $email = \Groundhogg\get_db('emails')->get($email_id);
        
        $this->send_webhook('email.sent', [
            'contact_id' => $contact_id,
            'email' => $contact ? $contact->get_email() : null,
            'email_id' => $email_id,
            'email_subject' => $email ? $email->get_subject() : null,
            'timestamp' => current_time('mysql')
        ]);
    }

    public function handle_email_opened($email_id, $contact_id) {
        $contact = \Groundhogg\get_contactdata($contact_id);
        $email = \Groundhogg\get_db('emails')->get($email_id);
        
        $this->send_webhook('email.opened', [
            'contact_id' => $contact_id,
            'email' => $contact ? $contact->get_email() : null,
            'email_id' => $email_id,
            'email_subject' => $email ? $email->get_subject() : null,
            'timestamp' => current_time('mysql')
        ]);
    }

    public function handle_email_clicked($email_id, $contact_id, $link) {
        $contact = \Groundhogg\get_contactdata($contact_id);
        $email = \Groundhogg\get_db('emails')->get($email_id);
        
        $this->send_webhook('email.clicked', [
            'contact_id' => $contact_id,
            'email' => $contact ? $contact->get_email() : null,
            'email_id' => $email_id,
            'email_subject' => $email ? $email->get_subject() : null,
            'link' => $link,
            'timestamp' => current_time('mysql')
        ]);
    }
}

// Initialize the plugin
function nextjs_groundhogg_init() {
    return NextJS_Groundhogg_Integration::instance();
}

add_action('plugins_loaded', 'nextjs_groundhogg_init'); 