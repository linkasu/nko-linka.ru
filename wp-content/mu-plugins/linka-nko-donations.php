<?php
/**
 * Voluntary donation form and YooKassa redirect integration.
 */

const LINKA_NKO_DONATION_AMOUNTS = [500, 1000, 3000, 5000];
const LINKA_NKO_DONATION_MIN_AMOUNT = 100;
const LINKA_NKO_DONATION_MAX_AMOUNT = 300000;
const LINKA_NKO_DONATION_SCHEMA_VERSION = '20260712-1';
const LINKA_NKO_DONATION_FREQUENCY_ONE_TIME = 'one_time';
const LINKA_NKO_DONATION_FREQUENCY_MONTHLY = 'monthly';

add_action('init', 'linka_nko_ensure_donation_schema', 1);
add_shortcode('linka_donation_form', 'linka_nko_render_donation_form');

add_action('admin_post_nopriv_linka_nko_create_donation', 'linka_nko_create_donation');
add_action('admin_post_linka_nko_create_donation', 'linka_nko_create_donation');
add_action('admin_post_nopriv_linka_nko_yookassa_webhook', 'linka_nko_yookassa_webhook');
add_action('admin_post_linka_nko_yookassa_webhook', 'linka_nko_yookassa_webhook');
add_action('admin_post_nopriv_linka_nko_run_recurring_donations', 'linka_nko_run_recurring_donations');
add_action('admin_post_linka_nko_run_recurring_donations', 'linka_nko_run_recurring_donations');

function linka_nko_render_donation_form(): string
{
    $shop_id = trim((string) getenv('YOOKASSA_SHOP_ID'));
    $secret_key = trim((string) getenv('YOOKASSA_SECRET_KEY'));
    $is_configured = $shop_id !== '' && $secret_key !== '';
    $recurring_enabled = linka_nko_recurring_enabled();
    $status = isset($_GET['donation']) ? sanitize_key((string) $_GET['donation']) : '';

    ob_start();
    ?>
    <section class="donation-form" aria-labelledby="donation-form-title">
      <h2 id="donation-form-title">Сделать пожертвование</h2>

      <?php if ($status === 'started') : ?>
        <p class="notice notice-success">Платеж создан. Если страница оплаты не открылась, попробуйте отправить форму еще раз.</p>
      <?php elseif ($status === 'cancelled') : ?>
        <p class="notice">Платеж не завершен. Вы можете попробовать еще раз.</p>
      <?php endif; ?>

      <?php if (!$is_configured) : ?>
        <p class="notice">Прием пожертвований одобрен и сейчас настраивается. Платежная форма будет включена после добавления ключей YooKassa.</p>
      <?php endif; ?>

      <form class="donation-form__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="linka_nko_create_donation">
        <?php wp_nonce_field('linka_nko_create_donation', 'linka_nko_donation_nonce'); ?>

        <?php if ($recurring_enabled) : ?>
          <fieldset class="donation-form__frequency">
            <legend>Периодичность</legend>
            <label>
              <input type="radio" name="donation_frequency" value="<?php echo esc_attr(LINKA_NKO_DONATION_FREQUENCY_ONE_TIME); ?>" checked>
              Разово
            </label>
            <label>
              <input type="radio" name="donation_frequency" value="<?php echo esc_attr(LINKA_NKO_DONATION_FREQUENCY_MONTHLY); ?>">
              Ежемесячно
            </label>
          </fieldset>
        <?php else : ?>
          <input type="hidden" name="donation_frequency" value="<?php echo esc_attr(LINKA_NKO_DONATION_FREQUENCY_ONE_TIME); ?>">
          <div class="donation-form__frequency-status" aria-label="Периодичность пожертвования">
            <strong>Разовое пожертвование</strong>
            <span>Ежемесячные пожертвования появятся после подключения автоплатежей YooKassa.</span>
          </div>
        <?php endif; ?>

        <fieldset class="donation-form__amounts">
          <legend>Сумма пожертвования</legend>
          <?php foreach (LINKA_NKO_DONATION_AMOUNTS as $amount) : ?>
            <label>
              <input type="radio" name="donation_amount" value="<?php echo esc_attr((string) $amount); ?>" <?php checked($amount, 1000); ?>>
              <?php echo esc_html(number_format($amount, 0, '.', ' ')); ?> ₽
            </label>
          <?php endforeach; ?>
          <label>
            <input type="radio" name="donation_amount" value="custom">
            Другая сумма
          </label>
          <label class="donation-form__custom-amount">
            <span>Введите сумму в рублях</span>
            <input type="number" name="custom_amount" min="<?php echo esc_attr((string) LINKA_NKO_DONATION_MIN_AMOUNT); ?>" max="<?php echo esc_attr((string) LINKA_NKO_DONATION_MAX_AMOUNT); ?>" step="1" inputmode="numeric">
          </label>
        </fieldset>

        <label>
          <span>ФИО жертвователя</span>
          <input type="text" name="donor_name" autocomplete="name" required maxlength="160">
        </label>

        <label>
          <span>Email для уведомлений</span>
          <input type="email" name="donor_email" autocomplete="email" required maxlength="190">
        </label>

        <label class="donation-form__consent">
          <input type="checkbox" name="donor_consent" value="1" required>
          <span>
            <strong>Согласен с условиями</strong>
            <span>Я ознакомился с <a href="<?php echo esc_url(home_url('/donation-offer/')); ?>" target="_blank" rel="noopener">публичной офертой</a> и <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" target="_blank" rel="noopener">политикой обработки персональных данных</a>, согласен на обработку персональных данных и понимаю, что пожертвование не является оплатой товаров или услуг.</span>
            <?php if ($recurring_enabled) : ?>
              <span>Для ежемесячного пожертвования я также соглашаюсь на сохранение способа оплаты YooKassa и последующие ежемесячные списания выбранной суммы до отмены.</span>
            <?php endif; ?>
          </span>
        </label>

        <p class="donation-form__note">После нажатия кнопки откроется платежная страница YooKassa со всеми доступными для магазина способами оплаты.</p>

        <button class="button" type="submit" <?php disabled(!$is_configured); ?>>Пожертвовать</button>
      </form>
    </section>
    <?php
    return (string) ob_get_clean();
}

