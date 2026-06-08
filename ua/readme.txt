=== Callback Request Admin ===
Contributors: rbilohash
Donate link: https://bilohash.com/donate.php
Tags: callback, form, newsletter, telegram, smtp
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional callback request form with contact type selection, design customization, email & Telegram notifications, and newsletter support.

== Description ==
**Callback Request Admin** is a complete lead capture and newsletter solution for WordPress. It includes a beautiful callback request form and a separate subscription form, both with full design control, reliable notifications, and powerful admin tools.

### Key Features

- **Callback Request Form** `[callback_request_form]`
  - Choice of contact type: Phone, Email or Messenger (WhatsApp / Telegram / Viber)
  - All requests saved as custom post type with nice admin list (contact type, value, name, date)
  - Live preview directly in the Design tab

- **Newsletter Subscription Form** `[callback_subscribe_form]`
  - Separate form with its own settings (title, button, success message, require name, double opt-in)
  - Subscribers stored separately with status management
  - Preview available in the Subscribe tab

- **Powerful Design System**
  - Full control over colors (background, button, accent), border radius and padding
  - Same design settings apply to both forms and all outgoing emails
  - Real-time preview while editing

- **Email Notifications**
  - Main admin email + unlimited additional emails
  - Beautiful HTML emails with your logo, colors and footer (from Email Design settings)
  - Built-in **Test Email** button to verify delivery instantly
  - Full **SMTP support** (host, port, encryption, username, password, From Name/Email)
  - Automatic fallback to server default mail when SMTP is disabled

- **Telegram Notifications** (optional)
  - Instant notifications to bot, group or channel when new request or subscription arrives
  - Easy setup with Bot Token and Chat ID

- **Mass Mailing (Bulk Email)**
  - Dedicated **Mass Mailing** page in the admin menu (not inside tabs)
  - Send to all active subscribers with one click
  - Personalization with `{name}` and `{email}` placeholders
  - Uses the same beautiful email styling as regular notifications
  - Shows exact count of successful / failed deliveries

- **Admin Experience**
  - Clean tabbed settings: General, Design, Notifications, Integrations, Email (SMTP), Subscribe, Mass Mailing, Links
  - Custom columns and quick overview for Requests and Subscribers
  - Separate "Mass Mailing" item in the plugin menu for focused workflow

Perfect for:
- Service businesses, clinics, auto services, consultants, agencies, online stores, and any site that needs callback requests or newsletter subscriptions.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/callback-request-admin/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Callback Requests → Settings**
4. Configure General settings (form title, button text, contact types)
5. Go to **Design** tab — adjust colors, radius and padding (live preview is inside the tab)
6. Set up Email notifications and (optionally) Telegram in the corresponding tabs
7. (Optional but recommended) Configure SMTP in the **Email (SMTP)** tab and use the built-in Test Email button
8. Insert shortcodes on your pages:
   - `[callback_request_form]` — callback request form
   - `[callback_subscribe_form]` — newsletter subscription form
9. Use the **Mass Mailing** menu item to send emails to all active subscribers

== Frequently Asked Questions ==

= How do I add the callback form? =
Just paste the shortcode `[callback_request_form]` anywhere on your site (page, post, widget, Elementor, etc.).

= How do I add the subscription form? =
Use the shortcode `[callback_subscribe_form]`.

= Do I need to configure SMTP? =
Not required. The plugin works with the default WordPress mail function. However, many hosting providers have poor default mail delivery. We strongly recommend setting up SMTP (Gmail, Mailgun, SendGrid, Brevo, etc.) and using the **Test Email** button to verify everything works.

= Can I send mass emails to subscribers? =
Yes. Go to the **Mass Mailing** item in the Callback Requests menu. You can write a subject and message (with `{name}` and `{email}` placeholders) and send it to all active subscribers with one click. The emails use the same beautiful styling as regular notifications.

