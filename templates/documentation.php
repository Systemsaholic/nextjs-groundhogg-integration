<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
?>

<div class="documentation-wrapper">
    <div class="doc-sidebar">
        <ul class="doc-nav">
            <li><a href="#getting-started" class="active">Getting Started</a></li>
            <li><a href="#webhook-setup">Webhook Setup</a></li>
            <li><a href="#api-endpoints">API Endpoints</a></li>
            <li><a href="#phone-integration">Phone Integration</a></li>
            <li><a href="#webhook-events">Webhook Events</a></li>
            <li><a href="#email-management">Email Management</a></li>
            <li><a href="#nextjs-setup">NextJS Setup</a></li>
            <li><a href="#receiving-data">Receiving Data from NextJS</a></li>
            <li><a href="#troubleshooting">Troubleshooting</a></li>
        </ul>
    </div>

    <div class="doc-content">
        <section id="getting-started">
            <h2>Getting Started</h2>
            <p>The NextJS Groundhogg Integration plugin connects your WordPress/Groundhogg CRM with a NextJS frontend application, enabling real-time data synchronization and seamless integration.</p>
            
            <h3>Prerequisites</h3>
            <ul>
                <li>WordPress with Groundhogg CRM installed and activated</li>
                <li>A NextJS application (version 12 or higher)</li>
                <li>Basic understanding of WordPress and NextJS development</li>
            </ul>

            <h3>Quick Start Guide</h3>
            <ol>
                <li>Configure allowed origins in the General Settings tab</li>
                <li>Set up webhook URL in the Webhooks tab</li>
                <li>Enable desired webhook events</li>
                <li>Generate NextJS endpoint code using the NextJS Generator</li>
                <li>Implement the generated code in your NextJS application</li>
            </ol>
        </section>

        <section id="webhook-setup">
            <h2>Webhook Setup</h2>
            <p>Webhooks allow real-time notifications when events occur in Groundhogg.</p>

            <h3>Configuration Steps</h3>
            <ol>
                <li>Go to the Webhooks tab in the plugin settings</li>
                <li>Enter your NextJS application's webhook endpoint URL</li>
                <li>Select which events you want to receive notifications for</li>
                <li>Test the webhook connection using the "Send Test" button</li>
            </ol>

            <h3>Webhook Security</h3>
            <p>Each webhook request includes security headers:</p>
            <ul>
                <li><code>X-Groundhogg-Event</code>: The type of event that triggered the webhook</li>
                <li><code>X-Groundhogg-Site</code>: Your WordPress site URL</li>
                <li><code>X-Groundhogg-Timestamp</code>: The event timestamp</li>
            </ul>
        </section>

        <section id="api-endpoints">
            <h2>API Endpoints</h2>
            <p>The plugin provides REST API endpoints for interacting with Groundhogg data:</p>

            <h3>Available Endpoints</h3>
            <pre><code>GET /wp-json/nextjs-groundhogg/v1/contacts
GET /wp-json/nextjs-groundhogg/v1/contact/{id}
POST /wp-json/nextjs-groundhogg/v1/contact
PUT /wp-json/nextjs-groundhogg/v1/contact/{id}
GET /wp-json/nextjs-groundhogg/v1/contact-by-phone/{phone}
POST /wp-json/nextjs-groundhogg/v1/quick-contact-sync-phone</code></pre>

            <h3>Authentication</h3>
            <p>API requests require authentication using either:</p>
            <ul>
                <li>WordPress nonce authentication for admin-level access</li>
                <li>API key authentication for frontend access</li>
            </ul>
        </section>

        <section id="phone-integration">
            <h2>Phone Integration</h2>
            <p>The plugin provides special handling for phone-based contact management:</p>

            <h3>Features</h3>
            <ul>
                <li>Create/update contacts using only phone numbers</li>
                <li>Configurable phone number formatting</li>
                <li>Automatic country code handling</li>
                <li>Phone number validation</li>
            </ul>

            <h3>Configuration</h3>
            <p>In the Phone Settings tab, you can configure:</p>
            <ul>
                <li>Phone number format (international/national/raw)</li>
                <li>Default country code</li>
                <li>Country code requirements</li>
            </ul>
        </section>

        <section id="webhook-events">
            <h2>Webhook Events</h2>
            <p>The plugin supports the following webhook events:</p>

            <h3>Contact Events</h3>
            <ul>
                <li><code>contact.created</code>: When a new contact is created</li>
                <li><code>contact.updated</code>: When contact details are updated</li>
                <li><code>contact.deleted</code>: When a contact is deleted</li>
            </ul>

            <h3>Tag Events</h3>
            <ul>
                <li><code>tag.applied</code>: When a tag is added to a contact</li>
                <li><code>tag.removed</code>: When a tag is removed from a contact</li>
            </ul>

            <h3>Form Events</h3>
            <ul>
                <li><code>form.submitted</code>: When a form is submitted</li>
            </ul>

            <h3>Other Events</h3>
            <ul>
                <li><code>note.added</code>: When a note is added to a contact</li>
                <li><code>activity.added</code>: When an activity is recorded</li>
                <li><code>email.sent</code>: When an email is sent</li>
                <li><code>email.opened</code>: When an email is opened</li>
                <li><code>email.clicked</code>: When an email link is clicked</li>
            </ul>
        </section>

        <section id="email-management">
            <h2>Email Management</h2>
            <p>The plugin provides comprehensive email management capabilities through its API endpoints.</p>

            <h3>Creating Emails</h3>
            <pre><code>POST /wp-json/nextjs-groundhogg/v1/emails