function linka_nko_create_donation(): void
{
    if (!isset($_POST['linka_nko_donation_nonce']) || !wp_verify_nonce((string) $_POST['linka_nko_donation_nonce'], 'linka_nko_create_donation')) {
        wp_die('Некорректный запрос.', '', ['response' => 400]);
    }

    if (!linka_nko_yookassa_configured()) {
        wp_die('Прием пожертвований еще настраивается.', '', ['response' => 503]);
    }

    $request = wp_unslash($_POST);
    $amount = linka_nko_get_donation_amount($request);
    $frequency = linka_nko_get_donation_frequency($request);
    $donor_name = linka_nko_sanitize_donor_name((string) ($request['donor_name'] ?? ''));
    $donor_email = sanitize_email((string) ($request['donor_email'] ?? ''));
    $has_consent = isset($_POST['donor_consent']) && (string) $_POST['donor_consent'] === '1';

    if ($frequency === LINKA_NKO_DONATION_FREQUENCY_MONTHLY && !linka_nko_recurring_enabled()) {
        wp_die('Ежемесячные пожертвования будут включены после подключения автоплатежей YooKassa.', '', ['response' => 503]);
    }

    if ($amount === null || $donor_name === '' || $donor_email === '' || !is_email($donor_email) || !$has_consent) {
        wp_die('Проверьте сумму, ФИО, email и согласие с условиями пожертвования.', '', ['response' => 400]);
    }

    $subscription_id = null;
    if ($frequency === LINKA_NKO_DONATION_FREQUENCY_MONTHLY) {
        $subscription_id = linka_nko_insert_subscription($amount, $donor_name, $donor_email);
        if ($subscription_id === null) {
            wp_die('Не удалось подготовить ежемесячное пожертвование. Попробуйте позже.', '', ['response' => 500]);
        }
    }

    $payment_id = linka_nko_insert_payment($amount, $donor_name, $donor_email, $frequency, $subscription_id);
    if ($payment_id === null) {
        wp_die('Не удалось подготовить платеж. Попробуйте позже.', '', ['response' => 500]);
    }

    $payload = linka_nko_build_yookassa_payload($amount, $donor_name, $donor_email, $frequency, $payment_id, $subscription_id);
    $response = linka_nko_yookassa_request('POST', 'payments', $payload, 20);

    if (is_wp_error($response)) {
        error_log('YooKassa donation error: ' . $response->get_error_message());
        linka_nko_mark_payment_failed($payment_id, 'request_error');
        wp_die('Не удалось создать платеж. Попробуйте позже.', '', ['response' => 502]);
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
        linka_nko_log_bad_yookassa_response($status_code, $body);
        linka_nko_mark_payment_failed($payment_id, 'bad_response');
        wp_die('Платежный провайдер вернул ошибку. Попробуйте позже.', '', ['response' => 502]);
    }

    linka_nko_sync_payment_from_yookassa($body, $payment_id);

    $confirmation_url = $body['confirmation']['confirmation_url'] ?? '';
    if (!is_string($confirmation_url) || $confirmation_url === '') {
        error_log('YooKassa donation missing confirmation_url');
        wp_die('Платеж создан без ссылки подтверждения. Напишите нам на feedback@linka.su.', '', ['response' => 502]);
    }

    wp_redirect(esc_url_raw($confirmation_url), 303);
    exit;
}

