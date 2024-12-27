# NextJS Groundhogg Integration Plugin

A WordPress plugin that provides seamless integration between NextJS applications and Groundhogg CRM.

## Features

- 🔄 Bi-directional sync between NextJS and Groundhogg
- 🔑 Secure API endpoints with authentication
- 🌐 CORS support for NextJS applications
- 📝 Contact management and tracking
- 📧 Email integration
- 📱 Phone number handling
- 🪝 Webhook support for real-time updates
- 📊 Activity logging
- ⚡ Rate limiting and caching

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- [Groundhogg CRM](https://wordpress.org/plugins/groundhogg/) plugin
- Valid Groundhogg API keys

## Installation

### Method 1: WordPress Admin
1. Go to Plugins > Add New > Upload Plugin
2. Select `nextjs-groundhogg-integration.zip`
3. Click "Install Now"
4. Activate the plugin

### Method 2: Manual Installation
1. Download the plugin zip file
2. Extract to `/wp-content/plugins/`
3. Activate through WordPress admin

## Configuration

### 1. API Keys
1. Navigate to Groundhogg > Settings > API Keys
2. Generate new API keys if you haven't already
3. Copy the Public Key and Token

### 2. CORS Settings
1. Go to NextJS CRM > General Settings
2. Add your NextJS application domains to Allowed Origins
3. Save changes

### 3. Environment Variables
Add these to your NextJS application's `.env.local`:
```env
# Groundhogg API Configuration
NEXT_PUBLIC_GROUNDHOGG_API_ENDPOINT=your-wordpress-url/wp-json/groundhogg/v4
NEXT_PUBLIC_GROUNDHOGG_API_VERSION=v4
NEXT_PUBLIC_GROUNDHOGG_PUBLIC_KEY=your-public-key
NEXT_PUBLIC_GROUNDHOGG_TOKEN=your-token
```

## Available Endpoints

### Basic Endpoints
- `GET /verify-connection`
- `POST /quick-contact-sync`
- `GET /contacts`
- `GET /contact/{id}`

### Contact Management
- `GET /contact-activity/{email}`
- `GET, POST /contact-notes/{email}`
- `GET, POST /contact-custom-fields/{email}`
- `PUT /update-contact`

### Form Handling
- `POST /submit-form`
- `POST /submit-form-with-files`
- `POST /form-submission`

### Phone-based Endpoints
- `GET /contact-by-phone/{phone}`
- `POST /quick-contact-sync-phone`

### Email Management
- `GET, POST /emails`
- `GET, PUT, DELETE /email/{id}`
- `POST /email/{id}/send`
- `GET /email-templates`

## Usage with NextJS SDK

This plugin is designed to work with the [Groundhogg NextJS SDK](https://github.com/Systemsaholic/groundhogg-nextjs-sdk).

```javascript
import { GroundhoggSDK } from 'groundhogg-nextjs-sdk';

const sdk = new GroundhoggSDK({
  apiEndpoint: process.env.NEXT_PUBLIC_GROUNDHOGG_API_ENDPOINT,
  publicKey: process.env.NEXT_PUBLIC_GROUNDHOGG_PUBLIC_KEY,
  token: process.env.NEXT_PUBLIC_GROUNDHOGG_TOKEN
});
```

## Webhook Support

The plugin includes webhook support for real-time updates:
- Contact creation/updates
- Tag applications/removals
- Form submissions
- Note additions
- Activity tracking
- Email events

Configure webhooks in NextJS CRM > Webhooks settings.

## Security

- API key authentication required
- Rate limiting enabled by default
- CORS protection
- WordPress nonce verification
- Input sanitization and validation

## Support

For issues and feature requests, please use the [GitHub issues](https://github.com/yourusername/nextjs-groundhogg-integration/issues) page.

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by [Al Guertin](https://systemsaholic.com) 