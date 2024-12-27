<?php
if (!defined('ABSPATH')) exit;

class NextJS_Groundhogg_Webhook_Logs {
    private static $instance = null;
    private $table_name;
    private $db_version = '1.0';

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'nextjs_gh_webhook_logs';
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            webhook_url text NOT NULL,
            payload longtext NOT NULL,
            response_code int(11),
            response_body longtext,
            error_message text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option('nextjs_gh_webhook_logs_db_version', $this->db_version);
    }

    public function log_webhook($event_type, $webhook_url, $payload, $response) {
        global $wpdb;

        $data = [
            'event_type' => $event_type,
            'webhook_url' => $webhook_url,
            'payload' => is_string($payload) ? $payload : json_encode($payload),
            'timestamp' => current_time('mysql')
        ];

        if (is_wp_error($response)) {
            $data['error_message'] = $response->get_error_message();
        } else {
            $data['response_code'] = wp_remote_retrieve_response_code($response);
            $data['response_body'] = wp_remote_retrieve_body($response);
        }

        $wpdb->insert($this->table_name, $data);
    }

    public function get_logs($per_page = 20, $page = 1, $filters = []) {
        global $wpdb;

        $where = [];
        $where_values = [];

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = %s';
            $where_values[] = $filters['event_type'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'success') {
                $where[] = 'response_code >= 200 AND response_code < 300 AND error_message IS NULL';
            } else {
                $where[] = '(response_code < 200 OR response_code >= 300 OR error_message IS NOT NULL)';
            }
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(timestamp) >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(timestamp) <= %s';
            $where_values[] = $filters['date_to'];
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} $where_sql";
        if (!empty($where_values)) {
            $count_sql = $wpdb->prepare($count_sql, $where_values);
        }
        $total = $wpdb->get_var($count_sql);

        // Get paginated results
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT * FROM {$this->table_name} $where_sql ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$per_page, $offset]);
        $logs = $wpdb->get_results($wpdb->prepare($sql, $query_values));

        return [
            'logs' => $logs,
            'total' => (int) $total,
            'pages' => ceil($total / $per_page)
        ];
    }

    public function get_stats() {
        global $wpdb;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $success = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE response_code >= 200 AND response_code < 300 AND error_message IS NULL"
        ));
        $error = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE response_code < 200 OR response_code >= 300 OR error_message IS NOT NULL"
        ));
        $last_24h = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE timestamp >= %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));

        return [
            'total' => (int) $total,
            'success' => (int) $success,
            'error' => (int) $error,
            'last_24h' => (int) $last_24h
        ];
    }

    public function get_event_types() {
        global $wpdb;
        return $wpdb->get_col("SELECT DISTINCT event_type FROM {$this->table_name} ORDER BY event_type ASC");
    }

    public function clear_old_logs($days = 30) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE timestamp < %s",
            date('Y-m-d H:i:s', strtotime("-$days days"))
        ));
    }
} 