function linka_nko_yookassa_webhook(): void
{
    $source = file_get_contents('php://input');
    $notification = json_decode((string) $source, true);
    if (!is_array($notification)) {
        status_header(400);
        echo 'bad request';
        exit;
    }

    $event = (string) ($notification['event'] ?? '');
    $payment_id = (string) ($notification['object']['id'] ?? '');
    if (!in_array($event, ['payment.succeeded', 'payment.canceled', 'payment.waiting_for_capture'], true) || $payment_id === '') {
        status_header(200);
        echo 'ignored';
        exit;
    }

    $response = linka_nko_yookassa_request('GET', 'payments/' . rawurlencode($payment_id), null, 10);
    if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
        error_log('YooKassa webhook verification failed for payment ' . substr($payment_id, 0, 80));
        status_header(502);
        echo 'verification failed';
        exit;
    }

    $payment = json_decode((string) wp_remote_retrieve_body($response), true);
    if (is_array($payment)) {
        linka_nko_sync_payment_from_yookassa($payment, null);
    }

    status_header(200);
    echo 'OK';
    exit;
}

function linka_nko_run_recurring_donations(): void
{
    $token = trim((string) getenv('LINKA_NKO_RECURRING_TOKEN'));
    $request_token = isset($_REQUEST['token']) ? (string) wp_unslash($_REQUEST['token']) : '';
    if ($token === '') {
        wp_send_json(['ok' => false, 'error' => 'recurring token is not configured'], 503);
    }

    if (!hash_equals($token, $request_token)) {
        wp_send_json(['ok' => false, 'error' => 'forbidden'], 403);
    }

    if (!linka_nko_recurring_enabled()) {
        wp_send_json(['ok' => true, 'enabled' => false, 'processed' => 0]);
    }

    $processed = 0;
    $failed = 0;
    foreach (linka_nko_get_due_subscriptions(20) as $subscription) {
        $result = linka_nko_charge_subscription($subscription);
        if ($result) {
            $processed++;
        } else {
            $failed++;
        }
    }

    wp_send_json(['ok' => true, 'enabled' => true, 'processed' => $processed, 'failed' => $failed]);
}

function linka_nko_charge_subscription(object $subscription): bool
{
    $amount = (int) $subscription->amount_value;
    $payment_method_id = (string) $subscription->payment_method_id;
    if ($amount <= 0 || $payment_method_id === '') {
        linka_nko_update_subscription_status((int) $subscription->id, 'failed');
        return false;
    }

    $payment_id = linka_nko_insert_payment($amount, (string) $subscription->donor_name, (string) $subscription->donor_email, LINKA_NKO_DONATION_FREQUENCY_MONTHLY, (int) $subscription->id);
    if ($payment_id === null) {
        return false;
    }

    $payload = linka_nko_build_recurring_charge_payload($amount, $payment_method_id, $payment_id, (int) $subscription->id);
    $response = linka_nko_yookassa_request('POST', 'payments', $payload, 20);
    if (is_wp_error($response)) {
        error_log('YooKassa recurring donation error: ' . $response->get_error_message());
        linka_nko_mark_payment_failed($payment_id, 'request_error');
        return false;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
        linka_nko_log_bad_yookassa_response($status_code, $body);
        linka_nko_mark_payment_failed($payment_id, 'bad_response');
        return false;
    }

    linka_nko_sync_payment_from_yookassa($body, $payment_id);
    return true;
}

