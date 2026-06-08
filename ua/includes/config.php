<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Дефолтні налаштування плагіна
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_default_settings() {
    return [
        // Загальні
        'form_title'           => 'Передзвоніть мені',
        'button_text'          => 'Передзвоніть мене',
        'success_message'      => 'Дякуємо! Ми зв\'яжемося з вами найближчим часом.',
        'admin_email'          => get_option('admin_email'),

        // Типи контактів (які показувати)
        'enable_phone'         => true,
        'enable_email'         => true,
        'enable_messenger'     => true,

        // Дизайн
        'form_background'      => '#ffffff',
        'button_color'         => '#2563eb',
        'button_text_color'    => '#ffffff',
        'accent_color'         => '#2563eb',
        'form_border_radius'   => '16px',
        'form_padding'         => '32px',

        // Повідомлення (Email)
        'enable_email_notification' => true,
        'additional_emails'         => '',   // Додаткові email (по одному на рядок)

        // Telegram
        'enable_telegram'           => false,
        'telegram_bot_token'        => '',
        'telegram_chat_id'          => '',

        // SMTP (для надійної відправки email)
        'enable_smtp'               => false,
        'smtp_host'                 => '',
        'smtp_port'                 => '587',
        'smtp_encryption'           => 'tls', // none, ssl, tls
        'smtp_username'             => '',
        'smtp_password'             => '',
        'smtp_from_name'            => '',
        'smtp_from_email'           => '',

        // IMAP (для майбутніх функцій, напр. обробка відповідей)
        'enable_imap'               => false,
        'imap_host'                 => '',
        'imap_port'                 => '993',
        'imap_encryption'           => 'ssl',
        'imap_username'             => '',
        'imap_password'             => '',

        // Дизайн email-листів
        'email_header_color'        => '#2563eb',
        'email_accent_color'        => '#2563eb',
        'email_logo_url'            => '',
        'email_footer_text'         => 'З повагою, команда сайту',

        // Підписка на розсилку (Newsletter)
        'enable_subscribe'          => true,
        'subscribe_title'           => 'Підписатися на розсилку',
        'subscribe_button_text'     => 'Підписатися',
        'subscribe_success_message' => 'Дякуємо! Ви успішно підписалися на розсилку.',
        'subscribe_require_name'    => true,
        'subscribe_double_optin'    => false,

        // Окремий дизайн для форми підписки
        'subscribe_form_background'      => '#f0fdf4',
        'subscribe_button_color'         => '#16a34a',
        'subscribe_button_text_color'    => '#ffffff',
        'subscribe_accent_color'         => '#16a34a',
        'subscribe_form_border_radius'   => '12px',
        'subscribe_form_padding'         => '24px',

        // reCAPTCHA
        'enable_recaptcha'             => false,
        'recaptcha_site_key'           => '',
        'recaptcha_secret_key'         => '',
        'enable_recaptcha_callback'    => true,
        'enable_recaptcha_subscribe'   => true,
    ];
}

/**
 * Отримуємо поточні налаштування
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_get_settings() {
    return get_option('cra_settings', cra_default_settings());
}

/**
 * Доступні типи месенджерів
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_get_messenger_options() {
    return [
        'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram',
        'viber'    => 'Viber',
    ];
}
