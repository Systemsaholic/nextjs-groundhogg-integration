<?php
if (!defined('ABSPATH')) exit;

$webhook_url = get_option('nextjs_gh_webhook_url');
$enabled_events = get_option('nextjs_gh_enabled_events', []);
?>

<div class="settings-section">
    <h3><?php _e('Webhook Settings', 'nextjs-groundhogg'); ?></h3>
    <form method="post" action="options.php">
        <?php
        settings_fields('nextjs_gh_webhook_settings');
        do_settings_sections('nextjs_gh_webhook_settings');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="nextjs_gh_webhook_url">
                        <?php _e('Webhook URL', 'nextjs-groundhogg'); ?>
                    </label>
                </th>
                <td>
                    <input type="url" id="nextjs_gh_webhook_url" name="nextjs_gh_webhook_url"
                        value="<?php echo esc_attr($webhook_url); ?>"
                        class="regular-text"
                        placeholder="https://your-nextjs-app.com/api/webhook">
                    <p class="description">
                        <?php _e('Enter the URL where webhook notifications should be sent.', 'nextjs-groundhogg'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Enabled Events', 'nextjs-groundhogg'); ?></th>
                <td>
                    <?php
                    $event_groups = [
                        'Contact Events' => [
                            'contact.created' => __('Contact Created', 'nextjs-groundhogg'),
                            'contact.updated' => __('Contact Updated', 'nextjs-groundhogg'),
                            'contact.deleted' => __('Contact Deleted', 'nextjs-groundhogg')
                        ],
                        'Tag Events' => [
                            'tag.applied' => __('Tag Applied', 'nextjs-groundhogg'),
                            'tag.removed' => __('Tag Removed', 'nextjs-groundhogg')
                        ],
                        'Form Events' => [
                            'form.submitted' => __('Form Submitted', 'nextjs-groundhogg')
                        ],
                        'Note Events' => [
                            'note.added' => __('Note Added', 'nextjs-groundhogg')
                        ],
                        'Activity Events' => [
                            'activity.added' => __('Activity Added', 'nextjs-groundhogg')
                        ],
                        'Email Events' => [
                            'email.sent' => __('Email Sent', 'nextjs-groundhogg'),
                            'email.opened' => __('Email Opened', 'nextjs-groundhogg'),
                            'email.clicked' => __('Email Clicked', 'nextjs-groundhogg')
                        ]
                    ];
                    ?>
                    <div class="event-groups">
                        <?php foreach ($event_groups as $group_name => $events): ?>
                            <div class="event-group">
                                <h4><?php echo esc_html($group_name); ?></h4>
                                <?php foreach ($events as $event => $label): ?>
                                    <label class="event-checkbox">
                                        <input type="checkbox"
                                            name="nextjs_gh_enabled_events[<?php echo esc_attr($event); ?>]"
                                            value="1"
                                            <?php checked(isset($enabled_events[$event]) && $enabled_events[$event]); ?>>
                                        <?php echo esc_html($label); ?>
                                        <button type="button"
                                            class="button test-webhook"
                                            data-event="<?php echo esc_attr($event); ?>">
                                            <?php _e('Test', 'nextjs-groundhogg'); ?>
                                        </button>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="select-all-events">
                        <button type="button" class="button" id="select-all-events">
                            <?php _e('Select All', 'nextjs-groundhogg'); ?>
                        </button>
                        <button type="button" class="button" id="deselect-all-events">
                            <?php _e('Deselect All', 'nextjs-groundhogg'); ?>
                        </button>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>

    <div id="webhook-test-result" style="display: none;">
        <h3><?php _e('Test Result', 'nextjs-groundhogg'); ?></h3>
        <div class="notice inline">
            <p class="message"></p>
            <pre class="response" style="display: none;"></pre>
        </div>
    </div>

    <div class="webhook-documentation">
        <h3><?php _e('Webhook Documentation', 'nextjs-groundhogg'); ?></h3>
        <p><?php _e('Webhooks allow your NextJS application to receive real-time updates when actions occur in Groundhogg.', 'nextjs-groundhogg'); ?></p>

        <h4><?php _e('Setting Up Your Webhook Endpoint', 'nextjs-groundhogg'); ?></h4>
        <p><?php _e('Your webhook endpoint should:', 'nextjs-groundhogg'); ?></p>
        <ul>
            <li><?php _e('Accept POST requests', 'nextjs-groundhogg'); ?></li>
            <li><?php _e('Return a 200 status code on success', 'nextjs-groundhogg'); ?></li>
            <li><?php _e('Process requests asynchronously if possible', 'nextjs-groundhogg'); ?></li>
            <li><?php _e('Validate the X-Groundhogg-Event header', 'nextjs-groundhogg'); ?></li>
        </ul>

        <h4><?php _e('Example NextJS Endpoint', 'nextjs-groundhogg'); ?></h4>
        <pre><code>// pages/api/webhook.js
export default async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).json({ message: 'Method not allowed' });
  }

  const event = req.headers['x-groundhogg-event'];
  const timestamp = req.headers['x-groundhogg-timestamp'];
  const data = req.body;

  try {
    // Process the webhook based on the event type
    switch (event) {
      case 'contact.created':
      case 'contact.updated':
        await updateContact(data.contact_data);
        break;
      case 'contact.deleted':
        await deleteContact(data.contact_id);
        break;
      case 'tag.applied':
      case 'tag.removed':
        await updateContactTags(data.contact_id, data.tag_id, event);
        break;
      // Handle other events...
    }

    res.status(200).json({ received: true });
  } catch (error) {
    console.error('Webhook processing error:', error);
    res.status(500).json({ error: error.message });
  }
}</code></pre>

        <h4><?php _e('Security Headers', 'nextjs-groundhogg'); ?></h4>
        <p><?php _e('Each webhook request includes the following headers:', 'nextjs-groundhogg'); ?></p>
        <ul>
            <li><code>X-Groundhogg-Event</code>: <?php _e('The type of event being sent', 'nextjs-groundhogg'); ?></li>
            <li><code>X-Groundhogg-Site</code>: <?php _e('The URL of the WordPress site', 'nextjs-groundhogg'); ?></li>
            <li><code>X-Groundhogg-Timestamp</code>: <?php _e('Unix timestamp when the event occurred', 'nextjs-groundhogg'); ?></li>
            <li><code>Content-Type</code>: <code>application/json</code></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle select/deselect all events
    $('#select-all-events').on('click', function() {
        $('.event-checkbox input[type="checkbox"]').prop('checked', true);
    });

    $('#deselect-all-events').on('click', function() {
        $('.event-checkbox input[type="checkbox"]').prop('checked', false);
    });

    // Handle webhook testing
    $('.test-webhook').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $result = $('#webhook-test-result');
        var $message = $result.find('.message');
        var $response = $result.find('.response');
        var event = $button.data('event');
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('<?php _e('Testing...', 'nextjs-groundhogg'); ?>');
        
        // Hide previous results
        $result.hide();
        $message.text('');
        $response.hide().text('');
        
        // Send test webhook
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'nextjs_gh_test_webhook',
                _ajax_nonce: '<?php echo wp_create_nonce('nextjs_gh_webhook_test'); ?>',
                event_type: event
            },
            success: function(response) {
                var notice_class = response.success ? 'notice-success' : 'notice-error';
                $result.find('.notice').removeClass('notice-success notice-error').addClass(notice_class);
                $message.text(response.data.message);
                
                if (response.data.response) {
                    $response.text(response.data.response).show();
                }
                
                $result.fadeIn();
            },
            error: function(xhr, status, error) {
                $result.find('.notice').removeClass('notice-success').addClass('notice-error');
                $message.text('<?php _e('An error occurred while testing the webhook.', 'nextjs-groundhogg'); ?>');
                $result.fadeIn();
            },
            complete: function() {
                // Re-enable button and restore text
                $button.prop('disabled', false).text('<?php _e('Test', 'nextjs-groundhogg'); ?>');
            }
        });
    });
});
</script>

<style>
.settings-section {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.event-groups {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 15px;
}

.event-group {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}

.event-group h4 {
    margin-top: 0;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #ddd;
}

.event-checkbox {
    display: block;
    margin: 10px 0;
}

.event-checkbox .button {
    margin-left: 10px;
}

.select-all-events {
    margin-top: 15px;
}

.select-all-events .button {
    margin-right: 10px;
}

#webhook-test-result {
    margin: 20px 0;
}

#webhook-test-result .notice {
    margin: 0;
    padding: 10px;
}

#webhook-test-result pre {
    background: #f8f9fa;
    padding: 10px;
    margin: 10px 0 0;
    overflow: auto;
    max-height: 200px;
    border: 1px solid #ddd;
}

.webhook-documentation {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.webhook-documentation h3 {
    margin-top: 0;
}

.webhook-documentation h4 {
    margin: 1.5em 0 0.5em;
}

.webhook-documentation ul {
    list-style: disc;
    margin-left: 20px;
}

.webhook-documentation pre {
    background: #f8f9fa;
    padding: 15px;
    overflow: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.webhook-documentation code {
    background: #f1f1f1;
    padding: 2px 5px;
    border-radius: 3px;
}
</style> 