function linka_nko_build_yookassa_payload(int $amount, string $donor_name, string $donor_email, string $frequency, int $local_payment_id, ?int $subscription_id): array
{
    $formatted_amount = number_format($amount, 2, '.', '');
    $description = $frequency === LINKA_NKO_DONATION_FREQUENCY_MONTHLY
        ? 'Ежемесячное добровольное пожертвование на уставную деятельность АНО Линка'
        : 'Добровольное пожертвование на уставную деятельность АНО Линка';

    $payload = [
        'amount' => [
            'value' => $formatted_amount,
            'currency' => 'RUB',
        ],
        'capture' => true,
        'confirmation' => [
            'type' => 'redirect',
            'return_url' => getenv('YOOKASSA_RETURN_URL') ?: home_url('/donation-thanks/'),
        ],
        'description' => $description,
        'save_payment_method' => $frequency === LINKA_NKO_DONATION_FREQUENCY_MONTHLY,
        'metadata' => [
            'purpose' => 'statutory_voluntary_donation',
            'frequency' => $frequency,
            'local_payment_id' => (string) $local_payment_id,
            'site' => home_url('/'),
        ],
    ];

    if ($subscription_id !== null) {
        $payload['metadata']['subscription_id'] = (string) $subscription_id;
    }

    if (filter_var(getenv('YOOKASSA_SEND_RECEIPT'), FILTER_VALIDATE_BOOLEAN)) {
        $payload['receipt'] = linka_nko_build_receipt($formatted_amount, $donor_name, $donor_email);
    }

    return $payload;
}

function linka_nko_build_recurring_charge_payload(int $amount, string $payment_method_id, int $local_payment_id, int $subscription_id): array
{
    $formatted_amount = number_format($amount, 2, '.', '');
    $payload = [
        'amount' => [
            'value' => $formatted_amount,
            'currency' => 'RUB',
        ],
        'capture' => true,
        'payment_method_id' => $payment_method_id,
        'description' => 'Ежемесячное добровольное пожертвование на уставную деятельность АНО Линка',
        'metadata' => [
            'purpose' => 'statutory_voluntary_donation',
            'frequency' => LINKA_NKO_DONATION_FREQUENCY_MONTHLY,
            'local_payment_id' => (string) $local_payment_id,
            'subscription_id' => (string) $subscription_id,
            'site' => home_url('/'),
        ],
    ];

    if (filter_var(getenv('YOOKASSA_SEND_RECEIPT'), FILTER_VALIDATE_BOOLEAN)) {
        $payload['receipt'] = linka_nko_build_receipt($formatted_amount, '', '');
    }

    return $payload;
}

function linka_nko_build_receipt(string $formatted_amount, string $donor_name, string $donor_email): array
{
    $customer = [];
    if ($donor_name !== '') {
        $customer['full_name'] = $donor_name;
    }
    if ($donor_email !== '') {
        $customer['email'] = $donor_email;
    }

    $receipt = [
        'items' => [[
            'description' => 'Добровольное пожертвование на уставную деятельность',
            'quantity' => '1.00',
            'amount' => [
                'value' => $formatted_amount,
                'currency' => 'RUB',
            ],
            'vat_code' => (int) (getenv('YOOKASSA_VAT_CODE') ?: 1),
            'payment_mode' => 'full_payment',
            'payment_subject' => getenv('YOOKASSA_PAYMENT_SUBJECT') ?: 'another',
        ]],
    ];

    if ($customer !== []) {
        $receipt['customer'] = $customer;
    }

    $tax_system_code = getenv('YOOKASSA_TAX_SYSTEM_CODE');
    if ($tax_system_code !== false && $tax_system_code !== '') {
        $receipt['tax_system_code'] = (int) $tax_system_code;
    }

    return $receipt;
}

