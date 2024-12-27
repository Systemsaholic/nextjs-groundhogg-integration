<?php
if (!defined('ABSPATH')) {
    exit;
}

class NextJS_Groundhogg_API {
    private static $instance = null;
    private $cache_group = 'nextjs_gh_api';
    private $rate_limit_key_prefix = 'nextjs_gh_rate_limit_';

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('nextjs-groundhogg/v1', '/verify-connection', [
            'methods' => 'GET',
            'callback' => [$this, 'verify_connection'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('nextjs-groundhogg/v1', '/quick-contact-sync', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_quick_contact_sync'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('nextjs-groundhogg/v1', '/contact-activity/(?P<email>[^/]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_contact_activity'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('nextjs-groundhogg/v1', '/contact-notes/(?P<email>[^/]+)', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_contact_notes'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('nextjs-groundhogg/v1', '/custom-fields', [
            'methods' => 'GET',
            'callback' => [$this, 'get_custom_fields'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('nextjs-groundhogg/v1', '/contacts', [
            'methods' => 'GET',
            'callback' => [$this, 'list_contacts'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('nextjs-groundhogg/v1', '/submit-form', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_form_submission'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }

    public function check_permission($request) {
        // Check rate limit
        if (!$this->check_rate_limit($request->get_header('X-GH-API-KEY'))) {
            return new WP_Error(
                'rate_limit_exceeded',
                'API rate limit exceeded',
                ['status' => 429]
            );
        }

        // Verify API key
        $api_key = $request->get_header('X-GH-API-KEY');
        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                'API key is required',
                ['status' => 401]
            );
        }

        // Get API keys from Groundhogg
        $api_keys = \Groundhogg\get_db('api_keys')->query();
        if (empty($api_keys)) {
            return new WP_Error(
                'no_api_keys',
                'No API keys configured in Groundhogg',
                ['status' => 401]
            );
        }

        // Check if the provided key matches any active key
        $key_valid = false;
        foreach ($api_keys as $key) {
            if ($key->status === 'active' && $key->public_key === $api_key) {
                $key_valid = true;
                break;
            }
        }

        if (!$key_valid) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid API key',
                ['status' => 401]
            );
        }

        return true;
    }

    private function check_rate_limit($api_key) {
        $settings = get_option('nextjs_gh_api_settings', [
            'rate_limit_enabled' => true,
            'rate_limit' => 60 // requests per minute
        ]);

        if (!$settings['rate_limit_enabled']) {
            return true;
        }

        $key = $this->rate_limit_key_prefix . $api_key;
        $count = get_transient($key);

        if ($count === false) {
            set_transient($key, 1, 60); // Start counting, expire in 60 seconds
            return true;
        }

        if ($count >= $settings['rate_limit']) {
            return false;
        }

        set_transient($key, $count + 1, 60);
        return true;
    }

    private function get_cache_key($endpoint, $params = []) {
        return md5($endpoint . serialize($params));
    }

    private function get_cached_response($key) {
        return get_transient($key);
    }

    private function cache_response($key, $data) {
        $settings = get_option('nextjs_gh_api_settings', [
            'cache_duration' => 300 // 5 minutes default
        ]);

        set_transient($key, $data, $settings['cache_duration']);
    }

    public function verify_connection() {
        return [
            'status' => 'success',
            'message' => 'Connection verified',
            'version' => '0.1.0-beta'
        ];
    }

    public function handle_quick_contact_sync($request) {
        $params = $request->get_json_params();
        
        if (empty($params['email'])) {
            return new WP_Error(
                'missing_email',
                'Email is required',
                ['status' => 400]
            );
        }

        try {
            $contact = \Groundhogg\get_contactdata($params['email']);
            
            if (!$contact) {
                // Create new contact
                $contact_data = [
                    'email' => $params['email'],
                    'first_name' => $params['first_name'] ?? '',
                    'last_name' => $params['last_name'] ?? '',
                    'phone_number' => $params['phone'] ?? ''
                ];
                
                $contact_id = \Groundhogg\get_db('contacts')->add($contact_data);
                if (!$contact_id) {
                    throw new Exception('Failed to create contact');
                }
                $contact = \Groundhogg\get_contactdata($contact_id);
            } else {
                // Update existing contact
                $updates = [];
                if (!empty($params['first_name'])) {
                    $updates['first_name'] = $params['first_name'];
                }
                if (!empty($params['last_name'])) {
                    $updates['last_name'] = $params['last_name'];
                }
                if (!empty($params['phone'])) {
                    $updates['phone_number'] = $params['phone'];
                }
                
                if (!empty($updates)) {
                    $contact->update($updates);
                }
            }

            // Apply tags if provided
            if (!empty($params['tags']) && is_array($params['tags'])) {
                foreach ($params['tags'] as $tag_id) {
                    $contact->apply_tag($tag_id);
                }
            }

            return [
                'status' => 'success',
                'contact_id' => $contact->get_id(),
                'email' => $contact->get_email(),
                'first_name' => $contact->get_first_name(),
                'last_name' => $contact->get_last_name(),
                'phone' => $contact->get_meta('phone_number'),
                'tags' => $contact->get_tags()
            ];

        } catch (Exception $e) {
            return new WP_Error(
                'sync_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_contact_activity($request) {
        $email = $request['email'];
        $cache_key = $this->get_cache_key('contact_activity', ['email' => $email]);
        
        // Check cache first
        $cached = $this->get_cached_response($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $contact = \Groundhogg\get_contactdata($email);
            if (!$contact) {
                return new WP_Error(
                    'contact_not_found',
                    'Contact not found',
                    ['status' => 404]
                );
            }

            $activity = \Groundhogg\get_db('activity')->query([
                'where' => [
                    'contact_id' => $contact->get_id()
                ],
                'orderby' => 'date_created',
                'order' => 'DESC',
                'number' => 50
            ]);

            $response = [
                'contact_id' => $contact->get_id(),
                'email' => $contact->get_email(),
                'activity' => array_map(function($item) {
                    return [
                        'id' => $item->ID,
                        'type' => $item->type,
                        'description' => $item->description,
                        'date' => $item->date_created,
                        'meta' => $item->meta
                    ];
                }, $activity)
            ];

            // Cache the response
            $this->cache_response($cache_key, $response);

            return $response;

        } catch (Exception $e) {
            return new WP_Error(
                'activity_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function handle_contact_notes($request) {
        $email = $request['email'];

        try {
            $contact = \Groundhogg\get_contactdata($email);
            if (!$contact) {
                return new WP_Error(
                    'contact_not_found',
                    'Contact not found',
                    ['status' => 404]
                );
            }

            if ($request->get_method() === 'GET') {
                $cache_key = $this->get_cache_key('contact_notes', ['email' => $email]);
                
                // Check cache first
                $cached = $this->get_cached_response($cache_key);
                if ($cached !== false) {
                    return $cached;
                }

                $notes = \Groundhogg\get_db('notes')->query([
                    'where' => [
                        'contact_id' => $contact->get_id()
                    ],
                    'orderby' => 'date_created',
                    'order' => 'DESC'
                ]);

                $response = [
                    'contact_id' => $contact->get_id(),
                    'email' => $contact->get_email(),
                    'notes' => array_map(function($note) {
                        return [
                            'id' => $note->ID,
                            'content' => $note->content,
                            'type' => $note->type,
                            'date' => $note->date_created,
                            'owner' => $note->owner
                        ];
                    }, $notes)
                ];

                // Cache the response
                $this->cache_response($cache_key, $response);

                return $response;

            } else if ($request->get_method() === 'POST') {
                $params = $request->get_json_params();
                
                if (empty($params['content'])) {
                    return new WP_Error(
                        'missing_content',
                        'Note content is required',
                        ['status' => 400]
                    );
                }

                $note_data = [
                    'contact_id' => $contact->get_id(),
                    'content' => $params['content'],
                    'type' => $params['type'] ?? 'note',
                    'owner' => get_current_user_id()
                ];

                $note_id = \Groundhogg\get_db('notes')->add($note_data);
                if (!$note_id) {
                    throw new Exception('Failed to save note');
                }

                $note = \Groundhogg\get_db('notes')->get($note_id);
                return [
                    'status' => 'success',
                    'note_id' => $note->ID,
                    'contact_id' => $contact->get_id(),
                    'content' => $note->content,
                    'type' => $note->type,
                    'date' => $note->date_created,
                    'owner' => $note->owner
                ];
            }

        } catch (Exception $e) {
            return new WP_Error(
                'notes_operation_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_custom_fields() {
        $cache_key = $this->get_cache_key('custom_fields');
        
        // Check cache first
        $cached = $this->get_cached_response($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $fields = apply_filters('groundhogg/contact/meta_fields', []);
            
            $response = array_map(function($field) {
                return [
                    'id' => $field['id'],
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'description' => $field['description'] ?? '',
                    'required' => $field['required'] ?? false,
                    'options' => $field['options'] ?? []
                ];
            }, $fields);

            // Cache the response
            $this->cache_response($cache_key, $response);

            return $response;

        } catch (Exception $e) {
            return new WP_Error(
                'custom_fields_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function list_contacts($request) {
        $params = $request->get_query_params();
        $cache_key = $this->get_cache_key('contacts', $params);
        
        // Check cache first
        $cached = $this->get_cached_response($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $args = [
                'number' => $params['per_page'] ?? 10,
                'offset' => ($params['page'] ?? 1) * ($params['per_page'] ?? 10),
                'orderby' => $params['orderby'] ?? 'date_created',
                'order' => $params['order'] ?? 'DESC'
            ];

            if (!empty($params['search'])) {
                $args['search'] = $params['search'];
            }

            if (!empty($params['tag_id'])) {
                $args['tag_id'] = $params['tag_id'];
            }

            $contacts = \Groundhogg\get_db('contacts')->query($args);
            $total = \Groundhogg\get_db('contacts')->count($args);

            $response = [
                'total' => $total,
                'page' => $params['page'] ?? 1,
                'per_page' => $params['per_page'] ?? 10,
                'contacts' => array_map(function($contact) {
                    return [
                        'id' => $contact->ID,
                        'email' => $contact->get_email(),
                        'first_name' => $contact->get_first_name(),
                        'last_name' => $contact->get_last_name(),
                        'phone' => $contact->get_meta('phone_number'),
                        'tags' => $contact->get_tags(),
                        'date_created' => $contact->date_created,
                        'last_modified' => $contact->last_modified
                    ];
                }, $contacts)
            ];

            // Cache the response
            $this->cache_response($cache_key, $response);

            return $response;

        } catch (Exception $e) {
            return new WP_Error(
                'contacts_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function handle_form_submission($request) {
        $params = $request->get_json_params();
        
        if (empty($params['email'])) {
            return new WP_Error(
                'missing_email',
                'Email is required',
                ['status' => 400]
            );
        }

        try {
            $contact = \Groundhogg\get_contactdata($params['email']);
            
            if (!$contact) {
                // Create new contact
                $contact_data = [
                    'email' => $params['email'],
                    'first_name' => $params['first_name'] ?? '',
                    'last_name' => $params['last_name'] ?? '',
                    'phone_number' => $params['phone'] ?? ''
                ];
                
                $contact_id = \Groundhogg\get_db('contacts')->add($contact_data);
                if (!$contact_id) {
                    throw new Exception('Failed to create contact');
                }
                $contact = \Groundhogg\get_contactdata($contact_id);
            }

            // Handle custom fields
            if (!empty($params['custom_fields']) && is_array($params['custom_fields'])) {
                foreach ($params['custom_fields'] as $field_id => $value) {
                    $contact->update_meta($field_id, $value);
                }
            }

            // Apply tags
            if (!empty($params['tags']) && is_array($params['tags'])) {
                foreach ($params['tags'] as $tag_id) {
                    $contact->apply_tag($tag_id);
                }
            }

            // Add form submission activity
            $activity_data = [
                'contact_id' => $contact->get_id(),
                'type' => 'form_submission',
                'description' => 'Form submitted via NextJS integration',
                'meta' => [
                    'form_data' => $params
                ]
            ];

            $activity_id = \Groundhogg\get_db('activity')->add($activity_data);
            if (!$activity_id) {
                throw new Exception('Failed to record activity');
            }

            return [
                'status' => 'success',
                'contact_id' => $contact->get_id(),
                'email' => $contact->get_email(),
                'activity_id' => $activity_id
            ];

        } catch (Exception $e) {
            return new WP_Error(
                'form_submission_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
} 