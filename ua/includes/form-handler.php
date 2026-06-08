<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend form + submission handler for Callback Request Admin
 * Follows WordPress standards: wp_enqueue_style/script + wp_localize_script
 */

// Enqueue Google reCAPTCHA script (with async/defer) only if the feature is enabled for any form.
// This must happen at wp_enqueue_scripts time for proper output.
add_action( 'wp_enqueue_scripts', function() {
    $settings = cra_get_settings();
    if ( ! empty( $settings['enable_recaptcha'] ) &&
         ( ! empty( $settings['enable_recaptcha_callback'] ) || ! empty( $settings['enable_recaptcha_subscribe'] ) ) &&
         ! empty( $settings['recaptcha_site_key'] ) ) {
        wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js', [], CRA_VERSION, false );
        if ( ! has_filter( 'script_loader_tag', 'cra_add_recaptcha_async_defer' ) ) {
            add_filter( 'script_loader_tag', 'cra_add_recaptcha_async_defer', 10, 2 );
        }
    }
} );

/**
 * Register frontend assets (style + script). Safe to call multiple times.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_register_frontend_assets() {
    wp_register_style(
        'cra-form',
        CRA_URL . 'assets/cra-form.css',
        [],
        CRA_VERSION
    );

    wp_register_script(
        'cra-form',
        CRA_URL . 'assets/cra-form.js',
        [],
        CRA_VERSION,
        true // load in footer
    );

    wp_localize_script('cra-form', 'craForm', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action'  => 'cra_submit_callback',
    ]);
}

/**
 * Enqueue the frontend form assets (CSS + JS).
 * Call this from shortcode or when previewing in admin.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_enqueue_frontend_assets() {
    cra_register_frontend_assets();
    wp_enqueue_style('cra-form');
    wp_enqueue_script('cra-form');
}

// Register on both frontend and admin (for settings page live preview)
add_action('wp_enqueue_scripts', 'cra_register_frontend_assets');
add_action('admin_enqueue_scripts', 'cra_register_frontend_assets');

/**
 * Shortcode: [callback_request_form]
 * Supports preview="1" attribute to disable submissions (used in admin settings preview)
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_render_callback_form($atts = []) {
    $atts = shortcode_atts([
        'preview' => '0',
        'admin_preview' => '0',
    ], $atts, 'callback_request_form');

    $is_preview = !empty($atts['preview']) && $atts['preview'] !== '0';
    $is_admin_preview = !empty($atts['admin_preview']) && $atts['admin_preview'] !== '0';

    $settings = cra_get_settings();

    // Determine which contact types are enabled
    $enabled = [];
    if (!empty($settings['enable_phone']))    $enabled[] = 'phone';
    if (!empty($settings['enable_email']))    $enabled[] = 'email';
    if (!empty($settings['enable_messenger'])) $enabled[] = 'messenger';

    if (empty($enabled)) {
        $enabled = ['phone'];
    }

    $default_type      = $enabled[0];
    $messenger_options = cra_get_messenger_options();

    // Unique prefix for this instance (good for multiple shortcodes + a11y)
    $uid = 'cra-' . uniqid();

    // Enqueue assets the WordPress way (safe on frontend + when previewing in admin)
    cra_enqueue_frontend_assets();

    // Dynamic design values → CSS custom properties (set on wrapper)
    $css_vars = sprintf(
        '--cra-bg:%s;--cra-btn:%s;--cra-btn-text:%s;--cra-accent:%s;--cra-radius:%s;--cra-pad:%s;',
        esc_attr($settings['form_background']),
        esc_attr($settings['button_color']),
        esc_attr($settings['button_text_color']),
        esc_attr($settings['accent_color']),
        esc_attr($settings['form_border_radius']),
        esc_attr($settings['form_padding'])
    );

    ob_start();
    ?>
    <div class="cra-form-wrap" data-preview="<?php echo $is_preview ? '1' : '0'; ?>" style="<?php echo esc_attr( $css_vars ); ?>">

        <div class="cra-form">
            <h3><?php echo esc_html($settings['form_title']); ?></h3>

            <?php if ( ! $is_admin_preview ) : ?>
            <form class="cra-form-element" method="post" novalidate>
                <?php wp_nonce_field('cra_submit_form', 'cra_nonce'); ?>
                <input type="hidden" name="action" value="cra_submit_callback">
            <?php endif; ?>

                <div class="cra-field">
                    <label for="<?php echo esc_attr($uid); ?>-name">Ім'я</label>
                    <input type="text" id="<?php echo esc_attr($uid); ?>-name" name="name" placeholder="Ваше ім'я (необов'язково)">
                </div>

                <div class="cra-field">
                    <label>Як з вами зв'язатися?</label>
                    <div class="cra-contact-types">
                        <?php foreach ($enabled as $type) :
                            $label = $type === 'phone' ? 'Телефон' : ($type === 'email' ? 'Email' : 'Messenger');
                            $radio_id = $uid . '-ct-' . $type;
                        ?>
                            <div>
                                <input type="radio"
                                       id="<?php echo esc_attr($radio_id); ?>"
                                       name="contact_type"
                                       value="<?php echo esc_attr($type); ?>"
                                       <?php checked($type, $default_type); ?>>
                                <label for="<?php echo esc_attr($radio_id); ?>" class="cra-type-label<?php echo $type === $default_type ? ' active' : ''; ?>">
                                    <?php echo esc_html($label); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Phone field -->
                <div class="cra-field cra-contact-field" data-type="phone"<?php echo $default_type === 'phone' ? '' : ' style="display:none;"'; ?>>
                    <label for="<?php echo esc_attr($uid); ?>-phone">Номер телефону</label>
                    <input type="tel" id="<?php echo esc_attr($uid); ?>-phone" name="phone" placeholder="+380 XX XXX XX XX">
                </div>

                <!-- Email field -->
                <div class="cra-field cra-contact-field" data-type="email"<?php echo $default_type === 'email' ? '' : ' style="display:none;"'; ?>>
                    <label for="<?php echo esc_attr($uid); ?>-email">Email</label>
                    <input type="email" id="<?php echo esc_attr($uid); ?>-email" name="email" placeholder="you@example.com">
                </div>

                <!-- Messenger field -->
                <div class="cra-field cra-contact-field" data-type="messenger"<?php echo $default_type === 'messenger' ? '' : ' style="display:none;"'; ?>>
                    <label>Месенджер</label>
                    <div class="cra-messenger-row">
                        <select name="messenger_type" id="<?php echo esc_attr($uid); ?>-messenger-type">
                            <?php foreach ($messenger_options as $key => $m_label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($m_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="messenger_value" id="<?php echo esc_attr($uid); ?>-messenger-value" placeholder="Номер або @username">
                    </div>
                </div>

                <div class="cra-field">
                    <label for="<?php echo esc_attr($uid); ?>-message">Повідомлення (необов'язково)</label>
                    <textarea id="<?php echo esc_attr($uid); ?>-message" name="message" rows="3" placeholder="Коротко опишіть, з якого приводу дзвінок..."></textarea>
                </div>

                <?php if ( ! $is_admin_preview && !empty($settings['enable_recaptcha']) && !empty($settings['enable_recaptcha_callback']) && !empty($settings['recaptcha_site_key']) ) : ?>
                <div class="cra-field">
                    <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($settings['recaptcha_site_key']); ?>"></div>
                </div>
                <?php endif; ?>

                <?php if ( ! $is_admin_preview ) : ?>
                <button type="submit" class="cra-submit"<?php echo $is_preview ? ' disabled' : ''; ?>>
                    <?php echo esc_html($settings['button_text']); ?>
                    <?php if ($is_preview) : ?><small>(превʼю)</small><?php endif; ?>
                </button>

                <div class="cra-form-message" style="margin-top:12px; display:none;"></div>
            </form>
            <?php endif; ?>

            <div class="cra-success" style="display:none;">
                <?php echo esc_html($settings['success_message']); ?>
            </div>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('callback_request_form', 'cra_render_callback_form');

/**
 * Handle form submission (AJAX + classic POST fallback)
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_handle_form_submission() {
    // Security
    $nonce = isset( $_POST['cra_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cra_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'cra_submit_form' ) ) {
        wp_send_json_error( 'Невірний токен безпеки.' );
    }

    $settings = cra_get_settings();

    // reCAPTCHA verification for callback form
    if ( !empty($settings['enable_recaptcha']) && !empty($settings['enable_recaptcha_callback']) && !empty($settings['recaptcha_secret_key']) ) {
        $recaptcha_response = sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ?? '' ) );
        if ( empty($recaptcha_response) ) {
            wp_send_json_error( 'Будь ласка, підтвердіть, що ви не робот (reCAPTCHA).' );
        }
        $verify = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $settings['recaptcha_secret_key'],
                'response' => $recaptcha_response,
                'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
            ],
        ] );
        $verify_body = json_decode( wp_remote_retrieve_body( $verify ) );
        if ( empty( $verify_body->success ) ) {
            wp_send_json_error( 'Перевірка reCAPTCHA не пройдена. Спробуйте ще раз.' );
        }
    }

    $name          = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $contact_type  = sanitize_text_field(wp_unslash($_POST['contact_type'] ?? ''));
    $phone         = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
    $email         = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $messenger_type= sanitize_text_field(wp_unslash($_POST['messenger_type'] ?? ''));
    $messenger_val = sanitize_text_field(wp_unslash($_POST['messenger_value'] ?? ''));
    $message       = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

    // Determine contact value
    $contact_value = '';
    switch ($contact_type) {
        case 'phone':
            $contact_value = $phone;
            break;
        case 'email':
            $contact_value = $email;
            break;
        case 'messenger':
            $contact_value = $messenger_val;
            break;
        default:
            $contact_type = 'phone';
            $contact_value = $phone;
    }

    if (empty($contact_value)) {
        wp_send_json_error('Будь ласка, вкажіть контакт для зв\'язку.');
    }

    // Build nice title
    $title = 'Заявка на дзвінок';
    if ($name) {
        $title .= ' від ' . $name;
    } elseif ($contact_value) {
        $title .= ' — ' . $contact_value;
    }

    // Create the post (CPT entry)
    $post_id = wp_insert_post([
        'post_type'   => 'callback_request',
        'post_title'  => $title,
        'post_status' => 'publish',
        'post_content'=> $message,
    ], true);

    if (is_wp_error($post_id)) {
        wp_send_json_error('Не вдалося зберегти заявку. Спробуйте пізніше.');
    }

    // Store meta
    update_post_meta($post_id, 'cra_contact_type',   $contact_type);
    update_post_meta($post_id, 'cra_contact_value',  $contact_value);
    update_post_meta($post_id, 'cra_user_name',      $name);
    update_post_meta($post_id, 'cra_user_message',   $message);
    $client_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    update_post_meta( $post_id, 'cra_user_ip', $client_ip );

    if ($contact_type === 'messenger') {
        update_post_meta($post_id, 'cra_messenger_type', $messenger_type);
    }

    // Email notification
    if (!empty($settings['enable_email_notification']) && !empty($settings['admin_email'])) {
        $subject = 'Нова заявка на зворотний дзвінок — ' . get_bloginfo('name');

        $body  = "Отримано нову заявку на зворотний дзвінок:\n\n";
        if ($name)            $body .= "Ім'я: {$name}\n";
        $body .= "Тип контакту: " . ucfirst($contact_type) . "\n";
        $body .= "Контакт: {$contact_value}\n";
        if ($contact_type === 'messenger' && $messenger_type) {
            $mopts = cra_get_messenger_options();
            $body .= "Месенджер: " . ($mopts[$messenger_type] ?? $messenger_type) . "\n";
        }
        if ($message)         $body .= "\nПовідомлення:\n{$message}\n";
        $body .= "\nДата: " . current_time('mysql') . "\n";
        $body .= "IP: " . esc_html( $client_ip ?: 'unknown' ) . "\n";
        $body .= "\nПереглянути в адмінці: " . admin_url('edit.php?post_type=callback_request') . "\n";

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $email_content = '
            <p><strong>Отримано нову заявку на зворотний дзвінок:</strong></p>
            ' . ($name ? '<p><strong>Ім\'я:</strong> ' . esc_html($name) . '</p>' : '') . '
            <p><strong>Тип контакту:</strong> ' . esc_html(ucfirst($contact_type)) . '</p>
            <p><strong>Контакт:</strong> ' . esc_html($contact_value) . '</p>
            ' . ($contact_type === 'messenger' && $messenger_type ? '<p><strong>Месенджер:</strong> ' . esc_html(cra_get_messenger_options()[$messenger_type] ?? $messenger_type) . '</p>' : '') . '
            ' . ($message ? '<p><strong>Повідомлення:</strong><br>' . nl2br(esc_html($message)) . '</p>' : '') . '
            <p style="margin-top:20px;font-size:13px;color:#666;">Дата: ' . current_time('mysql') . ' | IP: ' . esc_html( $client_ip ) . '</p>
            <p><a href="' . esc_url(admin_url('edit.php?post_type=callback_request')) . '">Переглянути всі заявки →</a></p>
        ';

        $html_body = cra_get_styled_email_html('Нова заявка на дзвінок', $email_content, $settings);

        wp_mail($settings['admin_email'], $subject, $html_body, $headers);
    }

    // === Додаткові Email ===
    if (!empty($settings['additional_emails'])) {
        $extra_emails = preg_split('/[\r\n,]+/', $settings['additional_emails']);
        foreach ($extra_emails as $extra) {
            $extra = sanitize_email(trim($extra));
            if ($extra && is_email($extra)) {
                wp_mail($extra, $subject, $html_body, $headers);
            }
        }
    }

    // === Telegram ===
    if (!empty($settings['enable_telegram']) && !empty($settings['telegram_bot_token']) && !empty($settings['telegram_chat_id'])) {
        $token   = $settings['telegram_bot_token'];
        $chat_id = $settings['telegram_chat_id'];

        $tg_text  = "📞 <b>Нова заявка на зворотний дзвінок</b>\n\n";
        if ($name) {
            $tg_text .= "👤 <b>Ім'я:</b> " . esc_html($name) . "\n";
        }
        $tg_text .= "📱 <b>Тип:</b> " . ucfirst($contact_type) . "\n";
        $tg_text .= "☎️ <b>Контакт:</b> " . esc_html($contact_value) . "\n";

        if ($contact_type === 'messenger' && $messenger_type) {
            $mopts = cra_get_messenger_options();
            $tg_text .= "💬 <b>Месенджер:</b> " . ($mopts[$messenger_type] ?? $messenger_type) . "\n";
        }
        if ($message) {
            $tg_text .= "\n📝 <b>Повідомлення:</b>\n" . esc_html($message) . "\n";
        }
        $tg_text .= "\n🕒 " . current_time('mysql');

        $telegram_api = "https://api.telegram.org/bot{$token}/sendMessage";

        wp_remote_post($telegram_api, [
            'timeout' => 15,
            'body'    => [
                'chat_id'    => $chat_id,
                'text'       => $tg_text,
                'parse_mode' => 'HTML',
            ],
        ]);
    }

    // Success response for both AJAX and non-AJAX
    if (wp_doing_ajax()) {
        wp_send_json_success([
            'message' => $settings['success_message'],
            'post_id' => $post_id,
        ]);
    } else {
        // Classic POST fallback – redirect or show message (simple)
        wp_safe_redirect(add_query_arg('cra_submitted', '1', wp_get_referer() ?: home_url()));
        exit;
    }
}
add_action('wp_ajax_cra_submit_callback', 'cra_handle_form_submission');
add_action('wp_ajax_nopriv_cra_submit_callback', 'cra_handle_form_submission');

/**
 * Optional: show success message on classic redirect fallback
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_maybe_show_success_notice() {
    // This is only a redirect success flag after a prior (nonce-protected) form submission.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $submitted = isset( $_GET['cra_submitted'] ) ? sanitize_text_field( wp_unslash( $_GET['cra_submitted'] ) ) : '';
    if ( ! empty( $submitted ) ) {
        $settings = cra_get_settings();
        echo '<div class="cra-success-notice" style="max-width:520px;margin:20px auto;padding:16px 20px;background:#ecfdf5;border:1px solid #10b981;border-radius:12px;color:#065f46;font-weight:600;">'
            . esc_html( $settings['success_message'] ) .
            '</div>';
    }
}
add_action('wp_footer', 'cra_maybe_show_success_notice');

/**
 * ============================================
 * НОВИЙ КОД: Підписка на розсилку (Newsletter)
 * ============================================
 */

