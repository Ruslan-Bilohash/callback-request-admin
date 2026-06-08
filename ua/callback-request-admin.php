<?php
/**
 * Plugin Name:       Callback Request Admin
 * Plugin URI:        https://bilohash.com
 * Description:       Професійна форма зворотного дзвінка з вибором типу контакту (Телефон / Email / Messenger), збереженням заявок в адмінці та гнучкими налаштуваннями дизайну.
 * Version:           1.2.0
 * Author:            Ruslan Bilohash
 * Author URI:        https://bilohash.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       callback-request-admin
 * Requires at least: 6.4
 * Tested up to:      7.0
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CRA_VERSION', '1.2.0');
define('CRA_PATH', plugin_dir_path(__FILE__));
define('CRA_URL',  plugin_dir_url(__FILE__));
define('CRA_SLUG', 'callback-request-admin');

// Підключаємо файли
require_once CRA_PATH . 'includes/config.php';
require_once CRA_PATH . 'includes/admin-settings.php';
require_once CRA_PATH . 'includes/form-handler.php';

// Реєстрація Custom Post Type для заявок
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_register_post_type() {
    register_post_type('callback_request', [
        'labels' => [
            'name'               => __('Callback Requests', 'callback-request-admin'),
            'singular_name'      => __('Callback Request', 'callback-request-admin'),
            'menu_name'          => __('Callback Requests', 'callback-request-admin'),
            'add_new'            => __('Add New', 'callback-request-admin'),
            'add_new_item'       => __('Add New Request', 'callback-request-admin'),
            'edit_item'          => __('Edit Request', 'callback-request-admin'),
            'new_item'           => __('New Request', 'callback-request-admin'),
            'view_item'          => __('View Request', 'callback-request-admin'),
            'search_items'       => __('Search Requests', 'callback-request-admin'),
            'not_found'          => __('No requests found', 'callback-request-admin'),
            'not_found_in_trash' => __('No requests found in Trash', 'callback-request-admin'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => CRA_SLUG, // Nest under our custom menu to avoid duplicates
        'menu_icon'           => 'dashicons-phone',
        'supports'            => ['title', 'custom-fields'],
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'show_in_rest'        => false,
    ]);
}
add_action('init', 'cra_register_post_type');

/**
 * Реєстрація CPT для підписників розсилки
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_register_subscriber_post_type() {
    register_post_type('callback_subscriber', [
        'labels' => [
            'name'               => __('Subscribers', 'callback-request-admin'),
            'singular_name'      => __('Subscriber', 'callback-request-admin'),
            'menu_name'          => __('Subscribers', 'callback-request-admin'),
            'not_found'          => __('No subscribers found', 'callback-request-admin'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => CRA_SLUG,
        'menu_icon'           => 'dashicons-email-alt',
        'supports'            => ['title', 'custom-fields'],
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'has_archive'         => false,
        'show_in_rest'        => false,
    ]);
}
add_action('init', 'cra_register_subscriber_post_type');

// Активація плагіна
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_activate() {
    cra_register_post_type();
    flush_rewrite_rules();

    // Створюємо дефолтні налаштування
    if (!get_option('cra_settings')) {
        add_option('cra_settings', cra_default_settings());
    }
}
register_activation_hook(__FILE__, 'cra_activate');

// Деактивація
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'cra_deactivate');

// Додаємо меню в адмінку
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_admin_menu() {
    // Головне меню → Налаштування
    add_menu_page(
        __('Callback Requests', 'callback-request-admin'),
        __('Callback Requests', 'callback-request-admin'),
        'manage_options',
        CRA_SLUG,
        'cra_settings_page',
        'dashicons-phone',
        59
    );

    // Перший підпункт — Налаштування (щоб не було дублікату)
    add_submenu_page(
        CRA_SLUG,
        __('Settings', 'callback-request-admin'),
        __('Settings', 'callback-request-admin'),
        'manage_options',
        CRA_SLUG,
        'cra_settings_page'
    );

    // Підпункт "Заявки"
    add_submenu_page(
        CRA_SLUG,
        __('All Requests', 'callback-request-admin'),
        __('All Requests', 'callback-request-admin'),
        'manage_options',
        'edit.php?post_type=callback_request'
    );

    // Підписники розсилки
    add_submenu_page(
        CRA_SLUG,
        __('Subscribers', 'callback-request-admin'),
        __('Subscribers', 'callback-request-admin'),
        'manage_options',
        'edit.php?post_type=callback_subscriber'
    );

    // Масова розсилка
    add_submenu_page(
        CRA_SLUG,
        __('Mass Mailing', 'callback-request-admin'),
        __('Mass Mailing', 'callback-request-admin'),
        'manage_options',
        CRA_SLUG . '-bulk',
        'cra_bulk_mailing_page'
    );
}
add_action('admin_menu', 'cra_admin_menu');

/**
 * Enqueue admin assets (settings page + CPT screens)
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_admin_enqueue($hook) {
    $screen = get_current_screen();

    $load = false;

    // Our settings page
    if ($hook === 'toplevel_page_' . CRA_SLUG) {
        $load = true;
    }

    // Bulk mailing page
    if (strpos($hook, CRA_SLUG . '-bulk') !== false) {
        $load = true;
    }

    // CPT list / edit screens
    if ($screen && $screen->post_type === 'callback_request') {
        $load = true;
    }

    if ($load) {
        wp_enqueue_style(
            'cra-admin',
            CRA_URL . 'assets/admin.css',
            [],
            CRA_VERSION
        );

        wp_enqueue_script(
            'cra-admin',
            CRA_URL . 'assets/admin.js',
            [],
            CRA_VERSION,
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'cra_admin_enqueue');

/**
 * Custom columns for callback_request list table
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_cpt_columns($columns) {
    $new = [
        'cb'              => $columns['cb'],
        'title'           => __('Title', 'callback-request-admin'),
        'contact_type'    => __('Contact Type', 'callback-request-admin'),
        'contact_value'   => __('Contact', 'callback-request-admin'),
        'user_name'       => __('Name', 'callback-request-admin'),
        'date'            => $columns['date'],
    ];
    return $new;
}
add_filter('manage_callback_request_posts_columns', 'cra_cpt_columns');

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_cpt_custom_column($column, $post_id) {
    switch ($column) {
        case 'contact_type':
            $type = get_post_meta($post_id, 'cra_contact_type', true);
            $labels = [
                'phone'     => '📞 Телефон',
                'email'     => '✉️ Email',
                'messenger' => '💬 Messenger',
            ];
            echo esc_html($labels[$type] ?? ucfirst($type));
            break;

        case 'contact_value':
            $value = get_post_meta($post_id, 'cra_contact_value', true);
            $type  = get_post_meta($post_id, 'cra_contact_type', true);

            if ($type === 'messenger') {
                $mtype = get_post_meta($post_id, 'cra_messenger_type', true);
                $m_labels = cra_get_messenger_options();
                $mname = $m_labels[$mtype] ?? ucfirst($mtype);
                echo esc_html($mname . ': ') . '<strong>' . esc_html($value) . '</strong>';
            } else {
                echo '<strong>' . esc_html($value) . '</strong>';
            }
            break;

        case 'user_name':
            $name = get_post_meta($post_id, 'cra_user_name', true);
            echo $name ? esc_html($name) : '—';
            break;
    }
}
add_action('manage_callback_request_posts_custom_column', 'cra_cpt_custom_column', 10, 2);

/**
 * Make some columns sortable
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_cpt_sortable_columns($columns) {
    $columns['contact_type']  = 'contact_type';
    $columns['contact_value'] = 'contact_value';
    return $columns;
}
add_filter('manage_edit-callback_request_sortable_columns', 'cra_cpt_sortable_columns');

/**
 * Кастомні колонки для підписників
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_subscriber_columns($columns) {
    return [
        'cb'        => $columns['cb'],
        'title'     => __('Email', 'callback-request-admin'),
        'name'      => __('Name', 'callback-request-admin'),
        'status'    => __('Status', 'callback-request-admin'),
        'date'      => __('Date', 'callback-request-admin'),
    ];
}
add_filter('manage_callback_subscriber_posts_columns', 'cra_subscriber_columns');

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_subscriber_custom_column($column, $post_id) {
    switch ($column) {
        case 'name':
            echo esc_html(get_post_meta($post_id, 'subscriber_name', true) ?: '—');
            break;
        case 'status':
            $status = get_post_meta($post_id, 'subscriber_status', true);
            echo $status === 'subscribed' ? '✅ Підписаний' : esc_html($status);
            break;
    }
}
add_action('manage_callback_subscriber_posts_custom_column', 'cra_subscriber_custom_column', 10, 2);

/**
 * (Usage instructions + live preview live inside the settings page in admin-settings.php)
 */