Content-Type: application/json
X-GH-API-KEY: your-api-key

{
    "subject": "Welcome to Our Newsletter",
    "content": "Hello &#123;first_name&#125;,\n\nWelcome to our newsletter!",
    "from_name": "Your Company",
    "from_email": "newsletter@example.com",
    "reply_to": "support@example.com",
    "status": "draft",
    "template": "newsletter"
}</code></pre>

            <h3>Retrieving Emails</h3>
            <pre><code>GET /wp-json/nextjs-groundhogg/v1/emails
X-GH-API-KEY: your-api-key

// Optional status filter
GET /wp-json/nextjs-groundhogg/v1/emails?status=draft</code></pre>

            <h3>Managing Single Email</h3>
            <pre><code>// Get email details
GET /wp-json/nextjs-groundhogg/v1/email/123
X-GH-API-KEY: your-api-key

// Update email
PUT /wp-json/nextjs-groundhogg/v1/email/123
Content-Type: application/json
X-GH-API-KEY: your-api-key

{
    "subject": "Updated Subject",
    "content": "Updated content",
    "status": "published"
}

// Delete email
DELETE /wp-json/nextjs-groundhogg/v1/email/123
X-GH-API-KEY: your-api-key</code></pre>

            <h3>Sending Emails</h3>
            <pre><code>POST /wp-json/nextjs-groundhogg/v1/email/123/send
Content-Type: application/json
X-GH-API-KEY: your-api-key

{
    "contact_ids": [456, 789]
}</code></pre>

            <h3>Email Templates</h3>
            <pre><code>GET /wp-json/nextjs-groundhogg/v1/email-templates
X-GH-API-KEY: your-api-key</code></pre>

            <h3>NextJS Implementation Example</h3>
            <pre><code>// app/components/EmailManager.tsx
import { useState } from 'react';

interface Email {
    id: number;
    subject: string;
    content: string;
    status: string;
}

