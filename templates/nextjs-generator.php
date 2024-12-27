<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$enabled_events = get_option('nextjs_gh_enabled_events', []);
?>

<div class="settings-section">
    <h3><?php _e('NextJS API Endpoint Generator', 'nextjs-groundhogg'); ?></h3>
    <p class="description">
        <?php _e('Generate NextJS API endpoint code for handling webhook events. The generated code uses the latest NextJS 14 App Router and best practices.', 'nextjs-groundhogg'); ?>
    </p>

    <form id="nextjs-generator-form">
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Select Events', 'nextjs-groundhogg'); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <?php _e('Select Events', 'nextjs-groundhogg'); ?>
                        </legend>
                        <?php
                        $event_descriptions = [
                            'contact.created' => 'Contact Created',
                            'contact.updated' => 'Contact Updated',
                            'contact.deleted' => 'Contact Deleted',
                            'tag.applied' => 'Tag Applied',
                            'tag.removed' => 'Tag Removed',
                            'form.submitted' => 'Form Submitted',
                            'note.added' => 'Note Added',
                            'activity.added' => 'Activity Added',
                            'email.sent' => 'Email Sent',
                            'email.opened' => 'Email Opened',
                            'email.clicked' => 'Email Clicked'
                        ];

                        foreach ($event_descriptions as $event => $description): ?>
                            <label>
                                <input type="checkbox" 
                                    name="selected_events[]" 
                                    value="<?php echo esc_attr($event); ?>"
                                    <?php checked(isset($enabled_events[$event]) && $enabled_events[$event]); ?>>
                                <?php echo esc_html($description); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Database Type', 'nextjs-groundhogg'); ?></th>
                <td>
                    <select name="database_type" id="database-type">
                        <option value="prisma">Prisma (Recommended)</option>
                        <option value="mongoose">Mongoose (MongoDB)</option>
                        <option value="drizzle">Drizzle ORM</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Additional Features', 'nextjs-groundhogg'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="features[]" value="validation">
                            <?php _e('Add Zod validation', 'nextjs-groundhogg'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="features[]" value="logging">
                            <?php _e('Add logging (using pino)', 'nextjs-groundhogg'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="features[]" value="error_handling">
                            <?php _e('Add error handling middleware', 'nextjs-groundhogg'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" id="generate-code" class="button button-primary">
                <?php _e('Generate Code', 'nextjs-groundhogg'); ?>
            </button>
        </p>
    </form>

    <div id="code-output" style="display: none;">
        <h3><?php _e('Generated Code', 'nextjs-groundhogg'); ?></h3>
        <p class="description">
            <?php _e('Copy and paste this code into your NextJS project.', 'nextjs-groundhogg'); ?>
        </p>
        <div class="code-container">
            <button type="button" id="copy-code" class="button">
                <?php _e('Copy Code', 'nextjs-groundhogg'); ?>
            </button>
            <pre><code id="generated-code"></code></pre>
        </div>
    </div>
</div>

<style>
.settings-section {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-top: 20px;
}
.code-container {
    position: relative;
    margin-top: 20px;
}
#generated-code {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    white-space: pre-wrap;
    font-family: monospace;
}
#copy-code {
    position: absolute;
    top: 10px;
    right: 10px;
}
fieldset label {
    margin-bottom: 8px;
    display: inline-block;
    margin-right: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    function generateNextJSCode(formData) {
        const selectedEvents = formData.getAll('selected_events[]');
        const databaseType = formData.get('database_type');
        const features = formData.getAll('features[]');
        
        let code = `// app/api/webhooks/groundhogg/route.ts
import { NextRequest, NextResponse } from 'next/server';
${features.includes('validation') ? "import { z } from 'zod';" : ''}
${features.includes('logging') ? "import pino from 'pino';" : ''}
${databaseType === 'prisma' ? "import { prisma } from '@/lib/prisma';" : ''}
${databaseType === 'mongoose' ? "import { connectDB } from '@/lib/mongodb';" : ''}
${databaseType === 'drizzle' ? "import { db } from '@/lib/db';" : ''}\n`;

        if (features.includes('logging')) {
            code += `\nconst logger = pino({
    name: 'groundhogg-webhook',
    level: process.env.NODE_ENV === 'development' ? 'debug' : 'info',
});\n`;
        }

        if (features.includes('validation')) {
            code += `\n// Webhook payload validation schemas
const baseSchema = z.object({
    plugin_version: z.string(),
    site_url: z.string().url(),
    event: z.string(),
    timestamp: z.string(),
    contact_id: z.number(),
});\n`;

            // Add specific schemas for each event type
            if (selectedEvents.includes('contact.created') || selectedEvents.includes('contact.updated')) {
                code += `
const contactDataSchema = baseSchema.extend({
    contact_data: z.object({
        first_name: z.string(),
        last_name: z.string(),
        email: z.string().email(),
        phone: z.string().optional(),
        tags: z.array(z.number()),
        status: z.string()
    })
});\n`;
            }
        }

        // Main handler function
        code += `\nexport async function POST(req: NextRequest) {
    try {
        const body = await req.json();
        const event = req.headers.get('x-groundhogg-event');
        ${features.includes('logging') ? '\nlogger.info({ event, body }, "Received webhook");' : ''}

        // Verify webhook signature if needed
        // const signature = req.headers.get('x-groundhogg-signature');

        ${features.includes('validation') ? `
        // Validate the webhook payload
        try {
            baseSchema.parse(body);
        } catch (error) {
            return NextResponse.json({ error: 'Invalid webhook payload' }, { status: 400 });
        }` : ''}

        switch (event) {`;

        // Generate case statements for each selected event
        selectedEvents.forEach(event => {
            code += `
            case '${event}':
                ${features.includes('logging') ? `logger.debug({ event }, 'Processing ${event} event');` : ''}
                await handle${event.split('.').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join('')}(body);
                break;`;
        });

        code += `
            default:
                ${features.includes('logging') ? "logger.warn({ event }, 'Unhandled webhook event');" : ''}
                return NextResponse.json({ message: 'Unhandled webhook event' }, { status: 200 });
        }

        return NextResponse.json({ success: true });
    } catch (error) {
        ${features.includes('logging') ? "logger.error(error, 'Error processing webhook');" : ''}
        return NextResponse.json(
            { error: 'Internal server error' },
            { status: 500 }
        );
    }
}\n`;

        // Generate handler functions for each event
        selectedEvents.forEach(event => {
            const functionName = `handle${event.split('.').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join('')}`;
            code += `\nasync function ${functionName}(data: any) {
    ${features.includes('validation') ? `// Validate event-specific data
    ${event.includes('contact') ? 'contactDataSchema.parse(data);' : 'baseSchema.parse(data);'}` : ''}

    ${databaseType === 'prisma' ? `// Update database using Prisma
    await prisma.contact.upsert({
        where: { id: data.contact_id },
        create: {
            id: data.contact_id,
            email: data.contact_data?.email,
            firstName: data.contact_data?.first_name,
            lastName: data.contact_data?.last_name,
            phone: data.contact_data?.phone,
            status: data.contact_data?.status,
        },
        update: {
            email: data.contact_data?.email,
            firstName: data.contact_data?.first_name,
            lastName: data.contact_data?.last_name,
            phone: data.contact_data?.phone,
            status: data.contact_data?.status,
        },
    });` : ''}
    ${databaseType === 'mongoose' ? `// Update database using Mongoose
    await Contact.findOneAndUpdate(
        { contactId: data.contact_id },
        {
            email: data.contact_data?.email,
            firstName: data.contact_data?.first_name,
            lastName: data.contact_data?.last_name,
            phone: data.contact_data?.phone,
            status: data.contact_data?.status,
        },
        { upsert: true }
    );` : ''}
    ${databaseType === 'drizzle' ? `// Update database using Drizzle
    await db.insert(contacts).values({
        contactId: data.contact_id,
        email: data.contact_data?.email,
        firstName: data.contact_data?.first_name,
        lastName: data.contact_data?.last_name,
        phone: data.contact_data?.phone,
        status: data.contact_data?.status,
    }).onConflictDoUpdate({
        target: contacts.contactId,
        set: {
            email: data.contact_data?.email,
            firstName: data.contact_data?.first_name,
            lastName: data.contact_data?.last_name,
            phone: data.contact_data?.phone,
            status: data.contact_data?.status,
        }
    });` : ''}
}\n`;
        });

        return code;
    }

    $('#generate-code').on('click', function() {
        const formData = new FormData($('#nextjs-generator-form')[0]);
        const code = generateNextJSCode(formData);
        $('#generated-code').text(code);
        $('#code-output').show();
    });

    $('#copy-code').on('click', function() {
        const code = $('#generated-code').text();
        navigator.clipboard.writeText(code).then(() => {
            const $button = $(this);
            $button.text('Copied!');
            setTimeout(() => {
                $button.text('Copy Code');
            }, 2000);
        });
    });
});
</script> 