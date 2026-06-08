<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Недостатньо прав доступу.', 'callback-request-admin'));
    }

    $settings = cra_get_settings();
    $test_result = null;

    // Обробка тестового email
    if (isset($_POST['cra_send_test_email'])) {
        $test_nonce = isset($_POST['cra_test_nonce']) ? sanitize_text_field( wp_unslash( $_POST['cra_test_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $test_nonce, 'cra_test_email' ) ) {
            wp_die(esc_html__('Помилка безпеки.', 'callback-request-admin'));
        }

        $test_recipient = sanitize_email(wp_unslash($_POST['test_recipient'] ?? ''));
        if (!$test_recipient) {
            $test_result = ['success' => false, 'message' => 'Вкажіть коректний email отримувача.'];
        } else {
            $subject = 'Тестовий лист від Callback Request Admin — ' . get_bloginfo('name');
            $content = '<p>Привіт! Це тестовий лист.</p><p>Якщо ви його отримали — налаштування відправки працюють коректно.</p>';
            $html_body = cra_get_styled_email_html('Тест відправки', $content);

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($test_recipient, $subject, $html_body, $headers);

            if ($sent) {
                $test_result = ['success' => true, 'message' => 'Тестовий лист успішно надіслано на ' . $test_recipient . '. Перевірте пошту (включаючи спам).'];
                delete_option('cra_last_mail_error'); // очищаємо попередню помилку
            } else {
                $last_error = get_option('cra_last_mail_error', 'Невідома помилка (дивіться логи сервера або SMTP налаштування).');
                $test_result = ['success' => false, 'message' => 'Не вдалося надіслати тестовий лист. Помилка: ' . $last_error];
            }
        }
    }

    // Обробка повідомлення розробнику
    $contact_result = null;
    if (isset($_POST['send_to_developer'])) {
        $contact_nonce = isset($_POST['cra_contact_nonce']) ? sanitize_text_field( wp_unslash( $_POST['cra_contact_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $contact_nonce, 'cra_contact_developer' ) ) {
            wp_die(esc_html__('Помилка безпеки.', 'callback-request-admin'));
        }

        $contact_name    = sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) );
        $contact_email   = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
        $contact_subject = sanitize_text_field( wp_unslash( $_POST['contact_subject'] ?? '' ) );
        $contact_message = sanitize_textarea_field( wp_unslash( $_POST['contact_message'] ?? '' ) );

        if ( !$contact_name || !$contact_email || !$contact_subject || !$contact_message ) {
            $contact_result = ['success' => false, 'message' => 'Будь ласка, заповніть усі поля.'];
        } else {
            $to      = 'email@bilohash.com';
            $subj    = '[Callback Request Admin] ' . $contact_subject;
            $body    = "Від: {$contact_name} <{$contact_email}>\n\n{$contact_message}";
            $headers = [ 'From: ' . $contact_name . ' <' . $contact_email . '>' ];

            if ( wp_mail( $to, $subj, $body, $headers ) ) {
                $contact_result = ['success' => true, 'message' => 'Дякуємо! Ваше повідомлення надіслано розробнику.'];
            } else {
                $contact_result = ['success' => false, 'message' => 'Не вдалося надіслати повідомлення. Спробуйте пізніше або напишіть напряму на email@bilohash.com.'];
            }
        }
    }

    if (isset($_POST['cra_save_settings'])) {
        check_admin_referer('cra_save_settings');

        $new_settings = [
            'form_title'                => sanitize_text_field(wp_unslash($_POST['form_title'] ?? '')),
            'button_text'               => sanitize_text_field(wp_unslash($_POST['button_text'] ?? '')),
            'success_message'           => sanitize_textarea_field(wp_unslash($_POST['success_message'] ?? '')),
            'admin_email'               => sanitize_email(wp_unslash($_POST['admin_email'] ?? '')),
            'enable_phone'              => !empty($_POST['enable_phone']),
            'enable_email'              => !empty($_POST['enable_email']),
            'enable_messenger'          => !empty($_POST['enable_messenger']),
            'form_background'           => sanitize_hex_color(wp_unslash($_POST['form_background'] ?? '#ffffff')),
            'button_color'              => sanitize_hex_color(wp_unslash($_POST['button_color'] ?? '#2563eb')),
            'button_text_color'         => sanitize_hex_color(wp_unslash($_POST['button_text_color'] ?? '#ffffff')),
            'accent_color'              => sanitize_hex_color(wp_unslash($_POST['accent_color'] ?? '#2563eb')),
            'form_border_radius'        => sanitize_text_field(wp_unslash($_POST['form_border_radius'] ?? '16px')),
            'form_padding'              => sanitize_text_field(wp_unslash($_POST['form_padding'] ?? '32px')),
            'enable_email_notification' => !empty($_POST['enable_email_notification']),
            'additional_emails'         => sanitize_textarea_field(wp_unslash($_POST['additional_emails'] ?? '')),
            'enable_telegram'           => !empty($_POST['enable_telegram']),
            'telegram_bot_token'        => sanitize_text_field(wp_unslash($_POST['telegram_bot_token'] ?? '')),
            'telegram_chat_id'          => sanitize_text_field(wp_unslash($_POST['telegram_chat_id'] ?? '')),

            // SMTP
            'enable_smtp'               => !empty($_POST['enable_smtp']),
            'smtp_host'                 => sanitize_text_field(wp_unslash($_POST['smtp_host'] ?? '')),
            'smtp_port'                 => sanitize_text_field(wp_unslash($_POST['smtp_port'] ?? '587')),
            'smtp_encryption'           => sanitize_text_field(wp_unslash($_POST['smtp_encryption'] ?? 'tls')),
            'smtp_username'             => sanitize_text_field(wp_unslash($_POST['smtp_username'] ?? '')),
            'smtp_password'             => sanitize_text_field(wp_unslash($_POST['smtp_password'] ?? '')),
            'smtp_from_name'            => sanitize_text_field(wp_unslash($_POST['smtp_from_name'] ?? '')),
            'smtp_from_email'           => sanitize_email(wp_unslash($_POST['smtp_from_email'] ?? '')),

            // IMAP
            'enable_imap'               => !empty($_POST['enable_imap']),
            'imap_host'                 => sanitize_text_field(wp_unslash($_POST['imap_host'] ?? '')),
            'imap_port'                 => sanitize_text_field(wp_unslash($_POST['imap_port'] ?? '993')),
            'imap_encryption'           => sanitize_text_field(wp_unslash($_POST['imap_encryption'] ?? 'ssl')),
            'imap_username'             => sanitize_text_field(wp_unslash($_POST['imap_username'] ?? '')),
            'imap_password'             => sanitize_text_field(wp_unslash($_POST['imap_password'] ?? '')),

            // Email design
            'email_header_color'        => sanitize_hex_color(wp_unslash($_POST['email_header_color'] ?? '#2563eb')),
            'email_accent_color'        => sanitize_hex_color(wp_unslash($_POST['email_accent_color'] ?? '#2563eb')),
            'email_logo_url'            => esc_url_raw(wp_unslash($_POST['email_logo_url'] ?? '')),
            'email_footer_text'         => sanitize_text_field(wp_unslash($_POST['email_footer_text'] ?? '')),

            // Subscribe / Newsletter
            'enable_subscribe'          => !empty($_POST['enable_subscribe']),
            'subscribe_title'           => sanitize_text_field(wp_unslash($_POST['subscribe_title'] ?? '')),
            'subscribe_button_text'     => sanitize_text_field(wp_unslash($_POST['subscribe_button_text'] ?? '')),
            'subscribe_success_message' => sanitize_textarea_field(wp_unslash($_POST['subscribe_success_message'] ?? '')),
            'subscribe_require_name'    => !empty($_POST['subscribe_require_name']),
            'subscribe_double_optin'    => !empty($_POST['subscribe_double_optin']),

            // Окремий дизайн для форми підписки
            'subscribe_form_background'      => sanitize_hex_color(wp_unslash($_POST['subscribe_form_background'] ?? '#f0fdf4')),
            'subscribe_button_color'         => sanitize_hex_color(wp_unslash($_POST['subscribe_button_color'] ?? '#16a34a')),
            'subscribe_button_text_color'    => sanitize_hex_color(wp_unslash($_POST['subscribe_button_text_color'] ?? '#ffffff')),
            'subscribe_accent_color'         => sanitize_hex_color(wp_unslash($_POST['subscribe_accent_color'] ?? '#16a34a')),
            'subscribe_form_border_radius'   => sanitize_text_field(wp_unslash($_POST['subscribe_form_border_radius'] ?? '12px')),
            'subscribe_form_padding'         => sanitize_text_field(wp_unslash($_POST['subscribe_form_padding'] ?? '24px')),

            // reCAPTCHA
            'enable_recaptcha'             => !empty($_POST['enable_recaptcha']),
            'recaptcha_site_key'           => sanitize_text_field(wp_unslash($_POST['recaptcha_site_key'] ?? '')),
            'recaptcha_secret_key'         => sanitize_text_field(wp_unslash($_POST['recaptcha_secret_key'] ?? '')),
            'enable_recaptcha_callback'    => !empty($_POST['enable_recaptcha_callback']),
            'enable_recaptcha_subscribe'   => !empty($_POST['enable_recaptcha_subscribe']),
        ];

        update_option('cra_settings', $new_settings);
        $settings = $new_settings;

        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Налаштування збережено!</strong></p></div>';
    }
    ?>
    <div class="wrap cra-admin-wrap">

        <!-- Красива шапка -->
        <div class="cra-header">
            <div class="cra-header-content">
                <div class="cra-header-main">
                    <span class="cra-header-icon">📞</span>
                    <div>
                        <h1>Callback Request Admin</h1>
                        <p class="cra-header-subtitle">Професійна форма зворотного дзвінка з інтеграціями</p>
                    </div>
                </div>
                <div class="cra-header-meta">
                    <span class="cra-version">v<?php echo esc_html(CRA_VERSION); ?></span>
                    <a href="https://bilohash.com/donate.php" target="_blank" class="button button-secondary cra-donate-btn">❤️ Донат</a>
                </div>
            </div>
        </div>

        <p class="cra-page-subtitle" style="margin-top:8px;">Налаштування форми зворотного зв’язку</p>

        <form method="post">
            <?php wp_nonce_field('cra_save_settings'); ?>

            <div class="cra-nav-tab-wrapper">
                <a href="#" class="cra-nav-tab cra-nav-tab-active" data-tab="general">Загальні</a>
                <a href="#" class="cra-nav-tab" data-tab="design">Дизайн</a>
                <a href="#" class="cra-nav-tab" data-tab="notifications">Сповіщення</a>
                <a href="#" class="cra-nav-tab" data-tab="integrations">Інтеграції</a>
                <a href="#" class="cra-nav-tab" data-tab="email">Пошта (SMTP)</a>
                <a href="#" class="cra-nav-tab" data-tab="subscribe">Підписка</a>
                <a href="#" class="cra-nav-tab" data-tab="recaptcha">reCAPTCHA</a>
                <a href="#" class="cra-nav-tab" data-tab="links">Посилання</a>
            </div>

            <!-- Загальні -->
            <div id="tab-general" class="cra-tab-content">
                <div class="cra-card">
                    <h2>Загальні налаштування</h2>
                    <table class="form-table">
                        <tr>
                            <th>Заголовок форми</th>
                            <td><input type="text" name="form_title" value="<?php echo esc_attr($settings['form_title']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Текст кнопки</th>
                            <td><input type="text" name="button_text" value="<?php echo esc_attr($settings['button_text']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Повідомлення про успіх</th>
                            <td><textarea name="success_message" rows="3" class="large-text"><?php echo esc_textarea($settings['success_message']); ?></textarea></td>
                        </tr>
                        <tr>
                            <th>Доступні типи контактів</th>
                            <td>
                                <label><input type="checkbox" name="enable_phone" <?php checked($settings['enable_phone']); ?>> Телефон</label><br>
                                <label><input type="checkbox" name="enable_email" <?php checked($settings['enable_email']); ?>> Email</label><br>
                                <label><input type="checkbox" name="enable_messenger" <?php checked($settings['enable_messenger']); ?>> Messenger</label>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Дизайн -->
            <div id="tab-design" class="cra-tab-content" style="display:none;">
                <div class="cra-card">
                    <h2>Налаштування дизайну</h2>
                    <table class="form-table">
                        <tr>
                            <th>Колір фону форми</th>
                            <td><input type="color" name="form_background" value="<?php echo esc_attr($settings['form_background']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Колір кнопки</th>
                            <td><input type="color" name="button_color" value="<?php echo esc_attr($settings['button_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Колір тексту кнопки</th>
                            <td><input type="color" name="button_text_color" value="<?php echo esc_attr($settings['button_text_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Акцентний колір</th>
                            <td><input type="color" name="accent_color" value="<?php echo esc_attr($settings['accent_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Радіус кутів</th>
                            <td><input type="text" name="form_border_radius" value="<?php echo esc_attr($settings['form_border_radius']); ?>" style="width:100px;"></td>
                        </tr>
                        <tr>
                            <th>Внутрішні відступи</th>
                            <td><input type="text" name="form_padding" value="<?php echo esc_attr($settings['form_padding']); ?>" style="width:100px;"></td>
                        </tr>
                    </table>
                </div>

                <div class="cra-card">
                    <h2>Превʼю форми передзвонити</h2>
                    <div style="max-width:560px; margin: 0 auto; padding: 20px 0;">
                        <?php
                            // Make sure frontend assets are loaded for the live preview
                            if (function_exists('cra_enqueue_frontend_assets')) {
                                cra_enqueue_frontend_assets();
                            }
                            echo do_shortcode('[callback_request_form preview="1" admin_preview="1"]');
                        ?>
                    </div>
                    <p style="text-align:center; color:#6b7280; font-size:12px; margin-top:8px;">
                        Зміни кольорів, радіусу та відступів одразу видно в цьому превʼю (внизу вкладки). Збережіть налаштування, щоб побачити оновлення.
                    </p>
                </div>
            </div>

            <!-- Сповіщення -->
            <div id="tab-notifications" class="cra-tab-content" style="display:none;">
                <div class="cra-card">
                    <h2>Сповіщення</h2>
                    <table class="form-table">
                        <tr>
                            <th>Email для сповіщень</th>
                            <td><input type="email" name="admin_email" value="<?php echo esc_attr($settings['admin_email']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Надсилати email при новій заявці</th>
                            <td><label><input type="checkbox" name="enable_email_notification" <?php checked($settings['enable_email_notification']); ?>> Так</label></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Інтеграції -->
            <div id="tab-integrations" class="cra-tab-content" style="display:none;">
                <div class="cra-card">
                    <h2>Додаткові Email</h2>
                    <table class="form-table">
                        <tr>
                            <th>Додаткові адреси для сповіщень</th>
                            <td>
                                <textarea name="additional_emails" rows="4" class="large-text" placeholder="email1@example.com&#10;email2@example.com"><?php echo esc_textarea($settings['additional_emails']); ?></textarea>
                                <p class="description">Вкажіть додаткові email (по одному на рядок). На них теж будуть надходити сповіщення про нові заявки.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="cra-card">
                    <h2>Telegram Bot</h2>
                    <table class="form-table">
                        <tr>
                            <th>Увімкнути сповіщення в Telegram</th>
                            <td><label><input type="checkbox" name="enable_telegram" <?php checked($settings['enable_telegram']); ?>> Так</label></td>
                        </tr>
                        <tr>
                            <th>Telegram Bot Token</th>
                            <td>
                                <input type="text" name="telegram_bot_token" value="<?php echo esc_attr($settings['telegram_bot_token']); ?>" class="regular-text" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11">
                                <p class="description">Отримайте токен у @BotFather в Telegram.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Chat ID / Channel ID</th>
                            <td>
                                <input type="text" name="telegram_chat_id" value="<?php echo esc_attr($settings['telegram_chat_id']); ?>" class="regular-text" placeholder="-1001234567890 або @yourchannel">
                                <p class="description">Для каналу/групи використовуйте від'ємний ID. Для особистого чату — ваш ID (можна дізнатися у @userinfobot).</p>
                            </td>
                        </tr>
                    </table>
                    <p style="margin-top:10px;">
                        <a href="https://core.telegram.org/bots#6-botfather" target="_blank" rel="noopener">Як створити Telegram бота →</a>
                    </p>
                </div>
            </div>

            <!-- Пошта (SMTP + Email дизайн) -->
            <div id="tab-email" class="cra-tab-content" style="display:none;">
                <div class="cra-card">
                    <h2>SMTP — Надійна відправка email</h2>
                    <p class="description" style="margin-bottom:15px;">
                        За замовчуванням використовується функція сервера (wp_mail / PHP mailer). 
                        Якщо листи не доходять або потрапляють у спам — увімкніть SMTP нижче та вкажіть дані вашого поштового сервера (Gmail, Mailgun, SendGrid тощо).
                    </p>
                    <table class="form-table">
                        <tr>
                            <th>Увімкнути SMTP</th>
                            <td><label><input type="checkbox" name="enable_smtp" <?php checked($settings['enable_smtp']); ?>> Так</label></td>
                        </tr>
                        <tr>
                            <th>SMTP Host</th>
                            <td><input type="text" name="smtp_host" value="<?php echo esc_attr($settings['smtp_host']); ?>" class="regular-text" placeholder="smtp.gmail.com"></td>
                        </tr>
                        <tr>
                            <th>Порт</th>
                            <td><input type="text" name="smtp_port" value="<?php echo esc_attr($settings['smtp_port']); ?>" style="width:80px;"></td>
                        </tr>
                        <tr>
                            <th>Шифрування</th>
                            <td>
                                <select name="smtp_encryption">
                                    <option value="tls" <?php selected($settings['smtp_encryption'], 'tls'); ?>>TLS (рекомендовано)</option>
                                    <option value="ssl" <?php selected($settings['smtp_encryption'], 'ssl'); ?>>SSL</option>
                                    <option value="none" <?php selected($settings['smtp_encryption'], 'none'); ?>>Без шифрування</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Логін (Username)</th>
                            <td><input type="text" name="smtp_username" value="<?php echo esc_attr($settings['smtp_username']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Пароль (App Password)</th>
                            <td><input type="password" name="smtp_password" value="<?php echo esc_attr($settings['smtp_password']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Від кого (From Name)</th>
                            <td><input type="text" name="smtp_from_name" value="<?php echo esc_attr($settings['smtp_from_name']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Від кого (From Email)</th>
                            <td><input type="email" name="smtp_from_email" value="<?php echo esc_attr($settings['smtp_from_email']); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                </div>

                <div class="cra-card">
                    <h2>IMAP (опціонально)</h2>
                    <p class="description">Використовується для майбутніх функцій (наприклад, обробка відповідей на заявки).</p>
                    <table class="form-table">
                        <tr>
                            <th>Увімкнути IMAP</th>
                            <td><label><input type="checkbox" name="enable_imap" <?php checked($settings['enable_imap']); ?>> Так</label></td>
                        </tr>
                        <tr>
                            <th>IMAP Host</th>
                            <td><input type="text" name="imap_host" value="<?php echo esc_attr($settings['imap_host']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Порт</th>
                            <td><input type="text" name="imap_port" value="<?php echo esc_attr($settings['imap_port']); ?>" style="width:80px;"></td>
                        </tr>
                        <tr>
                            <th>Шифрування</th>
                            <td>
                                <select name="imap_encryption">
                                    <option value="ssl" <?php selected($settings['imap_encryption'], 'ssl'); ?>>SSL</option>
                                    <option value="tls" <?php selected($settings['imap_encryption'], 'tls'); ?>>TLS</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Логін</th>
                            <td><input type="text" name="imap_username" value="<?php echo esc_attr($settings['imap_username']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Пароль</th>
                            <td><input type="password" name="imap_password" value="<?php echo esc_attr($settings['imap_password']); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                </div>

                <div class="cra-card">
                    <h2>Дизайн email-листів</h2>
                    <table class="form-table">
                        <tr>
                            <th>Колір шапки листа</th>
                            <td><input type="color" name="email_header_color" value="<?php echo esc_attr($settings['email_header_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Акцентний колір</th>
                            <td><input type="color" name="email_accent_color" value="<?php echo esc_attr($settings['email_accent_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th>URL логотипу в листі</th>
                            <td><input type="url" name="email_logo_url" value="<?php echo esc_attr($settings['email_logo_url']); ?>" class="regular-text" placeholder="https://.../logo.png"></td>
                        </tr>
                        <tr>
                            <th>Текст у футері листа</th>
                            <td><input type="text" name="email_footer_text" value="<?php echo esc_attr($settings['email_footer_text']); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                    <p class="description" style="margin-top:12px;">Ці параметри використовуються при генерації HTML-версії сповіщень про заявки та підтвердження підписки.</p>
                </div>

                <div class="cra-card">
                    <h2>Тест відправки email</h2>
                    <p>Використовуйте цю функцію, щоб перевірити, чи доходять листи. Буде надіслано красивий HTML-лист з використанням поточних налаштувань (SMTP або стандартна відправка).</p>
                    <table class="form-table">
                        <tr>
                            <th>Email для тесту</th>
                            <td>
                                <?php
                                    $test_recipient_value = isset($_POST['test_recipient']) 
                                        ? sanitize_email(wp_unslash($_POST['test_recipient'])) 
                                        : $settings['admin_email'];
                                ?>
                                <input type="email" name="test_recipient" value="<?php echo esc_attr($test_recipient_value); ?>" class="regular-text">
                                <p class="description">Зазвичай це ваш email або email адміністратора.</p>
                            </td>
                        </tr>
                    </table>

                    <?php wp_nonce_field('cra_test_email', 'cra_test_nonce'); ?>
                    <p class="submit" style="margin: 15px 0 5px;">
                        <input type="submit" name="cra_send_test_email" class="button button-secondary" value="Надіслати тестовий лист">
                    </p>

                    <?php if ($test_result): ?>
                        <div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?> inline" style="margin-top:10px;">
                            <p><strong><?php echo esc_html($test_result['message']); ?></strong></p>
                        </div>
                    <?php endif; ?>

                    <?php 
                    $last_error = get_option('cra_last_mail_error');
                    if ($last_error): 
                    ?>
                        <div class="notice notice-warning inline" style="margin-top:10px;">
                            <p><strong>Остання помилка відправки:</strong> <?php echo esc_html($last_error); ?></p>
                            <p class="description">Ця помилка буде автоматично очищена після успішного тестового листа.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Підписка на розсилку -->
            <div id="tab-subscribe" class="cra-tab-content" style="display:none;">
                <div class="cra-card">
                    <h2>Налаштування форми підписки</h2>
                    <table class="form-table">
                        <tr>
                            <th>Увімкнути форму підписки</th>
                            <td><label><input type="checkbox" name="enable_subscribe" <?php checked($settings['enable_subscribe']); ?>> Так</label></td>
                        </tr>
                        <tr>
                            <th>Заголовок форми</th>
                            <td><input type="text" name="subscribe_title" value="<?php echo esc_attr($settings['subscribe_title']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Текст кнопки</th>
                            <td><input type="text" name="subscribe_button_text" value="<?php echo esc_attr($settings['subscribe_button_text']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Повідомлення про успіх</th>
                            <td><textarea name="subscribe_success_message" rows="2" class="large-text"><?php echo esc_textarea($settings['subscribe_success_message']); ?></textarea></td>
                        </tr>
                        <tr>
                            <th>Вимагати ім'я</th>
                            <td><label><input type="checkbox" name="subscribe_require_name" <?php checked($settings['subscribe_require_name']); ?>> Так</label></td>
                        </tr>
                        <tr>
                            <th>Подвійне підтвердження (Double Opt-in)</th>
                            <td><label><input type="checkbox" name="subscribe_double_optin" <?php checked($settings['subscribe_double_optin']); ?>> Так</label></td>
                        </tr>
                    </table>
                </div>

                <div class="cra-card">
                    <h2>Дизайн форми підписки</h2>
                    <p class="description">Тепер форма підписки має власні налаштування дизайну (окремо від основної форми).</p>
                    <table class="form-table">
                        <tr>
                            <th>Колір фону форми</th>
                            <td><input type="color" name="subscribe_form_background" value="<?php echo esc_attr($settings['subscribe_form_background']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Колір кнопки</th>
                            <td><input type="color" name="subscribe_button_color" value="<?php echo esc_attr($settings['subscribe_button_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Колір тексту кнопки</th>
                            <td><input type="color" name="subscribe_button_text_color" value="<?php echo esc_attr($settings['subscribe_button_text_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Акцентний колір</th>
                            <td><input type="color" name="subscribe_accent_color" value="<?php echo esc_attr($settings['subscribe_accent_color']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Радіус кутів</th>
                            <td><input type="text" name="subscribe_form_border_radius" value="<?php echo esc_attr($settings['subscribe_form_border_radius']); ?>" style="width:100px;"></td>
                        </tr>
                        <tr>
                            <th>Внутрішні відступи</th>
                            <td><input type="text" name="subscribe_form_padding" value="<?php echo esc_attr($settings['subscribe_form_padding']); ?>" style="width:100px;"></td>
                        </tr>
                    </table>
                    <p>Шорткод для вставки: <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;">[callback_subscribe_form]</code></p>
                </div>

                <div class="cra-card">
                    <h2>Превʼю форми підписки</h2>
                    <div style="max-width:560px; margin: 0 auto; padding: 20px 0;">
                        <?php
                            // Make sure frontend assets are loaded for the live preview
                            if (function_exists('cra_enqueue_frontend_assets')) {
                                cra_enqueue_frontend_assets();
                            }
                            echo do_shortcode('[callback_subscribe_form preview="1" admin_preview="1"]');
                        ?>
                    </div>
                    <p style="text-align:center; color:#6b7280; font-size:12px; margin-top:8px;">
                        Це превʼю використовує налаштування дизайну з вкладки «Дизайн». Збережіть налаштування, щоб побачити оновлення.
                    </p>
                </div>

                <div class="cra-card">
                    <h2>Підписники</h2>
                    <p>Всі підписники зберігаються у розділі <strong>Callback Requests → Subscribers</strong> (з'явиться після активації).</p>
                    <p><a href="<?php echo esc_url(admin_url('edit.php?post_type=callback_subscriber')); ?>" class="button">Переглянути підписників</a></p>
                </div>
            </div>

            <!-- reCAPTCHA -->
            <div id="tab-recaptcha" class="cra-tab-content" style="display:none;">
                <div class="cra-card">
                    <h2>reCAPTCHA</h2>
                    <p>Захистіть форми від спаму та ботів за допомогою Google reCAPTCHA v2. Рекомендується для публічних форм.</p>
                    <table class="form-table">
                        <tr>
                            <th>Увімкнути reCAPTCHA</th>
                            <td><label><input type="checkbox" name="enable_recaptcha" <?php checked($settings['enable_recaptcha']); ?>> Так</label></td>
                        </tr>
                        <tr>
                            <th>Site Key (публічний ключ)</th>
                            <td>
                                <input type="text" name="recaptcha_site_key" value="<?php echo esc_attr($settings['recaptcha_site_key']); ?>" class="regular-text">
                                <p class="description">Отримайте ключі на <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a> (тип v2 "I'm not a robot").</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Secret Key (секретний ключ)</th>
                            <td><input type="text" name="recaptcha_secret_key" value="<?php echo esc_attr($settings['recaptcha_secret_key']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Використовувати в формі зворотного дзвінка</th>
                            <td><label><input type="checkbox" name="enable_recaptcha_callback" <?php checked($settings['enable_recaptcha_callback']); ?>> Так</label></td>
                        </tr>
                        <tr>
                            <th>Використовувати в формі підписки</th>
                            <td><label><input type="checkbox" name="enable_recaptcha_subscribe" <?php checked($settings['enable_recaptcha_subscribe']); ?>> Так</label></td>
                        </tr>
                    </table>
                </div>

                <div class="cra-card">
                    <h2>Як це працює</h2>
                    <ul style="margin-left: 20px; line-height: 1.8;">
                        <li>Створіть ключі на Google reCAPTCHA (рекомендовано v2 Checkbox).</li>
                        <li>Вставте Site Key та Secret Key вище.</li>
                        <li>Увімкніть для потрібних форм (зворотний дзвінок та/або підписка).</li>
                        <li>На фронтенді з'явиться віджет reCAPTCHA.</li>
                        <li>При відправці форми токен перевіряється на сервері.</li>
                    </ul>
                    <p class="description">Якщо ключі не вказані або опція вимкнена — reCAPTCHA не показується і не перевіряється.</p>
                </div>
            </div>

            <!-- Посилання та підтримка -->
            <div id="tab-links" class="cra-tab-content" style="display:none;">
                <div class="cra-card">
                    <h2>Інші плагіни від Bilohash</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-top: 15px;">
                        <div style="border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #f8fafc;">
                            <strong style="font-size: 15px;">Bilohash AI Chat Consultant</strong>
                            <p style="margin: 8px 0; color: #4b5563; font-size: 13px;">Розумний AI-чат з Grok (xAI) та OpenAI GPT + Telegram сповіщення.</p>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="https://wordpress.org/plugins/bilohash-ai-chat-consultant/" target="_blank" class="button button-secondary" style="font-size:12px; padding: 4px 10px;">Переглянути на WP.org</a>
                                <a href="https://wordpress.org/plugins/bilohash-ai-chat-consultant/reviews/" target="_blank" class="button button-secondary" style="font-size:12px; padding: 4px 10px;">Відгуки</a>
                            </div>
                        </div>
                        <div style="border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #f8fafc;">
                            <strong style="font-size: 15px;">Bilohash Smart Popups</strong>
                            <p style="margin: 8px 0; color: #4b5563; font-size: 13px;">Гнучкі спливаючі вікна, банери та повідомлення з потужними умовами показу.</p>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="https://wordpress.org/plugins/bilohash-smart-popups/" target="_blank" class="button button-secondary" style="font-size:12px; padding: 4px 10px;">Переглянути на WP.org</a>
                                <a href="https://wordpress.org/plugins/bilohash-smart-popups/reviews/" target="_blank" class="button button-secondary" style="font-size:12px; padding: 4px 10px;">Відгуки</a>
                            </div>
                        </div>
                    </div>
                    <p style="margin-top: 16px; margin-bottom: 0;">
                        <a href="https://bilohash.com/donate.php" target="_blank" class="button button-secondary">❤️ Підтримати автора (Donation)</a>
                    </p>
                </div>

                <div class="cra-card">
                    <h2>Написати розробнику</h2>
                    <p>Маєте питання, пропозицію чи знайшли баг? Напишіть мені напряму.</p>
                    <?php wp_nonce_field('cra_contact_developer', 'cra_contact_nonce'); ?>
                    <table class="form-table" style="margin-bottom: 0; margin-top: 12px;">
                        <tr>
                            <th>Ваше ім'я</th>
                            <td><input type="text" name="contact_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><input type="email" name="contact_email" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th>Тема</th>
                            <td><input type="text" name="contact_subject" class="regular-text" value="Питання щодо Callback Request Admin" required></td>
                        </tr>
                        <tr>
                            <th>Повідомлення</th>
                            <td><textarea name="contact_message" rows="5" class="large-text" required></textarea></td>
                        </tr>
                    </table>
                    <p class="submit" style="margin-top: 10px;">
                        <input type="submit" name="send_to_developer" class="button button-primary" value="Надіслати повідомлення">
                    </p>

                    <?php if (isset($contact_result)): ?>
                        <div class="notice notice-<?php echo $contact_result['success'] ? 'success' : 'error'; ?> inline" style="margin-top: 10px;">
                            <p><strong><?php echo esc_html($contact_result['message']); ?></strong></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="cra-card">
                    <h2>Відгуки та покращення</h2>
                    <p>Будь ласка, залишайте відгуки та пропозиції на сторінках плагінів — це дуже допомагає розвитку!</p>
                    <ul style="margin: 12px 0 0 20px; line-height: 1.8;">
                        <li><a href="https://wordpress.org/plugins/bilohash-ai-chat-consultant/reviews/" target="_blank">Відгуки на AI Chat Consultant</a></li>
                        <li><a href="https://wordpress.org/plugins/bilohash-smart-popups/reviews/" target="_blank">Відгуки на Smart Popups</a></li>
                        <li><a href="https://wordpress.org/support/plugin/bilohash-ai-chat-consultant/" target="_blank">Підтримка та ідеї для AI Chat</a></li>
                        <li><a href="https://wordpress.org/support/plugin/bilohash-smart-popups/" target="_blank">Підтримка та ідеї для Smart Popups</a></li>
                    </ul>
                </div>
            </div>

            <p class="submit">
                <input type="submit" name="cra_save_settings" class="button button-primary button-large" value="Зберегти налаштування">
            </p>
        </form>

        <!-- Usage + Preview -->
        <div class="cra-card" style="margin-top: 30px;">
            <h2>Як використовувати форму</h2>
            <p style="margin: 8px 0 16px;">
                Вставте цей шорткод на будь-яку сторінку, пост або в віджет:
            </p>
            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;font-family:monospace;font-size:15px;margin-bottom:20px;">
                [callback_request_form]
            </div>

            <p style="margin-bottom:6px;"><strong>Поради:</strong></p>
            <ul style="margin:0 0 16px 20px; line-height:1.7;">
                <li>Увімкніть/вимкніть типи контактів у вкладці «Загальні»</li>
                <li>Налаштуйте кольори та зовнішній вигляд у вкладці «Дизайн» — превʼю форми є безпосередньо у цій вкладці</li>
                <li>Заявки зберігаються як «Callback Requests» (можна переглянути у меню зліва)</li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Окрема сторінка для масової розсилки (в боковому меню)
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
function cra_bulk_mailing_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Недостатньо прав доступу.', 'callback-request-admin'));
    }

    $result = null;

    if (isset($_POST['cra_send_bulk'])) {
        if (!isset($_POST['cra_bulk_nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cra_bulk_nonce'] ) ), 'cra_bulk_email' )) {
            wp_die(esc_html__('Помилка безпеки.', 'callback-request-admin'));
        }

        $subject = sanitize_text_field( wp_unslash( $_POST['bulk_subject'] ?? '' ) );
        $message = wp_kses_post( wp_unslash( $_POST['bulk_message'] ?? '' ) );

        if (empty($subject) || empty($message)) {
            $result = ['success' => false, 'message' => 'Вкажіть тему та текст листа.'];
        } else {
            $subscribers = get_posts([ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                'post_type'      => 'callback_subscriber',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_key'       => 'subscriber_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_value'     => 'subscribed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            ]);

            $sent = 0;
            $failed = 0;

            foreach ($subscribers as $sub) {
                $email = get_post_meta($sub->ID, 'subscriber_email', true);
                $name = get_post_meta($sub->ID, 'subscriber_name', true) ?: 'Шановний підписнику';

                if ($email) {
                    $personalized = str_replace(['{name}', '{email}'], [esc_html($name), esc_html($email)], $message);

                    // Cool custom header + footer using chosen colors for this mailing (same as preview)
                    $h_color = isset($_POST['bulk_header_color']) ? sanitize_hex_color( wp_unslash( $_POST['bulk_header_color'] ) ) : '#2563eb';
                    $f_color = isset($_POST['bulk_footer_color']) ? sanitize_hex_color( wp_unslash( $_POST['bulk_footer_color'] ) ) : '#f1f5f9';
                    $logo = !empty($settings['email_logo_url']) ? '<img src="' . esc_url($settings['email_logo_url']) . '" alt="Logo" style="max-height:40px; margin-bottom:8px;">' : '';
                    $footer_text = !empty($settings['email_footer_text']) ? esc_html($settings['email_footer_text']) : 'З повагою, команда сайту';

                    $html_body = '
                    <div style="font-family: system-ui, Arial, sans-serif; max-width: 620px; margin: 0 auto; background: #f8fafc;">
                        <div style="background:' . esc_attr($h_color) . '; color: #fff; padding: 20px 25px; text-align: center;">
                            ' . $logo . '
                            <h2 style="margin:0; font-size:20px; color:#fff;">' . esc_html($subject) . '</h2>
                        </div>
                        <div style="background:#ffffff; padding:25px 30px; border:1px solid #e5e7eb; border-top:none; color:#111827; line-height:1.6;">
                            ' . wpautop( $personalized ) . '
                            <div style="margin-top:30px; padding-top:15px; border-top:1px solid #eee; font-size:13px; color:#6b7280;">
                                ' . $footer_text . '
                            </div>
                        </div>
                        <div style="background:' . esc_attr($f_color) . '; padding: 12px 20px; text-align:center; font-size:12px; color:#374151; border-top:1px solid #e5e7eb;">
                            Ви отримали цей лист, тому що підписані на розсилку.
                        </div>
                    </div>';

                    $headers = ['Content-Type: text/html; charset=UTF-8'];

                    if (wp_mail($email, $subject, $html_body, $headers)) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                }
            }

            $result = [
                'success' => ($failed === 0),
                'message' => sprintf('Розсилку завершено. Успішно: %d, помилок: %d, всього: %d.', $sent, $failed, count($subscribers))
            ];
        }
    }

    // Підрахунок підписників (з ігнором для checker)
    $subscriber_count = count( get_posts([ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        'post_type'      => 'callback_subscriber',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_key'       => 'subscriber_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        'meta_value'     => 'subscribed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
    ]) );

    ?>
    <div class="wrap cra-admin-wrap">
        <h1>Масова розсилка підписникам</h1>
        <p>Надішліть красиво оформлений email усім активним підписникам (<?php echo intval($subscriber_count); ?>).</p>

        <div class="cra-card">
            <h2>Скласти та надіслати розсилку</h2>
            <form method="post">
                <?php wp_nonce_field('cra_bulk_email', 'cra_bulk_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th>Тема листа</th>
                        <td>
                            <?php $bulk_subject_val = isset($_POST['bulk_subject']) ? sanitize_text_field( wp_unslash( $_POST['bulk_subject'] ) ) : ''; ?>
                            <input type="text" name="bulk_subject" class="regular-text" value="<?php echo esc_attr( $bulk_subject_val ); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th>Текст повідомлення</th>
                        <td>
                            <?php $bulk_message_val = isset($_POST['bulk_message']) ? wp_kses_post( wp_unslash( $_POST['bulk_message'] ) ) : "Шановний {name},\n\nДякуємо за підписку!\n\nЗ найкращими побажаннями,\nКоманда сайту"; ?>
                            <textarea name="bulk_message" rows="10" class="large-text" required><?php echo esc_textarea( $bulk_message_val ); ?></textarea>
                            <p class="description">Використовуйте {name} та {email}. HTML підтримується.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Колір шапки листа (для цієї розсилки)</th>
                        <td>
                            <?php $bulk_header_color = isset($_POST['bulk_header_color']) ? sanitize_hex_color( wp_unslash( $_POST['bulk_header_color'] ) ) : '#2563eb'; ?>
                            <input type="color" name="bulk_header_color" value="<?php echo esc_attr( $bulk_header_color ); ?>">
                            <p class="description">Колір фону шапки email для цієї масової розсилки.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Колір футера листа (для цієї розсилки)</th>
                        <td>
                            <?php $bulk_footer_color = isset($_POST['bulk_footer_color']) ? sanitize_hex_color( wp_unslash( $_POST['bulk_footer_color'] ) ) : '#f1f5f9'; ?>
                            <input type="color" name="bulk_footer_color" value="<?php echo esc_attr( $bulk_footer_color ); ?>">
                            <p class="description">Колір фону футера email для цієї масової розсилки.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="cra_send_bulk" class="button button-primary button-large" value="Надіслати всім підписникам">
                </p>
            </form>

            <?php if ($result): ?>
                <div class="notice notice-<?php echo $result['success'] ? 'success' : 'error'; ?> is-dismissible" style="margin-top:15px;">
                    <p><strong><?php echo esc_html($result['message']); ?></strong></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="cra-card">
            <h2>Попередній перегляд оформлення листа</h2>
            <p>Як виглядатиме ваш лист з обраними кольорами шапки та футера (використовується глобальний логотип та текст футера з налаштувань email):</p>
            <div style="max-width: 620px; margin: 20px auto; border: 1px solid #e5e7eb; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                <?php
                    $bulk_subject_post = isset( $_POST['bulk_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_subject'] ) ) : '';
                    $bulk_message_post = isset( $_POST['bulk_message'] ) ? wp_kses_post( wp_unslash( $_POST['bulk_message'] ) ) : '';
                    $preview_subj = !empty($bulk_subject_post) ? $bulk_subject_post : 'Приклад теми вашої розсилки';
                    $preview_msg  = !empty($bulk_message_post) ? $bulk_message_post : 'Шановний {name},<br><br>Це приклад вашого повідомлення.<br><br>Дякуємо за підписку!';

                    $h_color = isset($_POST['bulk_header_color']) ? sanitize_hex_color( wp_unslash( $_POST['bulk_header_color'] ) ) : '#2563eb';
                    $f_color = isset($_POST['bulk_footer_color']) ? sanitize_hex_color( wp_unslash( $_POST['bulk_footer_color'] ) ) : '#f1f5f9';

                    // Cool custom header + footer for bulk mailing preview/send
                    $logo = !empty($settings['email_logo_url']) ? '<img src="' . esc_url($settings['email_logo_url']) . '" alt="Logo" style="max-height:40px; margin-bottom:8px;">' : '';
                    $footer_text = !empty($settings['email_footer_text']) ? esc_html($settings['email_footer_text']) : 'З повагою, команда сайту';

                    $custom_preview = '
                    <div style="font-family: system-ui, Arial, sans-serif; max-width: 620px; margin: 0 auto; background: #f8fafc;">
                        <div style="background:' . esc_attr($h_color) . '; color: #fff; padding: 20px 25px; text-align: center;">
                            ' . $logo . '
                            <h2 style="margin:0; font-size:20px; color:#fff;">' . esc_html($preview_subj) . '</h2>
                        </div>
                        <div style="background:#ffffff; padding:25px 30px; border:1px solid #e5e7eb; border-top:none; color:#111827; line-height:1.6;">
                            ' . wpautop( str_replace('{name}', 'Олексій', $preview_msg) ) . '
                            <div style="margin-top:30px; padding-top:15px; border-top:1px solid #eee; font-size:13px; color:#6b7280;">
                                ' . $footer_text . '
                            </div>
                        </div>
                        <div style="background:' . esc_attr($f_color) . '; padding: 12px 20px; text-align:center; font-size:12px; color:#374151; border-top:1px solid #e5e7eb;">
                            Ви отримали цей лист, тому що підписані на розсилку.
                        </div>
                    </div>';
                    echo wp_kses_post( $custom_preview );
                ?>
            </div>
            <p style="text-align:center; color:#6b7280; font-size:12px;">Кольори шапки та футера можна налаштувати безпосередньо у формі вище для цієї розсилки. Глобальний логотип та текст футера — з вкладки «Пошта (SMTP)».</p>
        </div>
    </div>
    <?php
}