export default function EmailManager() {
    const [emails, setEmails] = useState<Email[]>([]);
    const [newEmail, setNewEmail] = useState({
        subject: '',
        content: '',
        status: 'draft'
    });

    const API_BASE = process.env.NEXT_PUBLIC_WP_URL + '/wp-json/nextjs-groundhogg/v1';
    const API_KEY = process.env.NEXT_PUBLIC_GH_API_KEY;

    const createEmail = async () => {
        try {
            const response = await fetch(API_BASE + '/emails', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-GH-API-KEY': API_KEY
                },
                body: JSON.stringify(newEmail)
            });

            if (response.ok) {
                const result = await response.json();
                setEmails([...emails, result.email]);
                setNewEmail({ subject: '', content: '', status: 'draft' });
            }
        } catch (error) {
            console.error('Error creating email:', error);
        }
    };

    const sendEmail = async (emailId: number, contactIds: number[]) => {
        try {
            const response = await fetch(
                API_BASE + '/email/' + emailId + '/send',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-GH-API-KEY': API_KEY
                    },
                    body: JSON.stringify({ contact_ids: contactIds })
                }
            );

            if (response.ok) {
                const result = await response.json();
                console.log('Email sent successfully:', result);
            }
        } catch (error) {
            console.error('Error sending email:', error);
        }
    };

    return (
        <div>
            {/* Email creation form */}
            <form onSubmit={(e) => {
                e.preventDefault();
                createEmail();
            }}>
                <input
                    type="text"
                    placeholder="Subject"
                    value={newEmail.subject}
                    onChange={(e) => setNewEmail({
                        ...newEmail,
                        subject: e.target.value
                    })}
                />
                <textarea
                    placeholder="Content"
                    value={newEmail.content}
                    onChange={(e) => setNewEmail({
                        ...newEmail,
                        content: e.target.value
                    })}
                />
                <button type="submit">Create Email</button>
            </form>

            {/* Email list */}
            <?php
            $code_example = <<<'CODE'
<div className="email-list">
    {emails.map(email => (
        <div key={email.id} className="email-item">
            <h3>{email.subject}</h3>
            <p>{email.status}</p>
            <button onClick={() => sendEmail(email.id, [])}>
                Send Email
            </button>
        </div>
    ))}
</div>
CODE;
            ?>
            <pre><code><?php echo htmlspecialchars($code_example); ?></code></pre>

            <h3>Available Email Placeholders</h3>
            <p>You can use these placeholders in your email content:</p>
            <ul>
                <li><code>&#123;first_name&#125;</code>: Contact's first name</li>
                <li><code>&#123;last_name&#125;</code>: Contact's last name</li>
                <li><code>&#123;email&#125;</code>: Contact's email address</li>
                <li><code>&#123;phone&#125;</code>: Contact's phone number</li>
                <li><code>&#123;unsubscribe_link&#125;</code>: Unsubscribe link</li>
                <li><code>&#123;view_in_browser&#125;</code>: View in browser link</li>
            </ul>

            <h3>Email Status Values</h3>
            <ul>
                <li><code>draft</code>: Email is in draft mode</li>
                <li><code>published</code>: Email is ready to be sent</li>
                <li><code>scheduled</code>: Email is scheduled for future sending</li>
                <li><code>archived</code>: Email is archived</li>
            </ul>
        </section>

        <section id="nextjs-setup">
            <h2>NextJS Setup</h2>
            <p>To integrate with your NextJS application:</p>

            <h3>1. Generate API Endpoint Code</h3>
            <ol>
                <li>Go to the NextJS Generator tab</li>
                <li>Select the events you want to handle</li>
                <li>Choose your preferred database ORM</li>
                <li>Select additional features (validation, logging)</li>
                <li>Generate and copy the code</li>
            </ol>

            <h3>2. Implementation Steps</h3>
            <ol>
                <li>Create the webhook endpoint file in your NextJS app</li>
                <li>Set up your chosen database ORM</li>
                <li>Configure environment variables</li>
                <li>Test the webhook endpoint</li>
            </ol>

            <h3>Example Environment Variables</h3>
            <pre><code>DATABASE_URL="your-database-url"
GROUNDHOGG_WEBHOOK_SECRET="your-webhook-secret"
GROUNDHOGG_API_KEY="your-api-key"</code></pre>
        </section>

        <section id="receiving-data">
            <h2>Receiving Data from NextJS</h2>
            <p>The plugin provides endpoints to receive data from your NextJS application, such as form submissions and contact updates.</p>

            <h3>Form Submissions</h3>
            <p>To submit form data from NextJS to Groundhogg:</p>
            <?php
            $form_submission_example = <<<'EOD'
POST /wp-json/nextjs-groundhogg/v1/submit-form
Content-Type: application/json
X-GH-API-KEY: your-api-key

{
    "form_id": "contact_form",
    "data": {
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "phone": "+1-555-123-4567",
        "message": "Hello from NextJS!",
        "tags": ["newsletter", "lead"],
        "meta": {
            "source": "nextjs_contact_form",
            "page_url": "https://example.com/contact"
        }
    }
}
EOD;
            ?>
            <pre><code><?php echo htmlspecialchars($form_submission_example); ?></code></pre>

            <h3>Contact Updates</h3>
            <p>To update contact information from NextJS:</p>
            <pre><code>PUT /wp-json/nextjs-groundhogg/v1/update-contact
Content-Type: application/json
X-GH-API-KEY: your-api-key

{
    "contact_id": 123,
    "data": {
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "phone": "+1-555-123-4567",
        "tags_to_add": ["tag1", "tag2"],
        "tags_to_remove": ["old_tag"],
        "meta": {
            "custom_field": "value"
        }
    }
}</code></pre>

            <h3>NextJS Implementation Example</h3>
            <pre><code>// app/components/ContactForm.tsx
import { useState } from 'react';

export default function ContactForm() {
    const [status, setStatus] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        const formData = new FormData(e.target as HTMLFormElement);
        
        try {
            const response = await fetch('${YOUR_WP_URL}/wp-json/nextjs-groundhogg/v1/submit-form', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-GH-API-KEY': process.env.NEXT_PUBLIC_GH_API_KEY
                },
                body: JSON.stringify({
                    form_id: 'contact_form',
                    data: {
                        first_name: formData.get('first_name'),
                        last_name: formData.get('last_name'),
                        email: formData.get('email'),
                        phone: formData.get('phone'),
                        message: formData.get('message'),
                        meta: {
                            source: 'nextjs_contact_form',
                            page_url: window.location.href
                        }
                    }
                })
            });

            if (response.ok) {
                setStatus('success');
            } else {
                setStatus('error');
            }
        } catch (error) {
            setStatus('error');
            console.error('Error submitting form:', error);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <input type="text" name="first_name" placeholder="First Name" required />
            <input type="text" name="last_name" placeholder="Last Name" required />
            <input type="email" name="email" placeholder="Email" required />
            <input type="tel" name="phone" placeholder="Phone" />
            <textarea name="message" placeholder="Message" required></textarea>
            <button type="submit">Submit</button>
            {status === 'success' && <p>Form submitted successfully!</p>}
            {status === 'error' && <p>Error submitting form. Please try again.</p>}
        </form>
    );
}
</code></pre>

            <h3>Security Considerations</h3>
            <ul>
                <li>Always use HTTPS for API requests</li>
                <li>Keep your API key secure and never expose it in client-side code</li>
                <li>Use environment variables for sensitive data</li>
                <li>Implement rate limiting to prevent abuse</li>
            </ul>

            <h3>Handling File Uploads</h3>
            <p>For forms with file uploads:</p>
            <pre><code>POST /wp-json/nextjs-groundhogg/v1/submit-form-with-files
