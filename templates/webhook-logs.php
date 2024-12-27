<?php
if (!defined('ABSPATH')) exit;

$webhook_logs = NextJS_Groundhogg_Webhook_Logs::instance();
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// Get filters
$filters = [
    'event_type' => isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '',
    'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
    'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
    'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
];

// Get logs with pagination
$result = $webhook_logs->get_logs($per_page, $current_page, $filters);
$logs = $result['logs'];
$total_pages = $result['pages'];
$total_items = $result['total'];

// Get stats
$stats = $webhook_logs->get_stats();

// Get unique event types for filter
$event_types = $webhook_logs->get_event_types();
?>

<div class="wrap webhook-logs">
    <div class="webhook-stats">
        <div class="stat-box">
            <h3><?php _e('Total Webhooks', 'nextjs-groundhogg'); ?></h3>
            <span class="stat-number"><?php echo number_format($stats['total']); ?></span>
        </div>
        <div class="stat-box success">
            <h3><?php _e('Successful', 'nextjs-groundhogg'); ?></h3>
            <span class="stat-number"><?php echo number_format($stats['success']); ?></span>
        </div>
        <div class="stat-box error">
            <h3><?php _e('Failed', 'nextjs-groundhogg'); ?></h3>
            <span class="stat-number"><?php echo number_format($stats['error']); ?></span>
        </div>
        <div class="stat-box">
            <h3><?php _e('Last 24 Hours', 'nextjs-groundhogg'); ?></h3>
            <span class="stat-number"><?php echo number_format($stats['last_24h']); ?></span>
        </div>
    </div>

    <div class="webhook-filters">
        <form method="get">
            <input type="hidden" name="page" value="nextjs-groundhogg">
            <input type="hidden" name="tab" value="logs">
            
            <select name="event_type">
                <option value=""><?php _e('All Events', 'nextjs-groundhogg'); ?></option>
                <?php foreach ($event_types as $type): ?>
                    <option value="<?php echo esc_attr($type); ?>" <?php selected($filters['event_type'], $type); ?>>
                        <?php echo esc_html($type); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value=""><?php _e('All Status', 'nextjs-groundhogg'); ?></option>
                <option value="success" <?php selected($filters['status'], 'success'); ?>>
                    <?php _e('Success', 'nextjs-groundhogg'); ?>
                </option>
                <option value="error" <?php selected($filters['status'], 'error'); ?>>
                    <?php _e('Error', 'nextjs-groundhogg'); ?>
                </option>
            </select>

            <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>" placeholder="<?php _e('From Date', 'nextjs-groundhogg'); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>" placeholder="<?php _e('To Date', 'nextjs-groundhogg'); ?>">

            <button type="submit" class="button"><?php _e('Filter', 'nextjs-groundhogg'); ?></button>
            <a href="?page=nextjs-groundhogg&tab=logs" class="button"><?php _e('Reset', 'nextjs-groundhogg'); ?></a>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'nextjs-groundhogg'); ?></th>
                <th><?php _e('Event Type', 'nextjs-groundhogg'); ?></th>
                <th><?php _e('Status', 'nextjs-groundhogg'); ?></th>
                <th><?php _e('Timestamp', 'nextjs-groundhogg'); ?></th>
                <th><?php _e('Actions', 'nextjs-groundhogg'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5"><?php _e('No webhook logs found.', 'nextjs-groundhogg'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html($log->event_type); ?></td>
                        <td>
                            <?php if ($log->error_message): ?>
                                <span class="status-error" title="<?php echo esc_attr($log->error_message); ?>">
                                    <?php _e('Error', 'nextjs-groundhogg'); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-<?php echo ($log->response_code >= 200 && $log->response_code < 300) ? 'success' : 'error'; ?>">
                                    <?php echo esc_html($log->response_code); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->timestamp); ?></td>
                        <td>
                            <button type="button" class="button view-details" data-log-id="<?php echo esc_attr($log->id); ?>">
                                <?php _e('View Details', 'nextjs-groundhogg'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr class="log-details" id="log-details-<?php echo esc_attr($log->id); ?>" style="display: none;">
                        <td colspan="5">
                            <div class="log-details-content">
                                <h4><?php _e('Webhook URL', 'nextjs-groundhogg'); ?></h4>
                                <pre><?php echo esc_html($log->webhook_url); ?></pre>

                                <h4><?php _e('Payload', 'nextjs-groundhogg'); ?></h4>
                                <pre><?php echo esc_html(json_encode(json_decode($log->payload), JSON_PRETTY_PRINT)); ?></pre>

                                <?php if ($log->response_body): ?>
                                    <h4><?php _e('Response', 'nextjs-groundhogg'); ?></h4>
                                    <pre><?php echo esc_html(json_encode(json_decode($log->response_body), JSON_PRETTY_PRINT)); ?></pre>
                                <?php endif; ?>

                                <?php if ($log->error_message): ?>
                                    <h4><?php _e('Error', 'nextjs-groundhogg'); ?></h4>
                                    <pre class="error"><?php echo esc_html($log->error_message); ?></pre>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(_n('%s item', '%s items', $total_items, 'nextjs-groundhogg'), number_format_i18n($total_items)); ?>
                </span>
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.webhook-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-box {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-box h3 {
    margin: 0 0 10px;
    color: #23282d;
}

.stat-box .stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
}

.stat-box.success .stat-number {
    color: #46b450;
}

.stat-box.error .stat-number {
    color: #dc3232;
}

.webhook-filters {
    background: #fff;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.webhook-filters select,
.webhook-filters input {
    margin-right: 10px;
}

.status-success {
    color: #46b450;
}

.status-error {
    color: #dc3232;
}

.log-details-content {
    background: #f8f9fa;
    padding: 15px;
    margin: 10px 0;
}

.log-details-content h4 {
    margin: 10px 0 5px;
}

.log-details-content pre {
    background: #fff;
    padding: 10px;
    margin: 5px 0;
    overflow: auto;
    max-height: 200px;
}

.log-details-content pre.error {
    background: #fef7f7;
    border-left: 4px solid #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.view-details').on('click', function() {
        var logId = $(this).data('log-id');
        $('#log-details-' + logId).toggle();
    });
});
</script> 