/**
 * Shortcode для форми підписки: [callback_subscribe_form]
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_render_subscribe_form($atts = []) {
    $atts = shortcode_atts([
        'preview' => '0',
        'admin_preview' => '0',
    ], $atts, 'callback_subscribe_form');

    $is_preview = !empty($atts['preview']) && $atts['preview'] !== '0';
    $is_admin_preview = !empty($atts['admin_preview']) && $atts['admin_preview'] !== '0';

    $settings = cra_get_settings();

    if (empty($settings['enable_subscribe'])) {
        return '';
    }

    // Окремий дизайн для форми підписки
    $css_vars = sprintf(
        '--cra-bg:%s;--cra-btn:%s;--cra-btn-text:%s;--cra-accent:%s;--cra-radius:%s;--cra-pad:%s;',
        esc_attr($settings['subscribe_form_background']),
        esc_attr($settings['subscribe_button_color']),
        esc_attr($settings['subscribe_button_text_color']),
        esc_attr($settings['subscribe_accent_color']),
        esc_attr($settings['subscribe_form_border_radius']),
        esc_attr($settings['subscribe_form_padding'])
    );

    $uid = 'csub-' . uniqid();

    // Підключаємо ті ж фронтенд стилі та скрипт (вони універсальні)
    cra_enqueue_frontend_assets();

    ob_start();
    ?>
    <div class="cra-form-wrap cra-subscribe-wrap" data-preview="<?php echo $is_preview ? '1' : '0'; ?>" style="<?php echo esc_attr( $css_vars ); ?>">
        <div class="cra-form">
            <h3><?php echo esc_html($settings['subscribe_title']); ?></h3>

            <?php if ( ! $is_admin_preview ) : ?>
            <form class="cra-subscribe-form" method="post" novalidate>
                <?php wp_nonce_field('cra_subscribe_form', 'cra_subscribe_nonce'); ?>
                <input type="hidden" name="action" value="cra_submit_subscribe">

                <?php if (!empty($settings['subscribe_require_name'])) : ?>
                    <div class="cra-field">
                        <label for="<?php echo esc_attr($uid); ?>-name">Ім'я</label>
                        <input type="text" id="<?php echo esc_attr($uid); ?>-name" name="name" placeholder="Ваше ім'я" required>
                    </div>
                <?php endif; ?>

                <div class="cra-field">
                    <label for="<?php echo esc_attr($uid); ?>-email">Email</label>
                    <input type="email" id="<?php echo esc_attr($uid); ?>-email" name="email" placeholder="you@example.com" required>
                </div>

                <?php if ( ! $is_admin_preview && !empty($settings['enable_recaptcha']) && !empty($settings['enable_recaptcha_subscribe']) && !empty($settings['recaptcha_site_key']) ) : ?>
                <div class="cra-field">
                    <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($settings['recaptcha_site_key']); ?>"></div>
                </div>
                <?php endif; ?>

                <button type="submit" class="cra-submit">
                    <?php echo esc_html($settings['subscribe_button_text']); ?>
                </button>

                <div class="cra-form-message" style="margin-top:12px; display:none;"></div>
            </form>
            <?php endif; ?>

            <div class="cra-success" style="display:none;">
                <?php echo esc_html($settings['subscribe_success_message']); ?>
            </div>
        </div>
    </div>

    <script>
    (function() {
        // Використовуємо ту ж логіку, що й основна форма, але для підписки
        document.querySelectorAll('.cra-subscribe-wrap').forEach(function(wrap) {
            var form = wrap.querySelector('.cra-subscribe-form');
            if (!form) return;

            var successBox = wrap.querySelector('.cra-success');
            var msgBox = wrap.querySelector('.cra-form-message');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (msgBox) {
                    msgBox.style.display = 'none';
                    msgBox.className = 'cra-form-message';
                }

                var submitBtn = form.querySelector('.cra-submit');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    var origText = submitBtn.textContent;
                    submitBtn.textContent = 'Надсилаємо...';
                }

                var formData = new FormData(form);

                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        form.style.display = 'none';
                        if (successBox) successBox.style.display = 'block';
                    } else {
                        var err = (data && data.data) ? data.data : 'Помилка. Спробуйте ще раз.';
                        if (msgBox) {
                            msgBox.textContent = err;
                            msgBox.classList.add('cra-error');
                            msgBox.style.display = 'block';
                        }
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = origText;
                        }
                    }
                })
                .catch(function() {
                    if (msgBox) {
                        msgBox.textContent = 'Помилка з\'єднання.';
                        msgBox.classList.add('cra-error');
                        msgBox.style.display = 'block';
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = origText;
                    }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('callback_subscribe_form', 'cra_render_subscribe_form');

/**
 * Обробник підписки на розсилку (AJAX)
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_handle_subscribe_submission() {
    $subscribe_nonce = isset( $_POST['cra_subscribe_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cra_subscribe_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $subscribe_nonce, 'cra_subscribe_form' ) ) {
        wp_send_json_error( 'Невірний токен безпеки.' );
    }

    $settings = cra_get_settings();

    // reCAPTCHA verification for subscribe form
    if ( !empty($settings['enable_recaptcha']) && !empty($settings['enable_recaptcha_subscribe']) && !empty($settings['recaptcha_secret_key']) ) {
        $recaptcha_response = sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ?? '' ) );
        if ( empty($recaptcha_response) ) {
            wp_send_json_error( 'Будь ласка, підтвердіть, що ви не робот (reCAPTCHA).' );
        }
        $verify = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $settings['recaptcha_secret_key'],
                'response' => $recaptcha_response,
                'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
            ],
        ] );
        $verify_body = json_decode( wp_remote_retrieve_body( $verify ) );
        if ( empty( $verify_body->success ) ) {
            wp_send_json_error( 'Перевірка reCAPTCHA не пройдена. Спробуйте ще раз.' );
        }
    }

    $name  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));

    if (empty($email) || !is_email($email)) {
        wp_send_json_error('Будь ласка, введіть коректний email.');
    }

    // Перевіряємо, чи вже підписаний (simple existence check for subscriber).
    // Using get_posts with optimized args. The meta_query slow query warning is suppressed
    // because this is a lightweight duplicate prevention check on form submission.
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    $existing = get_posts( [
        'post_type'      => 'callback_subscriber',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            [
                'key'   => 'subscriber_email',
                'value' => $email,
            ],
        ],
    ] );

    if (!empty($existing)) {
        wp_send_json_success(['message' => 'Ви вже підписані на розсилку.']);
    }

    // Створюємо запис підписника
    $post_id = wp_insert_post([
        'post_type'   => 'callback_subscriber',
        'post_title'  => $email,
        'post_status' => 'publish',
    ]);

    if (is_wp_error($post_id)) {
        wp_send_json_error('Не вдалося зберегти підписку.');
    }

    update_post_meta($post_id, 'subscriber_email', $email);
    update_post_meta($post_id, 'subscriber_name', $name);
    update_post_meta($post_id, 'subscriber_status', 'subscribed');
    update_post_meta($post_id, 'subscriber_date', current_time('mysql'));
    update_post_meta($post_id, 'subscriber_source', 'form');

    // Надсилаємо вітальний / підтверджувальний email
    cra_send_subscriber_welcome_email($email, $name, $settings);

    wp_send_json_success([
        'message' => $settings['subscribe_success_message'],
    ]);
}
add_action('wp_ajax_cra_submit_subscribe', 'cra_handle_subscribe_submission');
add_action('wp_ajax_nopriv_cra_submit_subscribe', 'cra_handle_subscribe_submission');

/**
 * Відправка вітального листа підписнику (з HTML дизайном)
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_send_subscriber_welcome_email($email, $name, $settings) {
    $subject = 'Дякуємо за підписку на розсилку — ' . get_bloginfo('name');

    $logo = !empty($settings['email_logo_url']) ? '<img src="' . esc_url($settings['email_logo_url']) . '" alt="Logo" style="max-height:50px;margin-bottom:15px;">' : '';

    $html = '
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f8fafc;padding:20px;">
        <div style="background:' . esc_attr($settings['email_header_color']) . ';color:#fff;padding:25px 30px;border-radius:12px 12px 0 0;">
            ' . $logo . '
            <h1 style="margin:0;font-size:22px;">' . esc_html($settings['subscribe_title']) . '</h1>
        </div>
        <div style="background:#fff;padding:30px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;">
            <p>Вітаємо' . ($name ? ', ' . esc_html($name) : '') . '!</p>
            <p>Дякуємо, що підписалися на нашу розсилку. Тепер ви будете першими дізнаватися про новини, акції та корисні матеріали.</p>
            ' . ($settings['subscribe_double_optin'] ? '<p><strong>Будь ласка, підтвердіть підписку, перейшовши за посиланням у наступному листі (якщо потрібно).</strong></p>' : '') . '
            <p style="margin-top:25px;color:#6b7280;font-size:13px;">' . esc_html($settings['email_footer_text']) . '</p>
        </div>
    </div>';

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // Використовуємо wp_mail (який вже може бути налаштований через SMTP)
    wp_mail($email, $subject, $html, $headers);
}

/**
 * Допоміжна функція для генерації HTML email (можна розширювати)
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_add_recaptcha_async_defer( $tag, $handle ) {
    if ( 'google-recaptcha' === $handle ) {
        return str_replace( ' src', ' async defer src', $tag );
    }
    return $tag;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_get_styled_email_html($title, $content_html, $settings = null) {
    if (!$settings) $settings = cra_get_settings();

    $logo = !empty($settings['email_logo_url']) ? '<img src="' . esc_url($settings['email_logo_url']) . '" style="max-height:45px;margin-bottom:10px;">' : '';

    return '
    <div style="font-family: system-ui, Arial, sans-serif; max-width: 620px; margin: 0 auto; background: #f8fafc; padding: 20px;">
        <div style="background:' . esc_attr($settings['email_header_color']) . '; color: #fff; padding: 20px 25px; border-radius: 10px 10px 0 0;">
            ' . $logo . '
            <h2 style="margin:0; font-size:20px;">' . esc_html($title) . '</h2>
        </div>
        <div style="background:#ffffff; padding:25px 30px; border:1px solid #e5e7eb; border-top:none; border-radius:0 0 10px 10px; color:#111827; line-height:1.6;">
            ' . $content_html . '
            <div style="margin-top:30px; padding-top:15px; border-top:1px solid #eee; font-size:13px; color:#6b7280;">
                ' . esc_html($settings['email_footer_text']) . '
            </div>
        </div>
    </div>';
}