function linka_nko_yookassa_request(string $method, string $path, ?array $payload = null, int $timeout = 20)
{
    $shop_id = trim((string) getenv('YOOKASSA_SHOP_ID'));
    $secret_key = trim((string) getenv('YOOKASSA_SECRET_KEY'));
    if ($shop_id === '' || $secret_key === '') {
        return new WP_Error('linka_nko_yookassa_not_configured', 'YooKassa is not configured.');
    }

    $args = [
        'method' => $method,
        'timeout' => $timeout,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($shop_id . ':' . $secret_key),
            'Content-Type' => 'application/json',
            'Idempotence-Key' => wp_generate_uuid4(),
        ],
    ];

    if ($payload !== null) {
        $args['body'] = wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    return wp_remote_request('https://api.yookassa.ru/v3/' . ltrim($path, '/'), $args);
}

function linka_nko_get_donation_amount(array $source): ?int
{
    $selected = sanitize_text_field((string) ($source['donation_amount'] ?? ''));
    if ($selected === 'custom') {
        $amount = (int) ($source['custom_amount'] ?? 0);
    } else {
        $amount = (int) $selected;
    }

    if ($amount < LINKA_NKO_DONATION_MIN_AMOUNT || $amount > LINKA_NKO_DONATION_MAX_AMOUNT) {
        return null;
    }

    return $amount;
}

function linka_nko_get_donation_frequency(array $source): string
{
    $frequency = sanitize_key((string) ($source['donation_frequency'] ?? LINKA_NKO_DONATION_FREQUENCY_ONE_TIME));
    if ($frequency === LINKA_NKO_DONATION_FREQUENCY_MONTHLY) {
        return LINKA_NKO_DONATION_FREQUENCY_MONTHLY;
    }

    return LINKA_NKO_DONATION_FREQUENCY_ONE_TIME;
}

function linka_nko_sanitize_donor_name(string $name): string
{
    $name = trim(wp_strip_all_tags($name));
    $name = preg_replace('/\s+/u', ' ', $name) ?: '';
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 160);
    }

    return substr($name, 0, 160);
}

function linka_nko_yookassa_configured(): bool
{
    return trim((string) getenv('YOOKASSA_SHOP_ID')) !== '' && trim((string) getenv('YOOKASSA_SECRET_KEY')) !== '';
}

function linka_nko_recurring_enabled(): bool
{
    return filter_var(getenv('YOOKASSA_RECURRING_ENABLED'), FILTER_VALIDATE_BOOLEAN);
}