= Can I customize the look of the emails? =
Yes. Go to the **Email (SMTP)** tab → "Design of email letters". You can set header color, accent color, logo URL and footer text. All outgoing emails (requests, subscriptions, mass mailings and test letters) will use this design.

= Is Telegram required? =
No. Telegram notifications are completely optional. You can use only email or only Telegram, or both.

= Where are the requests and subscribers stored? =
All data is stored as custom post types (`callback_request` and `callback_subscriber`) in your WordPress database. You can view and manage them directly from the admin menu.

= Can I use my own SMTP? =
Yes. The plugin has full SMTP settings (host, port, encryption, authentication). The configuration is done through the standard `phpmailer_init` hook, so it works reliably with most providers.

== What's New in 1.2.0 ==

- Added dedicated **Mass Mailing** page in the admin menu (separate from tabs) for sending bulk emails to subscribers
- Beautiful email composer with live preview of the styled letter
- Personalization placeholders `{name}` and `{email}` in mass mailings
- Improved email delivery system with better error handling and last-error logging
- Added "Test Email" functionality with visible last error in the Email tab
- Separate nicely designed page for bulk sending with full email styling
- Fixed multiple Plugin Check issues (sanitization, escaping, slow queries, nested forms)
- Admin previews no longer output nested forms (fixes main settings form submission)
- Many code quality and WordPress.org compliance improvements

== Changelog ==

= 1.2.0 =
* Added full **Mass Mailing** feature as separate admin menu item
* Nice composer + preview for bulk emails to subscribers
* Personalization with {name} and {email}
* Improved SMTP test tool + last error display
* Fixed nested form issue that was breaking the main "Save Settings" button
* Fixed multiple WordPress Plugin Check warnings (sanitization, escaping, DB queries, naming)
* Added .phpcs.xml.dist for consistent code standards
* Admin previews now use special "admin_preview" mode (no nested forms)
* Better documentation and readme.txt

= 1.1.0 =
* Added dedicated **Email (SMTP)** tab with full SMTP configuration
* Built-in Test Email sender with error logging
* IMAP settings section (prepared for future features)
* Email design settings (header color, accent, logo, footer) applied to all outgoing letters
* Newsletter subscription form with its own settings and preview
* Separate "Subscribe" tab with subscription form preview
* Mass mailing logic prepared (later moved to dedicated menu)
* Improved HTML email templates using design settings
* Multiple security and sanitization fixes

= 1.0.0 =
* Initial release
* Callback request form with Phone / Email / Messenger selection
* Design customization (colors, radius, padding) with live preview
* Email notifications (main + additional recipients)
* Optional Telegram notifications
* Subscribers management
* Shortcodes `[callback_request_form]` and `[callback_subscribe_form]`
* Admin interface with tabs and custom columns

== Upgrade Notice ==

= 1.2.0 =
Major update! Mass Mailing is now a separate menu item with a beautiful composer and preview. Fixed the issue where the main "Save Settings" button stopped working due to nested forms. Many Plugin Check and security improvements. Highly recommended update.

== External Services ==

This plugin can optionally communicate with external services depending on your configuration.

**Telegram Bot API** (https://api.telegram.org)
- Purpose: (optional) sends instant notifications about new callback requests and subscriptions to your Telegram bot/group/channel.
- Data sent: message text containing request/subscription details.
- Data is sent only when a new request or subscription is submitted **and** Telegram integration is enabled in settings.
- Terms of Service: https://telegram.org/tos/bots
- Privacy Policy: https://telegram.org/privacy

**SMTP Servers** (user-configured)
- Purpose: reliable email delivery (instead of the site's default mail function).
- Data sent: standard email content (to, subject, body, headers).
- You configure your own SMTP credentials in the plugin settings (Gmail, Mailgun, SendGrid, Brevo, your hosting SMTP, etc.).
- No data is sent to any third-party service unless you explicitly enable and configure SMTP.
- The plugin uses WordPress built-in PHPMailer via the `phpmailer_init` hook.

The plugin does **not** send any data to external services by default. All external communication is optional and fully controlled by you in the plugin settings.