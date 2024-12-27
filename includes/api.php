<?php
if (!defined('ABSPATH')) {
    exit;
}

class NextJS_Groundhogg_API {
    private static $instance = null;
    private $namespace = 'nextjs-groundhogg/v1';
    private $cache_group = 'nextjs_groundhogg';
    private $rate_limit_key_prefix = 'nextjs_gh_rate_limit_';

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        // Add webhook listeners for contact updates
        add_action('groundhogg/contact/after_update', [$this, 'handle_contact_update'], 10, 2);
        add_action('groundhogg/contact/after_create', [$this, 'handle_contact_create'], 10, 2);
        
        // Initialize cache if not exists
        wp_cache_add_non_persistent_groups([$this->cache_group]);
    }

    public function register_routes() {
        // NextJS specific endpoints that augment Groundhogg's functionality
        register_rest_route($this->namespace, '/verify-connection', [
            'methods' => 'GET',
            'callback' => [$this, 'verify_connection'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Example of an enhanced endpoint that combines multiple Groundhogg operations
        register_rest_route($this->namespace, '/quick-contact-sync', [
            'methods' => 'POST',
            'callback' => [$this, 'quick_contact_sync'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Add endpoint for contact activity
        register_rest_route($this->namespace, '/contact-activity/(?P<email>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_contact_activity'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // New routes
        register_rest_route($this->namespace, '/contact-notes/(?P<email>[^/]+)', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_contact_notes'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/custom-fields', [
            'methods' => 'GET',
            'callback' => [$this, 'get_custom_fields'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/contact-custom-fields/(?P<email>[^/]+)', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_contact_custom_fields'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/form-submission', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_form_submission'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/webhook-config', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_webhook_config'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Add phone-based contact endpoints
        register_rest_route($this->namespace, '/contact-by-phone/(?P<phone>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_contact_by_phone'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/quick-contact-sync-phone', [
            'methods' => 'POST',
            'callback' => [$this, 'quick_contact_sync_phone'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/contacts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_contacts'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/contact/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_contact'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/contact-by-phone/(?P<phone>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_contact_by_phone'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/quick-contact-sync-phone', [
            'methods' => 'POST',
            'callback' => [$this, 'quick_contact_sync_phone'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // New endpoints for receiving data from NextJS
        register_rest_route($this->namespace, '/submit-form', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_form_submission'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/submit-form-with-files', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_form_submission_with_files'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/update-contact', [
            'methods' => 'PUT',
            'callback' => [$this, 'handle_contact_update_from_nextjs'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Email endpoints
        register_rest_route($this->namespace, '/emails', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_emails'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/email/(?P<id>\d+)', [
            'methods' => ['GET', 'PUT', 'DELETE'],
            'callback' => [$this, 'handle_single_email'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/email/(?P<id>\d+)/send', [
            'methods' => 'POST',
            'callback' => [$this, 'send_email'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/email-templates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_email_templates'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission($request) {
        // Get API settings
        $api_settings = get_option('nextjs_gh_api_settings', [
            'rate_limit_enabled' => true,
            'rate_limit' => 60, // requests per minute
            'cache_duration' => 300 // 5 minutes
        ]);

        // Check rate limit if enabled
        if ($api_settings['rate_limit_enabled']) {
            $api_key = $request->get_header('X-GH-API-KEY');
            $rate_limit_key = $this->rate_limit_key_prefix . md5($api_key);
            $current_count = (int) wp_cache_get($rate_limit_key, $this->cache_group);

            if ($current_count >= $api_settings['rate_limit']) {
                return new WP_Error(
                    'rate_limit_exceeded',
                    'Rate limit exceeded. Please try again later.',
                    ['status' => 429]
                );
            }

            wp_cache_add($rate_limit_key, $current_count + 1, $this->cache_group, 60);
        }

        // Leverage Groundhogg's permission system
        if (!class_exists('\Groundhogg\Plugin')) {
            return false;
        }

        // Use Groundhogg's API key validation
        $api_key = $request->get_header('X-GH-API-KEY');
        if (!$api_key) {
            return false;
        }

        return apply_filters('groundhogg/api/verify_key', false, $api_key);
    }

    public function verify_connection() {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        return new WP_REST_Response([
            'status' => 'connected',
            'version' => GROUNDHOGG_VERSION,
            'nextjs_integration_version' => '1.0.0'
        ], 200);
    }

    public function quick_contact_sync($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        $params = $request->get_json_params();
        
        if (empty($params['email']) && empty($params['phone'])) {
            return new WP_Error('missing_identifier', 'Either email or phone is required', ['status' => 400]);
        }

        try {
            $contact = null;
            
            // Try to find by email first
            if (!empty($params['email'])) {
                $contact = \Groundhogg\get_contactdata($params['email']);
            }
            
            // If no contact found and phone provided, try by phone
            if (!$contact && !empty($params['phone'])) {
                $contact = $this->get_contact_by_phone_number($params['phone']);
            }

            if (!$contact && !empty($params['email'])) {
                // Create new contact with email
                $contact_data = [
                    'email' => $params['email'],
                    'first_name' => $params['first_name'] ?? '',
                    'last_name' => $params['last_name'] ?? '',
                    'optin_status' => 2
                ];
                
                $contact_id = \Groundhogg\get_db('contacts')->add($contact_data);
                if (!$contact_id || is_wp_error($contact_id)) {
                    return new WP_Error('contact_creation_failed', 'Failed to create contact', ['status' => 500]);
                }
                
                $contact = \Groundhogg\get_contactdata($contact_id);
                
                // Add phone if provided
                if (!empty($params['phone'])) {
                    $contact->update_meta('phone_number', $this->normalize_phone($params['phone']));
                }
            }

            // Handle NextJS specific operations here
            // For example, syncing with NextJS cache or performing NextJS specific validations
            
            return new WP_REST_Response([
                'success' => true,
                'contact_id' => $contact->get_id(),
                'synced_with_nextjs' => true,
                'email' => $contact->get_email(),
                'first_name' => $contact->get_first_name(),
                'last_name' => $contact->get_last_name()
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error('sync_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function get_contact_activity($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        $email = sanitize_email($request['email']);
        $cache_key = $this->get_cache_key('activity', $email);
        
        // Try to get from cache first
        $cached_response = $this->get_cached_response($cache_key);
        if ($cached_response !== false) {
            return new WP_REST_Response($cached_response, 200);
        }

        $contact = \Groundhogg\get_contactdata($email);
        if (!$contact) {
            return new WP_Error('contact_not_found', 'Contact not found', ['status' => 404]);
        }

        try {
            $activity = \Groundhogg\get_db('activity')->query([
                'where' => [
                    'contact_id' => $contact->get_id()
                ],
                'limit' => 10,
                'orderby' => 'date_created',
                'order' => 'DESC'
            ]);

            $formatted_activity = array_map(function($item) {
                return [
                    'id' => $item->ID,
                    'type' => $item->type,
                    'date' => $item->date_created,
                    'description' => $item->description
                ];
            }, $activity);

            $response_data = [
                'contact_id' => $contact->get_id(),
                'email' => $contact->get_email(),
                'activity' => $formatted_activity
            ];

            // Cache the response
            $this->set_cached_response($cache_key, $response_data);

            return new WP_REST_Response($response_data, 200);

        } catch (\Exception $e) {
            return new WP_Error('activity_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function handle_contact_notes($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        $email = sanitize_email($request['email']);
        $contact = \Groundhogg\get_contactdata($email);

        if (!$contact) {
            return new WP_Error('contact_not_found', 'Contact not found', ['status' => 404]);
        }

        if ($request->get_method() === 'POST') {
            $params = $request->get_json_params();
            if (empty($params['note'])) {
                return new WP_Error('missing_note', 'Note content is required', ['status' => 400]);
            }

            $note_data = [
                'contact_id' => $contact->get_id(),
                'content' => sanitize_textarea_field($params['note']),
                'type' => 'note'
            ];

            $note_id = \Groundhogg\get_db('notes')->add($note_data);
            
            if (!$note_id || is_wp_error($note_id)) {
                return new WP_Error('note_creation_failed', 'Failed to create note', ['status' => 500]);
            }

            return new WP_REST_Response(['success' => true, 'note_id' => $note_id], 201);
        }

        // GET request - retrieve notes
        $notes = \Groundhogg\get_db('notes')->query([
            'where' => [
                'contact_id' => $contact->get_id(),
                'type' => 'note'
            ],
            'orderby' => 'date_created',
            'order' => 'DESC'
        ]);

        return new WP_REST_Response(['notes' => $notes], 200);
    }

    public function get_custom_fields() {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        $custom_fields = apply_filters('groundhogg/contact/meta_fields', []);
        return new WP_REST_Response(['custom_fields' => $custom_fields], 200);
    }

    public function handle_contact_custom_fields($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        $email = sanitize_email($request['email']);
        $contact = \Groundhogg\get_contactdata($email);

        if (!$contact) {
            return new WP_Error('contact_not_found', 'Contact not found', ['status' => 404]);
        }

        if ($request->get_method() === 'POST') {
            $params = $request->get_json_params();
            if (empty($params['fields'])) {
                return new WP_Error('missing_fields', 'Custom fields are required', ['status' => 400]);
            }

            foreach ($params['fields'] as $key => $value) {
                $contact->update_meta(sanitize_key($key), sanitize_text_field($value));
            }

            return new WP_REST_Response([
                'success' => true,
                'contact_id' => $contact->get_id()
            ], 200);
        }

        // GET request - retrieve custom fields
        $meta_fields = apply_filters('groundhogg/contact/meta_fields', []);
        $contact_meta = [];

        foreach ($meta_fields as $field => $config) {
            $contact_meta[$field] = $contact->get_meta($field);
        }

        return new WP_REST_Response(['custom_fields' => $contact_meta], 200);
    }

    public function handle_form_submission($request) {
        $params = $request->get_params();
        
        if (!isset($params['form_id']) || !isset($params['data'])) {
            return new WP_Error('missing_params', 'Missing required parameters', ['status' => 400]);
        }

        $form_id = sanitize_text_field($params['form_id']);
        $data = $params['data'];

        // Validate required fields
        if (!isset($data['email']) && !isset($data['phone'])) {
            return new WP_Error('missing_identifier', 'Either email or phone is required', ['status' => 400]);
        }

        try {
            // Create or update contact
            $contact_data = [
                'email' => isset($data['email']) ? sanitize_email($data['email']) : '',
                'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '',
                'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '',
            ];

            // Handle phone number if provided
            if (isset($data['phone'])) {
                $contact_data['meta']['phone_number'] = $this->normalize_phone_number($data['phone']);
            }

            // Find or create contact
            $contact = $this->find_or_create_contact($contact_data);

            if (is_wp_error($contact)) {
                return $contact;
            }

            // Add tags if provided
            if (isset($data['tags']) && is_array($data['tags'])) {
                foreach ($data['tags'] as $tag_name) {
                    $tag = $this->get_or_create_tag(sanitize_text_field($tag_name));
                    if ($tag && !is_wp_error($tag)) {
                        $contact->add_tag($tag->ID);
                    }
                }
            }

            // Add meta data
            if (isset($data['meta']) && is_array($data['meta'])) {
                foreach ($data['meta'] as $key => $value) {
                    $contact->update_meta(sanitize_key($key), sanitize_text_field($value));
                }
            }

            // Record form submission
            do_action('groundhogg/form/after_submission', $contact, $form_id, $data);

            // Add note about form submission
            $note = sprintf(
                'Form submission received from %s (Form ID: %s)',
                isset($data['meta']['page_url']) ? $data['meta']['page_url'] : 'NextJS application',
                $form_id
            );
            
            \Groundhogg\get_db('notes')->add([
                'contact_id' => $contact->get_id(),
                'content' => $note,
                'type' => 'note'
            ]);

            return new WP_REST_Response([
                'success' => true,
                'contact_id' => $contact->get_id(),
                'message' => 'Form submission processed successfully'
            ], 200);

        } catch (Exception $e) {
            return new WP_Error('submission_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function handle_form_submission_with_files($request) {
        $params = $request->get_params();
        $files = $request->get_file_params();

        // First handle the regular form submission
        $submission_response = $this->handle_form_submission($request);
        
        if (is_wp_error($submission_response)) {
            return $submission_response;
        }

        // Handle file uploads
        if (!empty($files)) {
            $contact_id = $submission_response->get_data()['contact_id'];
            $contact = \Groundhogg\get_contactdata($contact_id);

            foreach ($files as $key => $file) {
                if (!empty($file['tmp_name'])) {
                    $upload = wp_handle_upload($file, ['test_form' => false]);
                    
                    if (!isset($upload['error'])) {
                        // Store file URL in contact meta
                        $contact->update_meta("form_upload_{$key}", $upload['url']);
                    }
                }
            }
        }

        return $submission_response;
    }

    public function handle_contact_update_from_nextjs($request) {
        $params = $request->get_params();
        
        if (!isset($params['contact_id']) || !isset($params['data'])) {
            return new WP_Error('missing_params', 'Missing required parameters', ['status' => 400]);
        }

        $contact_id = intval($params['contact_id']);
        $data = $params['data'];
        $contact = \Groundhogg\get_contactdata($contact_id);

        if (!$contact) {
            return new WP_Error('contact_not_found', 'Contact not found', ['status' => 404]);
        }

        try {
            // Update basic contact information
            if (isset($data['email'])) {
                $contact->update_meta('email', sanitize_email($data['email']));
            }
            if (isset($data['first_name'])) {
                $contact->update_meta('first_name', sanitize_text_field($data['first_name']));
            }
            if (isset($data['last_name'])) {
                $contact->update_meta('last_name', sanitize_text_field($data['last_name']));
            }
            if (isset($data['phone'])) {
                $contact->update_meta('phone_number', $this->normalize_phone_number($data['phone']));
            }

            // Handle tags
            if (isset($data['tags_to_add']) && is_array($data['tags_to_add'])) {
                foreach ($data['tags_to_add'] as $tag_name) {
                    $tag = $this->get_or_create_tag(sanitize_text_field($tag_name));
                    if ($tag && !is_wp_error($tag)) {
                        $contact->add_tag($tag->ID);
                    }
                }
            }

            if (isset($data['tags_to_remove']) && is_array($data['tags_to_remove'])) {
                foreach ($data['tags_to_remove'] as $tag_name) {
                    $tag = \Groundhogg\get_db('tags')->get_by('tag_name', $tag_name);
                    if ($tag) {
                        $contact->remove_tag($tag->ID);
                    }
                }
            }

            // Update meta data
            if (isset($data['meta']) && is_array($data['meta'])) {
                foreach ($data['meta'] as $key => $value) {
                    $contact->update_meta(sanitize_key($key), sanitize_text_field($value));
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'contact_id' => $contact->get_id(),
                'message' => 'Contact updated successfully'
            ], 200);

        } catch (Exception $e) {
            return new WP_Error('update_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    private function find_or_create_contact($data) {
        $contact = null;

        // Try to find by email first
        if (!empty($data['email'])) {
            $contact = \Groundhogg\get_db('contacts')->get_by('email', $data['email']);
        }

        // If no contact found by email and phone is provided, try by phone
        if (!$contact && !empty($data['meta']['phone_number'])) {
            $contact = $this->get_contact_by_phone_number($data['meta']['phone_number']);
        }

        // If still no contact found, create new one
        if (!$contact) {
            $contact_id = \Groundhogg\get_db('contacts')->add([
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'status' => 'confirmed'
            ]);

            if (!$contact_id) {
                return new WP_Error('creation_failed', 'Failed to create contact');
            }

            $contact = \Groundhogg\get_contactdata($contact_id);

            // Add phone number if provided
            if (!empty($data['meta']['phone_number'])) {
                $contact->update_meta('phone_number', $data['meta']['phone_number']);
            }
        } else {
            // Update existing contact
            $contact->update_meta('first_name', $data['first_name']);
            $contact->update_meta('last_name', $data['last_name']);
            if (!empty($data['meta']['phone_number'])) {
                $contact->update_meta('phone_number', $data['meta']['phone_number']);
            }
        }

        return $contact;
    }

    private function get_or_create_tag($tag_name) {
        $tag = \Groundhogg\get_db('tags')->get_by('tag_name', $tag_name);
        
        if (!$tag) {
            $tag_id = \Groundhogg\get_db('tags')->add([
                'tag_name' => $tag_name
            ]);
            if ($tag_id) {
                $tag = \Groundhogg\get_db('tags')->get($tag_id);
            }
        }

        return $tag;
    }

    private function normalize_phone_number($phone) {
        $settings = get_option('nextjs_gh_phone_settings', [
            'require_country_code' => true,
            'default_country_code' => '1',
            'format' => 'international'
        ]);

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add default country code if needed
        if ($settings['require_country_code'] && strlen($phone) === 10) {
            $phone = $settings['default_country_code'] . $phone;
        }

        // Format phone number according to settings
        switch ($settings['format']) {
            case 'international':
                return '+' . substr($phone, 0, 1) . '-' . 
                       substr($phone, 1, 3) . '-' . 
                       substr($phone, 4, 3) . '-' . 
                       substr($phone, 7);
            case 'national':
                return substr($phone, -10, 3) . '-' . 
                       substr($phone, -7, 3) . '-' . 
                       substr($phone, -4);
            default:
                return $phone;
        }
    }

    public function handle_contact_update($contact_id, $contact) {
        $this->send_webhook('contact.updated', [
            'contact_id' => $contact_id,
            'email' => $contact->get_email()
        ]);
    }

    public function handle_contact_create($contact_id, $contact) {
        $this->send_webhook('contact.created', [
            'contact_id' => $contact_id,
            'email' => $contact->get_email()
        ]);
    }

    private function send_webhook($event, $data) {
        $webhook_url = get_option('nextjs_gh_webhook_url');
        if (!$webhook_url) {
            return;
        }

        wp_remote_post($webhook_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Groundhogg-Event' => $event
            ],
            'body' => json_encode($data)
        ]);
    }

    private function normalize_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Ensure it starts with country code if not present (assuming US/Canada)
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }
        
        return $phone;
    }

    private function get_contact_by_phone_number($phone) {
        $normalized_phone = $this->normalize_phone($phone);
        
        // Search in the meta table for this phone number
        global $wpdb;
        $table = $wpdb->prefix . 'gh_contact_meta';
        
        $contact_id = $wpdb->get_var($wpdb->prepare(
            "SELECT contact_id FROM $table WHERE meta_key = 'phone_number' AND meta_value = %s LIMIT 1",
            $normalized_phone
        ));

        if ($contact_id) {
            return \Groundhogg\get_contactdata($contact_id);
        }

        return null;
    }

    public function get_contact_by_phone($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        $phone = $request['phone'];
        $contact = $this->get_contact_by_phone_number($phone);

        if (!$contact) {
            return new WP_REST_Response(['message' => 'Contact not found'], 404);
        }

        return new WP_REST_Response([
            'id' => $contact->get_id(),
            'email' => $contact->get_email(),
            'phone' => $contact->get_meta('phone_number'),
            'first_name' => $contact->get_first_name(),
            'last_name' => $contact->get_last_name(),
            'tags' => $contact->get_tags(),
            'meta' => $contact->get_meta()
        ], 200);
    }

    public function quick_contact_sync_phone($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        $params = $request->get_json_params();
        
        if (empty($params['phone'])) {
            return new WP_Error('missing_phone', 'Phone number is required', ['status' => 400]);
        }

        try {
            $normalized_phone = $this->normalize_phone($params['phone']);
            
            // First try to find by phone
            $contact = $this->get_contact_by_phone_number($normalized_phone);
            
            // If no contact found and email provided, try by email
            if (!$contact && !empty($params['email'])) {
                $contact = \Groundhogg\get_contactdata($params['email']);
            }

            if (!$contact) {
                // Create new contact with a temporary email if none provided
                $email = !empty($params['email']) ? $params['email'] : $normalized_phone . '@placeholder.temp';
                
                $contact_data = [
                    'email' => $email,
                    'first_name' => $params['first_name'] ?? '',
                    'last_name' => $params['last_name'] ?? '',
                    'optin_status' => 2 // Confirmed opt-in
                ];
                
                $contact_id = \Groundhogg\get_db('contacts')->add($contact_data);
                if (!$contact_id || is_wp_error($contact_id)) {
                    return new WP_Error('contact_creation_failed', 'Failed to create contact', ['status' => 500]);
                }
                
                $contact = \Groundhogg\get_contactdata($contact_id);
                
                // Add phone number to meta
                $contact->update_meta('phone_number', $normalized_phone);
                
                // If this was created with a placeholder email, mark it in meta
                if (strpos($email, '@placeholder.temp') !== false) {
                    $contact->update_meta('is_phone_only', true);
                }
            } else {
                // Update existing contact
                if (!empty($params['first_name'])) {
                    $contact->update_meta('first_name', sanitize_text_field($params['first_name']));
                }
                if (!empty($params['last_name'])) {
                    $contact->update_meta('last_name', sanitize_text_field($params['last_name']));
                }
                // Update phone if it's different
                if ($contact->get_meta('phone_number') !== $normalized_phone) {
                    $contact->update_meta('phone_number', $normalized_phone);
                }
                // Update email if provided and contact was phone-only
                if (!empty($params['email']) && $contact->get_meta('is_phone_only')) {
                    $contact->update_meta('email', sanitize_email($params['email']));
                    $contact->delete_meta('is_phone_only');
                }
            }

            // Handle tags if provided
            if (!empty($params['tags'])) {
                $tags = array_map('intval', $params['tags']);
                $contact->add_tag($tags);
            }

            return new WP_REST_Response([
                'success' => true,
                'contact_id' => $contact->get_id(),
                'email' => $contact->get_email(),
                'phone' => $contact->get_meta('phone_number'),
                'first_name' => $contact->get_first_name(),
                'last_name' => $contact->get_last_name(),
                'is_phone_only' => (bool) $contact->get_meta('is_phone_only'),
                'tags' => $contact->get_tags()
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error('sync_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function handle_emails($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        if ($request->get_method() === 'POST') {
            $params = $request->get_json_params();
            
            if (empty($params['subject']) || empty($params['content'])) {
                return new WP_Error('missing_params', 'Subject and content are required', ['status' => 400]);
            }

            try {
                $email_data = [
                    'subject' => sanitize_text_field($params['subject']),
                    'content' => wp_kses_post($params['content']),
                    'from_name' => isset($params['from_name']) ? sanitize_text_field($params['from_name']) : '',
                    'from_email' => isset($params['from_email']) ? sanitize_email($params['from_email']) : '',
                    'reply_to' => isset($params['reply_to']) ? sanitize_email($params['reply_to']) : '',
                    'status' => isset($params['status']) ? sanitize_text_field($params['status']) : 'draft',
                    'template' => isset($params['template']) ? sanitize_text_field($params['template']) : 'default'
                ];

                $email_id = \Groundhogg\get_db('emails')->add($email_data);

                if (!$email_id || is_wp_error($email_id)) {
                    return new WP_Error('email_creation_failed', 'Failed to create email', ['status' => 500]);
                }

                $email = \Groundhogg\get_db('emails')->get($email_id);

                return new WP_REST_Response([
                    'success' => true,
                    'email_id' => $email_id,
                    'email' => $this->format_email_data($email)
                ], 201);

            } catch (Exception $e) {
                return new WP_Error('email_error', $e->getMessage(), ['status' => 500]);
            }
        }

        // GET request - retrieve emails
        try {
            $args = [
                'orderby' => 'date_created',
                'order' => 'DESC',
                'number' => 50
            ];

            // Add filters if provided
            if (isset($_GET['status'])) {
                $args['where']['status'] = sanitize_text_field($_GET['status']);
            }

            $emails = \Groundhogg\get_db('emails')->query($args);
            
            return new WP_REST_Response([
                'emails' => array_map([$this, 'format_email_data'], $emails)
            ], 200);

        } catch (Exception $e) {
            return new WP_Error('email_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function handle_single_email($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        $email_id = intval($request['id']);
        $email = \Groundhogg\get_db('emails')->get($email_id);

        if (!$email) {
            return new WP_Error('email_not_found', 'Email not found', ['status' => 404]);
        }

        switch ($request->get_method()) {
            case 'GET':
                return new WP_REST_Response([
                    'email' => $this->format_email_data($email)
                ], 200);

            case 'PUT':
                $params = $request->get_json_params();
                $update_data = [];

                if (isset($params['subject'])) {
                    $update_data['subject'] = sanitize_text_field($params['subject']);
                }
                if (isset($params['content'])) {
                    $update_data['content'] = wp_kses_post($params['content']);
                }
                if (isset($params['from_name'])) {
                    $update_data['from_name'] = sanitize_text_field($params['from_name']);
                }
                if (isset($params['from_email'])) {
                    $update_data['from_email'] = sanitize_email($params['from_email']);
                }
                if (isset($params['reply_to'])) {
                    $update_data['reply_to'] = sanitize_email($params['reply_to']);
                }
                if (isset($params['status'])) {
                    $update_data['status'] = sanitize_text_field($params['status']);
                }

                $updated = \Groundhogg\get_db('emails')->update($email_id, $update_data);

                if (!$updated) {
                    return new WP_Error('update_failed', 'Failed to update email', ['status' => 500]);
                }

                $email = \Groundhogg\get_db('emails')->get($email_id);
                return new WP_REST_Response([
                    'success' => true,
                    'email' => $this->format_email_data($email)
                ], 200);

            case 'DELETE':
                $deleted = \Groundhogg\get_db('emails')->delete($email_id);
                
                if (!$deleted) {
                    return new WP_Error('delete_failed', 'Failed to delete email', ['status' => 500]);
                }

                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Email deleted successfully'
                ], 200);
        }
    }

    public function send_email($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        $email_id = intval($request['id']);
        $params = $request->get_json_params();

        if (empty($params['contact_ids']) || !is_array($params['contact_ids'])) {
            return new WP_Error('missing_contacts', 'Contact IDs are required', ['status' => 400]);
        }

        $email = \Groundhogg\get_db('emails')->get($email_id);
        if (!$email) {
            return new WP_Error('email_not_found', 'Email not found', ['status' => 404]);
        }

        try {
            $results = [];
            foreach ($params['contact_ids'] as $contact_id) {
                $contact = \Groundhogg\get_contactdata($contact_id);
                if ($contact) {
                    // Create email event
                    $event_id = \Groundhogg\get_db('email_events')->add([
                        'email_id' => $email_id,
                        'contact_id' => $contact_id,
                        'status' => 'scheduled',
                        'scheduled_time' => current_time('mysql')
                    ]);

                    if ($event_id) {
                        // Trigger the email send
                        do_action('groundhogg/email/send', $email, $contact);
                        
                        $results[] = [
                            'contact_id' => $contact_id,
                            'status' => 'scheduled',
                            'event_id' => $event_id
                        ];
                    }
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'results' => $results
            ], 200);

        } catch (Exception $e) {
            return new WP_Error('send_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function get_email_templates($request) {
        if (!class_exists('\Groundhogg\Plugin')) {
            return new WP_Error('groundhogg_missing', 'Groundhogg plugin is not active', ['status' => 500]);
        }

        try {
            $templates = apply_filters('groundhogg/email/templates', []);
            return new WP_REST_Response(['templates' => $templates], 200);
        } catch (Exception $e) {
            return new WP_Error('template_error', $e->getMessage(), ['status' => 500]);
        }
    }

    private function format_email_data($email) {
        return [
            'id' => $email->ID,
            'subject' => $email->get_subject(),
            'content' => $email->get_content(),
            'from_name' => $email->get_from_name(),
            'from_email' => $email->get_from_email(),
            'reply_to' => $email->get_reply_to(),
            'status' => $email->get_status(),
            'template' => $email->get_template(),
            'date_created' => $email->date_created,
            'date_modified' => $email->date_modified,
            'stats' => [
                'sent' => $email->get_times_sent(),
                'opened' => $email->get_times_opened(),
                'clicked' => $email->get_times_clicked()
            ]
        ];
    }

    private function get_cache_key($prefix, $identifier) {
        return sprintf('%s_%s', $prefix, md5($identifier));
    }

    private function get_cached_response($cache_key) {
        return wp_cache_get($cache_key, $this->cache_group);
    }

    private function set_cached_response($cache_key, $data, $expiration = 300) {
        wp_cache_set($cache_key, $data, $this->cache_group, $expiration);
    }
} 