function linka_nko_ensure_donation_schema(): void
{
    if (get_option('linka_nko_donation_schema_version') === LINKA_NKO_DONATION_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $tables = linka_nko_donation_tables();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE {$tables['payments']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        subscription_id bigint(20) unsigned NULL,
        yookassa_payment_id varchar(80) NULL,
        amount_value decimal(12,2) NOT NULL,
        currency char(3) NOT NULL DEFAULT 'RUB',
        donor_name varchar(160) NOT NULL,
        donor_email varchar(190) NOT NULL,
        frequency varchar(20) NOT NULL DEFAULT 'one_time',
        status varchar(32) NOT NULL DEFAULT 'pending',
        payment_method_id varchar(128) NULL,
        payment_method_saved tinyint(1) NOT NULL DEFAULT 0,
        cancellation_reason varchar(100) NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        paid_at datetime NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY yookassa_payment_id (yookassa_payment_id),
        KEY subscription_id (subscription_id),
        KEY status (status),
        KEY donor_email (donor_email)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$tables['subscriptions']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        amount_value decimal(12,2) NOT NULL,
        currency char(3) NOT NULL DEFAULT 'RUB',
        donor_name varchar(160) NOT NULL,
        donor_email varchar(190) NOT NULL,
        status varchar(32) NOT NULL DEFAULT 'pending',
        payment_method_id varchar(128) NULL,
        cancellation_token_hash char(64) NOT NULL,
        next_charge_at datetime NULL,
        last_charge_at datetime NULL,
        last_payment_id bigint(20) unsigned NULL,
        failed_attempts int(10) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        canceled_at datetime NULL,
        PRIMARY KEY  (id),
        KEY donor_email (donor_email),
        KEY due_subscriptions (status,next_charge_at)
    ) {$charset_collate};");

    update_option('linka_nko_donation_schema_version', LINKA_NKO_DONATION_SCHEMA_VERSION, false);
}

function linka_nko_donation_tables(): array
{
    global $wpdb;

    return [
        'payments' => $wpdb->prefix . 'linka_donation_payments',
        'subscriptions' => $wpdb->prefix . 'linka_donation_subscriptions',
    ];
}

function linka_nko_insert_subscription(int $amount, string $donor_name, string $donor_email): ?int
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $now = linka_nko_utc_now();
    $token = wp_generate_password(40, false, false);

    $inserted = $wpdb->insert($tables['subscriptions'], [
        'amount_value' => number_format($amount, 2, '.', ''),
        'currency' => 'RUB',
        'donor_name' => $donor_name,
        'donor_email' => $donor_email,
        'status' => 'pending',
        'cancellation_token_hash' => hash('sha256', $token),
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

    return $inserted ? (int) $wpdb->insert_id : null;
}

function linka_nko_insert_payment(int $amount, string $donor_name, string $donor_email, string $frequency, ?int $subscription_id): ?int
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $now = linka_nko_utc_now();

    $inserted = $wpdb->insert($tables['payments'], [
        'subscription_id' => $subscription_id,
        'amount_value' => number_format($amount, 2, '.', ''),
        'currency' => 'RUB',
        'donor_name' => $donor_name,
        'donor_email' => $donor_email,
        'frequency' => $frequency,
        'status' => 'pending',
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

    return $inserted ? (int) $wpdb->insert_id : null;
}

function linka_nko_sync_payment_from_yookassa(array $payment, ?int $fallback_local_payment_id): void
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $yookassa_payment_id = isset($payment['id']) && is_scalar($payment['id']) ? (string) $payment['id'] : '';
    if ($yookassa_payment_id === '') {
        return;
    }

    $metadata = isset($payment['metadata']) && is_array($payment['metadata']) ? $payment['metadata'] : [];
    $local_payment_id = isset($metadata['local_payment_id']) ? (int) $metadata['local_payment_id'] : 0;
    if ($local_payment_id <= 0 && $fallback_local_payment_id !== null) {
        $local_payment_id = $fallback_local_payment_id;
    }

    $status = sanitize_key((string) ($payment['status'] ?? 'pending'));
    $payment_method = isset($payment['payment_method']) && is_array($payment['payment_method']) ? $payment['payment_method'] : [];
    $payment_method_id = isset($payment_method['id']) && is_scalar($payment_method['id']) ? (string) $payment_method['id'] : '';
    $payment_method_saved = !empty($payment_method['saved']) ? 1 : 0;
    $paid_at = null;
    if ($status === 'succeeded') {
        $paid_at = linka_nko_yookassa_datetime_to_mysql((string) ($payment['captured_at'] ?? $payment['created_at'] ?? '')) ?: linka_nko_utc_now();
    }

    $data = [
        'yookassa_payment_id' => $yookassa_payment_id,
        'status' => $status,
        'payment_method_id' => $payment_method_id !== '' ? $payment_method_id : null,
        'payment_method_saved' => $payment_method_saved,
        'updated_at' => linka_nko_utc_now(),
    ];
    $format = ['%s', '%s', '%s', '%d', '%s'];
    if ($paid_at !== null) {
        $data['paid_at'] = $paid_at;
        $format[] = '%s';
    }

    $where = $local_payment_id > 0 ? ['id' => $local_payment_id] : ['yookassa_payment_id' => $yookassa_payment_id];
    $where_format = $local_payment_id > 0 ? ['%d'] : ['%s'];
    $wpdb->update($tables['payments'], $data, $where, $format, $where_format);

    if ($status === 'canceled') {
        $reason = '';
        if (isset($payment['cancellation_details']['reason']) && is_scalar($payment['cancellation_details']['reason'])) {
            $reason = sanitize_key((string) $payment['cancellation_details']['reason']);
            $wpdb->update($tables['payments'], [
                'cancellation_reason' => $reason,
                'updated_at' => linka_nko_utc_now(),
            ], $where, ['%s', '%s'], $where_format);
        }
    }

    $subscription_id = isset($metadata['subscription_id']) ? (int) $metadata['subscription_id'] : 0;
    if ($subscription_id <= 0 && $local_payment_id > 0) {
        $subscription_id = (int) $wpdb->get_var($wpdb->prepare("SELECT subscription_id FROM {$tables['payments']} WHERE id = %d", $local_payment_id));
    }

    if ($subscription_id > 0) {
        linka_nko_sync_subscription_from_payment($subscription_id, $status, $payment_method_id, (bool) $payment_method_saved, $local_payment_id);
    }
}

function linka_nko_sync_subscription_from_payment(int $subscription_id, string $payment_status, string $payment_method_id, bool $payment_method_saved, int $local_payment_id): void
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $now = linka_nko_utc_now();

    if ($payment_status === 'succeeded' && $payment_method_saved && $payment_method_id !== '') {
        $wpdb->update($tables['subscriptions'], [
            'status' => 'active',
            'payment_method_id' => $payment_method_id,
            'last_charge_at' => $now,
            'next_charge_at' => linka_nko_next_monthly_charge_at($now),
            'last_payment_id' => $local_payment_id > 0 ? $local_payment_id : null,
            'failed_attempts' => 0,
            'updated_at' => $now,
        ], ['id' => $subscription_id], ['%s', '%s', '%s', '%s', '%d', '%d', '%s'], ['%d']);
        return;
    }

    if ($payment_status === 'succeeded') {
        $wpdb->update($tables['subscriptions'], [
            'status' => 'failed',
            'last_charge_at' => $now,
            'last_payment_id' => $local_payment_id > 0 ? $local_payment_id : null,
            'failed_attempts' => 1,
            'updated_at' => $now,
        ], ['id' => $subscription_id], ['%s', '%s', '%d', '%d', '%s'], ['%d']);
        return;
    }

    if ($payment_status === 'canceled') {
        $wpdb->update($tables['subscriptions'], [
            'status' => 'failed',
            'failed_attempts' => 1,
            'updated_at' => $now,
        ], ['id' => $subscription_id], ['%s', '%d', '%s'], ['%d']);
    }
}

function linka_nko_get_due_subscriptions(int $limit): array
{
    global $wpdb;
    $tables = linka_nko_donation_tables();

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$tables['subscriptions']} WHERE status = 'active' AND next_charge_at IS NOT NULL AND next_charge_at <= %s ORDER BY next_charge_at ASC LIMIT %d",
        linka_nko_utc_now(),
        $limit
    ));
}