Content-Type: multipart/form-data
X-GH-API-KEY: your-api-key

// Form data with files
{
    "form_id": "contact_form",
    "data": { ... },
    "files": {
        "attachment": File
    }
}</code></pre>
        </section>

        <section id="troubleshooting">
            <h2>Troubleshooting</h2>
            <p>Common issues and their solutions:</p>

            <h3>Webhook Issues</h3>
            <ul>
                <li>Verify webhook URL is correct and accessible</li>
                <li>Check webhook logs for delivery status</li>
                <li>Ensure proper authentication headers</li>
            </ul>

            <h3>API Issues</h3>
            <ul>
                <li>Verify API key is valid and active</li>
                <li>Check CORS settings in General Settings</li>
                <li>Review server error logs</li>
            </ul>
        </section>
    </div>
</div>

<style>
.documentation-wrapper {
    display: flex;
    gap: 30px;
    margin-top: 20px;
}

.doc-sidebar {
    flex: 0 0 200px;
    position: sticky;
    top: 32px;
    height: calc(100vh - 32px);
    overflow-y: auto;
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.doc-nav {
    margin: 0;
    padding: 0;
    list-style: none;
}

.doc-nav li {
    margin-bottom: 10px;
}

.doc-nav a {
    display: block;
    padding: 8px 12px;
    color: #23282d;
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.doc-nav a:hover,
.doc-nav a.active {
    background: #f0f0f1;
    color: #2271b1;
}

.doc-content {
    flex: 1;
    max-width: 800px;
    background: #fff;
    padding: 30px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.doc-content section {
    margin-bottom: 40px;
    padding-bottom: 40px;
    border-bottom: 1px solid #f0f0f1;
}

.doc-content section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.doc-content h2 {
    margin-top: 0;
    padding-top: 20px;
    margin-bottom: 20px;
    font-size: 24px;
    color: #1d2327;
}

.doc-content h3 {
    margin-top: 30px;
    margin-bottom: 15px;
    font-size: 18px;
    color: #1d2327;
}

.doc-content p {
    line-height: 1.6;
    margin-bottom: 15px;
}

.doc-content ul,
.doc-content ol {
    margin-left: 20px;
    margin-bottom: 15px;
}

.doc-content li {
    margin-bottom: 8px;
    line-height: 1.6;
}

.doc-content code {
    background: #f6f7f7;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 13px;
}

.doc-content pre {
    background: #f6f7f7;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    margin-bottom: 15px;
}

.doc-content pre code {
    padding: 0;
    background: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.doc-nav a');
    const sections = document.querySelectorAll('.doc-content section');

    // Handle navigation clicks
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href').substring(1);
            const targetSection = document.getElementById(targetId);
            
            if (targetSection) {
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                targetSection.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // Update active link on scroll
    window.addEventListener('scroll', () => {
        let currentSection = '';
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (window.pageYOffset >= sectionTop - 60) {
                currentSection = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${currentSection}`) {
                link.classList.add('active');
            }
        });
    });
});
</script>