/**
 * Додаємо посилання "Налаштування" на сторінці плагінів
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_plugin_action_links($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=' . CRA_SLUG)) . '"><strong>' . __('Settings', 'callback-request-admin') . '</strong></a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cra_plugin_action_links');

/**
 * Налаштування SMTP через PHPMailer (якщо увімкнено)
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_configure_smtp($phpmailer) {
    $settings = cra_get_settings();

    if (empty($settings['enable_smtp']) || empty($settings['smtp_host'])) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = $settings['smtp_host'];
    $phpmailer->Port       = (int) $settings['smtp_port'];
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = $settings['smtp_username'];
    $phpmailer->Password   = $settings['smtp_password'];

    $enc = strtolower($settings['smtp_encryption']);
    if ($enc === 'ssl') {
        $phpmailer->SMTPSecure = 'ssl';
    } elseif ($enc === 'tls') {
        $phpmailer->SMTPSecure = 'tls';
    } else {
        $phpmailer->SMTPAutoTLS = false;
    }

    // From
    if (!empty($settings['smtp_from_email'])) {
        $from_name = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : get_bloginfo('name');
        $phpmailer->setFrom($settings['smtp_from_email'], $from_name);
    }
}
add_action('phpmailer_init', 'cra_configure_smtp');

/**
 * Зберігаємо останню помилку відправки email для діагностики
 */
add_action('wp_mail_failed', function($wp_error) {
    update_option('cra_last_mail_error', $wp_error->get_error_message());
});