function linka_nko_update_subscription_status(int $subscription_id, string $status): void
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $wpdb->update($tables['subscriptions'], [
        'status' => $status,
        'updated_at' => linka_nko_utc_now(),
    ], ['id' => $subscription_id], ['%s', '%s'], ['%d']);
}

function linka_nko_mark_payment_failed(int $payment_id, string $reason): void
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $subscription_id = (int) $wpdb->get_var($wpdb->prepare("SELECT subscription_id FROM {$tables['payments']} WHERE id = %d", $payment_id));

    $wpdb->update($tables['payments'], [
        'status' => 'failed',
        'cancellation_reason' => $reason,
        'updated_at' => linka_nko_utc_now(),
    ], ['id' => $payment_id], ['%s', '%s', '%s'], ['%d']);

    if ($subscription_id > 0) {
        $wpdb->update($tables['subscriptions'], [
            'status' => 'failed',
            'failed_attempts' => 1,
            'updated_at' => linka_nko_utc_now(),
        ], ['id' => $subscription_id], ['%s', '%d', '%s'], ['%d']);
    }
}

function linka_nko_log_bad_yookassa_response(int $status_code, $body): void
{
    $error_details = [];
    if (is_array($body)) {
        foreach (['type', 'id', 'code', 'description', 'parameter'] as $field) {
            if (isset($body[$field]) && is_scalar($body[$field])) {
                $error_details[$field] = substr(wp_strip_all_tags((string) $body[$field]), 0, 300);
            }
        }
    }

    error_log('YooKassa donation bad response: HTTP ' . $status_code . ' ' . wp_json_encode($error_details, JSON_UNESCAPED_UNICODE));
}

function linka_nko_utc_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function linka_nko_next_monthly_charge_at(string $from): string
{
    $timestamp = strtotime($from . ' UTC +1 month');
    if ($timestamp === false) {
        $timestamp = time() + MONTH_IN_SECONDS;
    }

    return gmdate('Y-m-d H:i:s', $timestamp);
}

function linka_nko_yookassa_datetime_to_mysql(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return gmdate('Y-m-d H:i:s', $timestamp);
}
