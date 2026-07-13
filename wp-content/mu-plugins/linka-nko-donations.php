<?php
/**
 * Voluntary donation form and YooKassa redirect integration.
 */

const LINKA_NKO_DONATION_AMOUNTS = [500, 1000, 3000, 5000];
const LINKA_NKO_DONATION_MIN_AMOUNT = 100;
const LINKA_NKO_DONATION_MAX_AMOUNT = 300000;
const LINKA_NKO_DONATION_SCHEMA_VERSION = '20260713-2';
const LINKA_NKO_DONATION_FREQUENCY_ONE_TIME = 'one_time';
const LINKA_NKO_DONATION_FREQUENCY_MONTHLY = 'monthly';
const LINKA_NKO_DONATION_SUBSCRIPTION_PATH = 'donation-subscription';
const LINKA_NKO_RECURRING_INTERNAL_PATH = 'internal/run-recurring-donations';
const LINKA_NKO_DONATION_SYNC_INTERNAL_PATH = 'internal/sync-donations';

add_action('init', 'linka_nko_ensure_donation_schema', 1);
add_shortcode('linka_donation_form', 'linka_nko_render_donation_form');
add_shortcode('linka_donation_subscription', 'linka_nko_render_subscription_page');
add_shortcode('linka_donation_total', 'linka_nko_render_donation_total');
add_action('admin_menu', 'linka_nko_register_donations_admin_page');

add_action('admin_post_nopriv_linka_nko_create_donation', 'linka_nko_create_donation');
add_action('admin_post_linka_nko_create_donation', 'linka_nko_create_donation');
add_action('admin_post_nopriv_linka_nko_cancel_subscription', 'linka_nko_cancel_subscription');
add_action('admin_post_linka_nko_cancel_subscription', 'linka_nko_cancel_subscription');
add_action('admin_post_nopriv_linka_nko_yookassa_webhook', 'linka_nko_yookassa_webhook');
add_action('admin_post_linka_nko_yookassa_webhook', 'linka_nko_yookassa_webhook');
add_action('admin_post_nopriv_linka_nko_run_recurring_donations', 'linka_nko_run_recurring_donations');
add_action('admin_post_linka_nko_run_recurring_donations', 'linka_nko_run_recurring_donations');
add_action('template_redirect', 'linka_nko_run_internal_recurring_path', 1);
add_action('template_redirect', 'linka_nko_run_internal_donation_sync_path', 1);
add_action('template_redirect', 'linka_nko_render_subscription_path');

function linka_nko_render_donation_form(): string
{
    $shop_id = trim((string) getenv('YOOKASSA_SHOP_ID'));
    $secret_key = trim((string) getenv('YOOKASSA_SECRET_KEY'));
    $is_configured = $shop_id !== '' && $secret_key !== '';
    $recurring_enabled = linka_nko_recurring_enabled();
    $raw_status = $_GET['donation'] ?? '';
    $status = is_scalar($raw_status) ? sanitize_key((string) wp_unslash($raw_status)) : '';

    ob_start();
    ?>
    <section class="donation-form" aria-labelledby="donation-form-title">
      <h2 id="donation-form-title">Сделать пожертвование</h2>

      <?php echo linka_nko_render_donation_total(); ?>

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
              <span>Для ежемесячного пожертвования я также соглашаюсь на сохранение способа оплаты YooKassa и последующие ежемесячные списания выбранной суммы до самостоятельного отключения по ссылке управления.</span>
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

function linka_nko_render_donation_total(): string
{
    $total = linka_nko_get_successful_donation_total();
    return '<div class="donation-total" aria-label="Общая сумма успешных пожертвований"><span>Уже пожертвовали</span><strong>' . esc_html(linka_nko_format_amount($total)) . ' ₽</strong></div>';
}

function linka_nko_render_subscription_path(): void
{
    if (is_page(LINKA_NKO_DONATION_SUBSCRIPTION_PATH)) {
        return;
    }

    $path = trim((string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    if ($path !== LINKA_NKO_DONATION_SUBSCRIPTION_PATH) {
        return;
    }

    status_header(200);
    nocache_headers();
    get_header();
    echo '<main class="content"><div class="wrap"><article>';
    echo '<h1>Управление ежемесячным пожертвованием</h1>';
    echo linka_nko_render_subscription_page();
    echo '</article></div></main>';
    get_footer();
    exit;
}

function linka_nko_render_subscription_page(): string
{
    $token = linka_nko_get_request_token($_GET['token'] ?? '');
    $raw_status = $_GET['subscription'] ?? '';
    $status = is_scalar($raw_status) ? sanitize_key((string) wp_unslash($raw_status)) : '';

    ob_start();
    ?>
    <section class="donation-subscription" aria-labelledby="donation-subscription-title">
      <h2 id="donation-subscription-title">Ежемесячное пожертвование</h2>

      <?php if ($status === 'cancelled') : ?>
        <p class="notice notice-success">Ежемесячное пожертвование отключено. Сохраненный способ оплаты удален из нашей системы.</p>
      <?php endif; ?>

      <?php if ($token === '') : ?>
        <p class="notice">Откройте страницу по ссылке управления из письма о подключении ежемесячного пожертвования.</p>
        <p>Если ссылка недоступна, напишите нам на <a href="mailto:feedback@linka.su">feedback@linka.su</a>.</p>
      <?php else : ?>
        <?php $subscription = linka_nko_get_subscription_by_token($token); ?>
        <?php if ($subscription === null) : ?>
          <p class="notice">Ссылка управления не найдена или устарела.</p>
          <p>Проверьте ссылку из письма. Если проблема повторяется, напишите нам на <a href="mailto:feedback@linka.su">feedback@linka.su</a>.</p>
        <?php else : ?>
          <?php echo linka_nko_render_subscription_details($subscription, $token); ?>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

function linka_nko_render_subscription_details(object $subscription, string $token): string
{
    $status = (string) $subscription->status;
    $amount = linka_nko_format_amount((float) $subscription->amount_value);
    $next_charge = linka_nko_format_date((string) $subscription->next_charge_at);
    $email = linka_nko_mask_email((string) $subscription->donor_email);

    ob_start();
    ?>
    <div class="donation-subscription__card">
      <dl class="donation-subscription__details">
        <div>
          <dt>Статус</dt>
          <dd><?php echo esc_html(linka_nko_subscription_status_label($status)); ?></dd>
        </div>
        <div>
          <dt>Сумма</dt>
          <dd><?php echo esc_html($amount); ?> ₽</dd>
        </div>
        <div>
          <dt>Периодичность</dt>
          <dd>Один раз в месяц</dd>
        </div>
        <div>
          <dt>Email</dt>
          <dd><?php echo esc_html($email); ?></dd>
        </div>
        <?php if ($next_charge !== '') : ?>
          <div>
            <dt>Следующее списание</dt>
            <dd><?php echo esc_html($next_charge); ?></dd>
          </div>
        <?php endif; ?>
      </dl>

      <?php if (in_array($status, ['active', 'charging'], true) && (string) $subscription->payment_method_id !== '') : ?>
        <p>Вы можете в любой момент отключить ежемесячное пожертвование. После отключения сохраненный способ оплаты будет удален из нашей системы, новые списания выполняться не будут.</p>
        <form class="donation-subscription__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <input type="hidden" name="action" value="linka_nko_cancel_subscription">
          <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
          <?php wp_nonce_field('linka_nko_cancel_subscription', 'linka_nko_cancel_subscription_nonce'); ?>
          <button class="button donation-subscription__cancel" type="submit">Отключить ежемесячное пожертвование и отвязать способ оплаты</button>
        </form>
      <?php elseif ($status === 'canceled') : ?>
        <p class="notice notice-success">Ежемесячное пожертвование уже отключено. Сохраненный способ оплаты удален из нашей системы.</p>
      <?php elseif ($status === 'pending') : ?>
        <p class="notice">Мы ожидаем подтверждение первого платежа YooKassa. После успешного платежа управление ежемесячным пожертвованием станет доступно по этой ссылке.</p>
      <?php else : ?>
        <p class="notice">Ежемесячное пожертвование сейчас не активно. Если у вас есть вопросы, напишите нам на <a href="mailto:feedback@linka.su">feedback@linka.su</a>.</p>
      <?php endif; ?>
    </div>
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
    $donor_name = linka_nko_sanitize_donor_name(linka_nko_scalar_string($request['donor_name'] ?? ''));
    $donor_email = sanitize_email(linka_nko_scalar_string($request['donor_email'] ?? ''));
    $has_consent = isset($request['donor_consent']) && linka_nko_scalar_string($request['donor_consent']) === '1';

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

    $local_payment = linka_nko_get_payment_by_id($payment_id);
    if ($local_payment === null || (string) $local_payment->idempotence_key === '') {
        linka_nko_mark_payment_failed($payment_id, 'missing_idempotence_key');
        wp_die('Не удалось подготовить платеж. Попробуйте позже.', '', ['response' => 500]);
    }

    $payload = linka_nko_build_yookassa_payload($amount, $donor_name, $donor_email, $frequency, $payment_id, $subscription_id);
    $response = linka_nko_yookassa_request('POST', 'payments', $payload, 20, (string) $local_payment->idempotence_key);

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

function linka_nko_cancel_subscription(): void
{
    if (!isset($_POST['linka_nko_cancel_subscription_nonce']) || !wp_verify_nonce((string) $_POST['linka_nko_cancel_subscription_nonce'], 'linka_nko_cancel_subscription')) {
        wp_die('Некорректный запрос.', '', ['response' => 400]);
    }

    $token = linka_nko_get_request_token($_POST['token'] ?? '');
    if ($token === '') {
        wp_die('Ссылка управления не найдена.', '', ['response' => 400]);
    }

    $subscription = linka_nko_get_subscription_by_token($token);
    if ($subscription === null) {
        wp_die('Ежемесячное пожертвование не найдено.', '', ['response' => 404]);
    }

    linka_nko_cancel_subscription_record($subscription);

    wp_redirect(esc_url_raw(linka_nko_subscription_manage_url($token, ['subscription' => 'cancelled'])), 303);
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

    $raw_event = $notification['event'] ?? '';
    $raw_payment_id = is_array($notification['object'] ?? null) ? ($notification['object']['id'] ?? '') : '';
    $event = is_scalar($raw_event) ? (string) $raw_event : '';
    $payment_id = is_scalar($raw_payment_id) ? (string) $raw_payment_id : '';
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
    $raw_request_token = $_REQUEST['token'] ?? '';
    $request_token = is_scalar($raw_request_token) ? (string) wp_unslash($raw_request_token) : '';
    if ($token === '') {
        wp_send_json(['ok' => false, 'error' => 'recurring token is not configured'], 503);
    }

    if (!hash_equals($token, $request_token)) {
        wp_send_json(['ok' => false, 'error' => 'forbidden'], 403);
    }

    if (!linka_nko_recurring_enabled()) {
        wp_send_json(['ok' => true, 'enabled' => false, 'processed' => 0], 200);
    }

    wp_send_json(linka_nko_process_due_subscriptions(), 200);
}

function linka_nko_run_internal_recurring_path(): void
{
    $path = (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path !== '/' . LINKA_NKO_RECURRING_INTERNAL_PATH) {
        return;
    }

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        wp_send_json(['ok' => false, 'error' => 'method not allowed'], 405);
    }

    if (!linka_nko_recurring_enabled()) {
        wp_send_json(['ok' => true, 'enabled' => false, 'processed' => 0], 200);
    }

    wp_send_json(linka_nko_process_due_subscriptions(), 200);
}

function linka_nko_run_internal_donation_sync_path(): void
{
    $path = (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path !== '/' . LINKA_NKO_DONATION_SYNC_INTERNAL_PATH) {
        return;
    }

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        wp_send_json(['ok' => false, 'error' => 'method not allowed'], 405);
    }

    $sync = linka_nko_sync_successful_payments_from_yookassa();
    if (is_wp_error($sync)) {
        wp_send_json(['ok' => false, 'payments_synced' => false], 502);
    }

    wp_send_json(array_merge(['ok' => true], $sync), 200);
}

function linka_nko_sync_successful_payments_from_yookassa()
{
    $total = 0.0;
    $payment_count = 0;
    $cursor = '';

    for ($page = 0; $page < 20; $page++) {
        $path = 'payments?status=succeeded&limit=100';
        if ($cursor !== '') {
            $path .= '&cursor=' . rawurlencode($cursor);
        }

        $response = linka_nko_yookassa_request('GET', $path, null, 20);
        if (is_wp_error($response)) {
            error_log('YooKassa payment total sync failed: ' . $response->get_error_message());
            return $response;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status_code !== 200 || !is_array($body) || !is_array($body['items'] ?? null)) {
            linka_nko_log_bad_yookassa_response($status_code, $body);
            return new WP_Error('linka_nko_yookassa_total_sync_failed', 'YooKassa payment list is unavailable.');
        }

        foreach ($body['items'] as $payment) {
            if (!is_array($payment) || (string) ($payment['status'] ?? '') !== 'succeeded') {
                continue;
            }

            $metadata = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
            if (!empty($payment['test']) || (string) ($metadata['purpose'] ?? '') !== 'statutory_voluntary_donation') {
                continue;
            }

            $currency = (string) ($payment['amount']['currency'] ?? '');
            $amount = (float) ($payment['amount']['value'] ?? 0);
            $refunded = (float) ($payment['refunded_amount']['value'] ?? 0);
            if ($currency !== 'RUB' || $amount <= 0) {
                continue;
            }

            $total += max(0, $amount - $refunded);
            $payment_count++;
        }

        $cursor = isset($body['next_cursor']) && is_scalar($body['next_cursor']) ? (string) $body['next_cursor'] : '';
        if ($cursor === '') {
            break;
        }
    }

    if ($cursor !== '') {
        return new WP_Error('linka_nko_yookassa_total_sync_incomplete', 'YooKassa payment list pagination limit was reached.');
    }

    update_option('linka_nko_donation_total_api', number_format($total, 2, '.', ''), false);
    update_option('linka_nko_donation_total_synced_at', linka_nko_utc_now(), false);

    return [
        'payments_synced' => true,
        'successful_payments' => $payment_count,
        'donation_total' => number_format($total, 2, '.', ''),
    ];
}

function linka_nko_process_due_subscriptions(): array
{
    $recovered = 0;
    $recovery_failed = 0;
    foreach (linka_nko_get_charging_subscriptions(20) as $subscription) {
        if (linka_nko_recover_charging_subscription($subscription)) {
            $recovered++;
        } else {
            $recovery_failed++;
        }
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

    return [
        'ok' => true,
        'enabled' => true,
        'recovered' => $recovered,
        'recovery_failed' => $recovery_failed,
        'processed' => $processed,
        'failed' => $failed,
    ];
}

function linka_nko_charge_subscription(object $subscription): bool
{
    $current_subscription = linka_nko_get_subscription_by_id((int) $subscription->id);
    if ($current_subscription === null || (string) $current_subscription->status !== 'active') {
        return false;
    }

    $subscription = $current_subscription;
    $amount = (int) $subscription->amount_value;
    $payment_method_id = (string) $subscription->payment_method_id;
    if ($amount <= 0 || $payment_method_id === '') {
        linka_nko_update_subscription_status((int) $subscription->id, 'failed');
        return false;
    }

    $payment = linka_nko_prepare_subscription_charge($subscription);
    if ($payment === null) {
        return false;
    }

    return linka_nko_submit_recurring_payment($subscription, $payment);
}

function linka_nko_recover_charging_subscription(object $subscription): bool
{
    $current_subscription = linka_nko_get_subscription_by_id((int) $subscription->id);
    if ($current_subscription === null || (string) $current_subscription->status !== 'charging') {
        return false;
    }

    $payment = linka_nko_get_payment_by_id((int) $current_subscription->last_payment_id);
    if ($payment === null || (string) $payment->idempotence_key === '' || (string) $current_subscription->payment_method_id === '') {
        linka_nko_update_subscription_status((int) $current_subscription->id, 'failed');
        return false;
    }

    $yookassa_payment_id = (string) $payment->yookassa_payment_id;
    if ($yookassa_payment_id === '') {
        return linka_nko_submit_recurring_payment($current_subscription, $payment);
    }

    $response = linka_nko_yookassa_request('GET', 'payments/' . rawurlencode($yookassa_payment_id), null, 10);
    if (is_wp_error($response)) {
        error_log('YooKassa recurring recovery error: ' . $response->get_error_message());
        return false;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
        linka_nko_log_bad_yookassa_response($status_code, $body);
        if ($status_code >= 400 && $status_code < 500 && $status_code !== 429) {
            linka_nko_mark_payment_failed((int) $payment->id, 'recovery_rejected');
        }
        return false;
    }

    linka_nko_sync_payment_from_yookassa($body, (int) $payment->id);
    return true;
}

function linka_nko_submit_recurring_payment(object $subscription, object $payment): bool
{
    $amount = (int) $subscription->amount_value;
    $payment_method_id = (string) $subscription->payment_method_id;
    $payment_id = (int) $payment->id;
    $idempotence_key = (string) $payment->idempotence_key;
    if ($amount <= 0 || $payment_method_id === '' || $payment_id <= 0 || $idempotence_key === '') {
        linka_nko_mark_payment_failed($payment_id, 'invalid_recurring_attempt');
        return false;
    }

    $attempt_started_at = strtotime((string) $payment->created_at . ' UTC');
    if ($attempt_started_at === false || time() - $attempt_started_at >= 23 * HOUR_IN_SECONDS) {
        linka_nko_mark_payment_failed($payment_id, 'idempotence_window_expired');
        error_log('Recurring donation requires manual review for payment ' . $payment_id);
        return false;
    }

    $payload = linka_nko_build_recurring_charge_payload($amount, $payment_method_id, $payment_id, (int) $subscription->id);
    $response = linka_nko_yookassa_request('POST', 'payments', $payload, 20, $idempotence_key);
    if (is_wp_error($response)) {
        error_log('YooKassa recurring donation error: ' . $response->get_error_message());
        return false;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
        linka_nko_log_bad_yookassa_response($status_code, $body);
        if ($status_code >= 400 && $status_code < 500 && $status_code !== 429) {
            linka_nko_mark_payment_failed($payment_id, 'recurring_rejected');
        }
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

function linka_nko_yookassa_request(string $method, string $path, ?array $payload = null, int $timeout = 20, ?string $idempotence_key = null)
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
            'Idempotence-Key' => $idempotence_key ?: wp_generate_uuid4(),
        ],
    ];

    if ($payload !== null) {
        $args['body'] = wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    return wp_remote_request('https://api.yookassa.ru/v3/' . ltrim($path, '/'), $args);
}

function linka_nko_get_donation_amount(array $source): ?int
{
    $selected = sanitize_text_field(linka_nko_scalar_string($source['donation_amount'] ?? ''));
    if ($selected === 'custom') {
        $amount = (int) linka_nko_scalar_string($source['custom_amount'] ?? '0');
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
    $frequency = sanitize_key(linka_nko_scalar_string($source['donation_frequency'] ?? LINKA_NKO_DONATION_FREQUENCY_ONE_TIME));
    if ($frequency === LINKA_NKO_DONATION_FREQUENCY_MONTHLY) {
        return LINKA_NKO_DONATION_FREQUENCY_MONTHLY;
    }

    return LINKA_NKO_DONATION_FREQUENCY_ONE_TIME;
}

function linka_nko_scalar_string($value): string
{
    return is_scalar($value) ? (string) $value : '';
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
        idempotence_key char(36) NULL,
        amount_value decimal(12,2) NOT NULL,
        currency char(3) NOT NULL DEFAULT 'RUB',
        donor_name varchar(160) NOT NULL,
        donor_email varchar(190) NOT NULL,
        frequency varchar(20) NOT NULL DEFAULT 'one_time',
        status varchar(32) NOT NULL DEFAULT 'pending',
        payment_method_id varchar(128) NULL,
        payment_method_saved tinyint(1) NOT NULL DEFAULT 0,
        cancellation_reason varchar(100) NULL,
        thank_you_email_sent_at datetime NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        paid_at datetime NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY yookassa_payment_id (yookassa_payment_id),
        UNIQUE KEY idempotence_key (idempotence_key),
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
        activation_email_sent_at datetime NULL,
        cancellation_email_sent_at datetime NULL,
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

function linka_nko_get_request_token($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $token = sanitize_text_field((string) wp_unslash($value));
    $token = preg_replace('/[^A-Za-z0-9_-]/', '', $token) ?: '';

    if (strlen($token) < 32 || strlen($token) > 96) {
        return '';
    }

    return $token;
}

function linka_nko_generate_subscription_token(): string
{
    return wp_generate_password(48, false, false);
}

function linka_nko_hash_subscription_token(string $token): string
{
    return hash('sha256', $token);
}

function linka_nko_get_subscription_by_token(string $token): ?object
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$tables['subscriptions']} WHERE cancellation_token_hash = %s LIMIT 1",
        linka_nko_hash_subscription_token($token)
    ));

    return is_object($subscription) ? $subscription : null;
}

function linka_nko_subscription_manage_url(string $token, array $params = []): string
{
    $params = array_merge(['token' => $token], $params);

    return add_query_arg($params, home_url('/' . LINKA_NKO_DONATION_SUBSCRIPTION_PATH . '/'));
}

function linka_nko_insert_subscription(int $amount, string $donor_name, string $donor_email): ?int
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $now = linka_nko_utc_now();
    $token = linka_nko_generate_subscription_token();

    $inserted = $wpdb->insert($tables['subscriptions'], [
        'amount_value' => number_format($amount, 2, '.', ''),
        'currency' => 'RUB',
        'donor_name' => $donor_name,
        'donor_email' => $donor_email,
        'status' => 'pending',
        'cancellation_token_hash' => linka_nko_hash_subscription_token($token),
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
        'idempotence_key' => wp_generate_uuid4(),
        'amount_value' => number_format($amount, 2, '.', ''),
        'currency' => 'RUB',
        'donor_name' => $donor_name,
        'donor_email' => $donor_email,
        'frequency' => $frequency,
        'status' => 'pending',
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

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

    $subscription = $subscription_id > 0 ? linka_nko_get_subscription_by_id($subscription_id) : null;
    if ($subscription !== null && (string) $subscription->status === 'canceled') {
        $wpdb->update($tables['payments'], [
            'payment_method_id' => null,
            'updated_at' => linka_nko_utc_now(),
        ], $where, ['%s', '%s'], $where_format);
        return;
    }

    if ($subscription_id > 0) {
        linka_nko_sync_subscription_from_payment($subscription_id, $status, $payment_method_id, (bool) $payment_method_saved, $local_payment_id);
    }

    if ($status === 'succeeded' && $local_payment_id > 0) {
        linka_nko_maybe_send_one_time_thank_you_email($local_payment_id);
    }
}

function linka_nko_sync_subscription_from_payment(int $subscription_id, string $payment_status, string $payment_method_id, bool $payment_method_saved, int $local_payment_id): void
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $now = linka_nko_utc_now();
    $subscription = linka_nko_get_subscription_by_id($subscription_id);
    if ($subscription !== null && (string) $subscription->status === 'canceled') {
        return;
    }

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
        linka_nko_maybe_send_subscription_activation_email($subscription_id);
        return;
    }

    if ($payment_status === 'succeeded' && $subscription !== null && in_array((string) $subscription->status, ['active', 'charging'], true) && (string) $subscription->payment_method_id !== '') {
        $wpdb->update($tables['subscriptions'], [
            'status' => 'active',
            'last_charge_at' => $now,
            'next_charge_at' => linka_nko_next_monthly_charge_at($now),
            'last_payment_id' => $local_payment_id > 0 ? $local_payment_id : null,
            'failed_attempts' => 0,
            'updated_at' => $now,
        ], ['id' => $subscription_id], ['%s', '%s', '%s', '%d', '%d', '%s'], ['%d']);
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

function linka_nko_get_subscription_by_id(int $subscription_id): ?object
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $subscription = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['subscriptions']} WHERE id = %d", $subscription_id));

    return is_object($subscription) ? $subscription : null;
}

function linka_nko_get_payment_by_id(int $payment_id): ?object
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['payments']} WHERE id = %d", $payment_id));

    return is_object($payment) ? $payment : null;
}

function linka_nko_get_successful_donation_total(): float
{
    $api_total = get_option('linka_nko_donation_total_api', false);
    if ($api_total !== false && is_numeric($api_total)) {
        return (float) $api_total;
    }

    global $wpdb;
    $tables = linka_nko_donation_tables();
    $total = $wpdb->get_var("SELECT COALESCE(SUM(amount_value), 0) FROM {$tables['payments']} WHERE status = 'succeeded'");

    return (float) $total;
}

function linka_nko_register_donations_admin_page(): void
{
    add_menu_page(
        'Пожертвования',
        'Пожертвования',
        'manage_options',
        'linka-nko-donations',
        'linka_nko_render_donations_admin_page',
        'dashicons-heart',
        58
    );
}

function linka_nko_render_donations_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав.', '', ['response' => 403]);
    }

    global $wpdb;
    $tables = linka_nko_donation_tables();
    $total = linka_nko_get_successful_donation_total();
    $successful_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['payments']} WHERE status = 'succeeded'");
    $active_subscriptions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['subscriptions']} WHERE status = 'active'");
    $payments = $wpdb->get_results("SELECT * FROM {$tables['payments']} ORDER BY created_at DESC LIMIT 100");
    $subscriptions = $wpdb->get_results("SELECT * FROM {$tables['subscriptions']} ORDER BY created_at DESC LIMIT 50");
    ?>
    <div class="wrap linka-donations-admin">
      <h1>Пожертвования</h1>
      <p>YooKassa остается источником истины по платежам. Эта страница показывает локальные записи сайта для сверки, писем и регулярных пожертвований.</p>

      <div class="linka-donations-admin__summary">
        <div><strong><?php echo esc_html(linka_nko_format_amount($total)); ?> ₽</strong><span>успешных пожертвований</span></div>
        <div><strong><?php echo esc_html((string) $successful_count); ?></strong><span>успешных платежей</span></div>
        <div><strong><?php echo esc_html((string) $active_subscriptions); ?></strong><span>активных ежемесячных</span></div>
      </div>

      <h2>Последние платежи</h2>
      <table class="widefat striped">
        <thead><tr><th>ID</th><th>Дата</th><th>Сумма</th><th>Периодичность</th><th>Статус</th><th>Жертвователь</th><th>Email</th><th>YooKassa</th><th>Письмо</th></tr></thead>
        <tbody>
          <?php if ($payments === []) : ?>
            <tr><td colspan="9">Платежей пока нет.</td></tr>
          <?php else : ?>
            <?php foreach ($payments as $payment) : ?>
              <tr>
                <td><?php echo esc_html((string) $payment->id); ?></td>
                <td><?php echo esc_html(linka_nko_format_admin_datetime((string) $payment->created_at)); ?></td>
                <td><?php echo esc_html(linka_nko_format_amount((float) $payment->amount_value)); ?> ₽</td>
                <td><?php echo esc_html(linka_nko_frequency_label((string) $payment->frequency)); ?></td>
                <td><?php echo esc_html(linka_nko_payment_status_label((string) $payment->status)); ?></td>
                <td><?php echo esc_html((string) $payment->donor_name); ?></td>
                <td><a href="mailto:<?php echo esc_attr((string) $payment->donor_email); ?>"><?php echo esc_html((string) $payment->donor_email); ?></a></td>
                <td><?php echo esc_html((string) $payment->yookassa_payment_id); ?></td>
                <td><?php echo esc_html((string) $payment->thank_you_email_sent_at !== '' ? linka_nko_format_admin_datetime((string) $payment->thank_you_email_sent_at) : ''); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <h2>Ежемесячные пожертвования</h2>
      <table class="widefat striped">
        <thead><tr><th>ID</th><th>Создано</th><th>Сумма</th><th>Статус</th><th>Следующее списание</th><th>Жертвователь</th><th>Email</th><th>Payment method</th></tr></thead>
        <tbody>
          <?php if ($subscriptions === []) : ?>
            <tr><td colspan="8">Подписок пока нет.</td></tr>
          <?php else : ?>
            <?php foreach ($subscriptions as $subscription) : ?>
              <tr>
                <td><?php echo esc_html((string) $subscription->id); ?></td>
                <td><?php echo esc_html(linka_nko_format_admin_datetime((string) $subscription->created_at)); ?></td>
                <td><?php echo esc_html(linka_nko_format_amount((float) $subscription->amount_value)); ?> ₽</td>
                <td><?php echo esc_html(linka_nko_subscription_status_label((string) $subscription->status)); ?></td>
                <td><?php echo esc_html(linka_nko_format_admin_datetime((string) $subscription->next_charge_at)); ?></td>
                <td><?php echo esc_html((string) $subscription->donor_name); ?></td>
                <td><a href="mailto:<?php echo esc_attr((string) $subscription->donor_email); ?>"><?php echo esc_html((string) $subscription->donor_email); ?></a></td>
                <td><?php echo esc_html((string) $subscription->payment_method_id); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <style>
      .linka-donations-admin__summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin: 18px 0 28px; }
      .linka-donations-admin__summary div { padding: 16px; background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; }
      .linka-donations-admin__summary strong { display: block; font-size: 24px; line-height: 1.2; }
      .linka-donations-admin__summary span { color: #646970; }
      .linka-donations-admin table { margin-bottom: 28px; }
    </style>
    <?php
}

function linka_nko_maybe_send_one_time_thank_you_email(int $payment_id): void
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $payment = linka_nko_get_payment_by_id($payment_id);
    if ($payment === null || (string) $payment->frequency !== LINKA_NKO_DONATION_FREQUENCY_ONE_TIME) {
        return;
    }

    if ((string) $payment->status !== 'succeeded' || (string) $payment->thank_you_email_sent_at !== '') {
        return;
    }

    $now = linka_nko_utc_now();
    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE {$tables['payments']} SET thank_you_email_sent_at = %s, updated_at = %s WHERE id = %d AND thank_you_email_sent_at IS NULL",
        $now,
        $now,
        $payment_id
    ));
    if ($claimed !== 1) {
        return;
    }

    if (!linka_nko_send_one_time_thank_you_email($payment)) {
        $wpdb->update($tables['payments'], [
            'thank_you_email_sent_at' => null,
            'updated_at' => linka_nko_utc_now(),
        ], ['id' => $payment_id], ['%s', '%s'], ['%d']);
        error_log('Donation thank-you email failed for payment ' . $payment_id);
    }
}

function linka_nko_send_one_time_thank_you_email(object $payment): bool
{
    $amount = linka_nko_format_amount((float) $payment->amount_value);
    $body = "Здравствуйте!\n\n"
        . "Спасибо за добровольное пожертвование в пользу АНО «Линка».\n\n"
        . "Сумма пожертвования: {$amount} ₽\n"
        . "Назначение: поддержка уставной деятельности АНО «Линка»\n\n"
        . "Ваш вклад помогает развивать доступную среду и поддерживать программы альтернативной и дополнительной коммуникации для людей с ОВЗ.\n\n"
        . "Пожертвование не является оплатой товаров, работ или услуг.\n\n"
        . "Если у вас есть вопросы или пожертвование было отправлено ошибочно, напишите нам: feedback@linka.su\n\n"
        . "Спасибо!\nАНО «Линка»";

    return wp_mail((string) $payment->donor_email, 'Спасибо за пожертвование АНО «Линка»', $body);
}

function linka_nko_maybe_send_subscription_activation_email(int $subscription_id): void
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $subscription = linka_nko_get_subscription_by_id($subscription_id);
    if ($subscription === null || (string) $subscription->activation_email_sent_at !== '') {
        return;
    }

    if ((string) $subscription->status !== 'active' || (string) $subscription->payment_method_id === '') {
        return;
    }

    $token = linka_nko_generate_subscription_token();
    $now = linka_nko_utc_now();
    $updated = $wpdb->update($tables['subscriptions'], [
        'cancellation_token_hash' => linka_nko_hash_subscription_token($token),
        'updated_at' => $now,
    ], ['id' => $subscription_id], ['%s', '%s'], ['%d']);
    if ($updated === false) {
        error_log('Donation subscription token update failed for subscription ' . $subscription_id);
        return;
    }

    if (linka_nko_send_subscription_activation_email($subscription, $token)) {
        $wpdb->update($tables['subscriptions'], [
            'activation_email_sent_at' => $now,
            'updated_at' => $now,
        ], ['id' => $subscription_id], ['%s', '%s'], ['%d']);
    }
}

function linka_nko_cancel_subscription_record(object $subscription): void
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $subscription_id = (int) $subscription->id;
    $now = linka_nko_utc_now();

    if ((string) $subscription->status !== 'canceled') {
        $wpdb->update($tables['subscriptions'], [
            'status' => 'canceled',
            'payment_method_id' => null,
            'next_charge_at' => null,
            'canceled_at' => $now,
            'updated_at' => $now,
        ], ['id' => $subscription_id], ['%s', '%s', '%s', '%s', '%s'], ['%d']);

        $wpdb->update($tables['payments'], [
            'payment_method_id' => null,
            'updated_at' => $now,
        ], ['subscription_id' => $subscription_id], ['%s', '%s'], ['%d']);
    }

    $subscription = linka_nko_get_subscription_by_id($subscription_id) ?: $subscription;
    if ((string) $subscription->cancellation_email_sent_at !== '') {
        return;
    }

    if (linka_nko_send_subscription_cancellation_email($subscription)) {
        $wpdb->update($tables['subscriptions'], [
            'cancellation_email_sent_at' => $now,
            'updated_at' => $now,
        ], ['id' => $subscription_id], ['%s', '%s'], ['%d']);
    }
}

function linka_nko_send_subscription_activation_email(object $subscription, string $token): bool
{
    $amount = linka_nko_format_amount((float) $subscription->amount_value);
    $manage_url = linka_nko_subscription_manage_url($token);
    $body = "Здравствуйте!\n\n"
        . "Спасибо за поддержку АНО «Линка».\n\n"
        . "Вы подключили ежемесячное добровольное пожертвование:\n"
        . "Сумма: {$amount} ₽\n"
        . "Периодичность: один раз в месяц\n\n"
        . "Пожертвование не является оплатой товаров, работ или услуг.\n\n"
        . "Управлять ежемесячным пожертвованием и отключить последующие списания можно по ссылке:\n"
        . $manage_url . "\n\n"
        . "Если у вас есть вопросы, напишите нам: feedback@linka.su\n\n"
        . "Спасибо!\nАНО «Линка»";

    return wp_mail((string) $subscription->donor_email, 'Ежемесячное пожертвование АНО «Линка» подключено', $body);
}

function linka_nko_send_subscription_cancellation_email(object $subscription): bool
{
    $body = "Здравствуйте!\n\n"
        . "Ежемесячное добровольное пожертвование отключено.\n\n"
        . "Сохраненный способ оплаты удален из нашей системы. Новых списаний по этому ежемесячному пожертвованию не будет.\n\n"
        . "Спасибо за поддержку АНО «Линка».\n\n"
        . "Если у вас есть вопросы, напишите нам: feedback@linka.su";

    return wp_mail((string) $subscription->donor_email, 'Ежемесячное пожертвование АНО «Линка» отключено', $body);
}

function linka_nko_subscription_status_label(string $status): string
{
    if ($status === 'active') {
        return 'Активно';
    }
    if ($status === 'canceled') {
        return 'Отключено';
    }
    if ($status === 'pending') {
        return 'Ожидает подтверждения';
    }
    if ($status === 'charging') {
        return 'Выполняется списание';
    }

    return 'Не активно';
}

function linka_nko_payment_status_label(string $status): string
{
    if ($status === 'succeeded') {
        return 'Успешен';
    }
    if ($status === 'pending') {
        return 'Ожидает оплаты';
    }
    if ($status === 'canceled') {
        return 'Отменен';
    }
    if ($status === 'failed') {
        return 'Ошибка';
    }

    return $status;
}

function linka_nko_frequency_label(string $frequency): string
{
    if ($frequency === LINKA_NKO_DONATION_FREQUENCY_MONTHLY) {
        return 'Ежемесячно';
    }

    return 'Разово';
}

function linka_nko_format_amount(float $amount): string
{
    return number_format_i18n($amount, 0);
}

function linka_nko_format_admin_datetime(string $mysql_datetime): string
{
    if ($mysql_datetime === '') {
        return '';
    }

    $timestamp = strtotime($mysql_datetime . ' UTC');
    if ($timestamp === false) {
        return '';
    }

    return wp_date('d.m.Y H:i', $timestamp);
}

function linka_nko_format_date(string $mysql_datetime): string
{
    if ($mysql_datetime === '') {
        return '';
    }

    $timestamp = strtotime($mysql_datetime . ' UTC');
    if ($timestamp === false) {
        return '';
    }

    return wp_date('d.m.Y', $timestamp);
}

function linka_nko_mask_email(string $email): string
{
    if (!is_email($email)) {
        return '';
    }

    [$local, $domain] = explode('@', $email, 2);
    $prefix = substr($local, 0, min(2, strlen($local)));

    return $prefix . str_repeat('*', max(2, strlen($local) - strlen($prefix))) . '@' . $domain;
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

function linka_nko_get_charging_subscriptions(int $limit): array
{
    global $wpdb;
    $tables = linka_nko_donation_tables();

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$tables['subscriptions']} WHERE status = 'charging' ORDER BY updated_at ASC LIMIT %d",
        $limit
    ));
}

function linka_nko_prepare_subscription_charge(object $subscription): ?object
{
    global $wpdb;
    $tables = linka_nko_donation_tables();
    $subscription_id = (int) $subscription->id;
    $next_charge_at = (string) $subscription->next_charge_at;
    if ($subscription_id <= 0 || $next_charge_at === '') {
        return null;
    }

    $wpdb->query('START TRANSACTION');
    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE {$tables['subscriptions']} SET status = 'charging', updated_at = %s WHERE id = %d AND status = 'active' AND next_charge_at = %s AND next_charge_at <= %s",
        linka_nko_utc_now(),
        $subscription_id,
        $next_charge_at,
        linka_nko_utc_now()
    ));
    if ($claimed !== 1) {
        $wpdb->query('ROLLBACK');
        return null;
    }

    $payment_id = linka_nko_insert_payment(
        (int) $subscription->amount_value,
        (string) $subscription->donor_name,
        (string) $subscription->donor_email,
        LINKA_NKO_DONATION_FREQUENCY_MONTHLY,
        $subscription_id
    );
    if ($payment_id === null) {
        $wpdb->query('ROLLBACK');
        return null;
    }

    $linked = $wpdb->update($tables['subscriptions'], [
        'last_payment_id' => $payment_id,
        'updated_at' => linka_nko_utc_now(),
    ], ['id' => $subscription_id, 'status' => 'charging'], ['%d', '%s'], ['%d', '%s']);
    if ($linked !== 1) {
        $wpdb->query('ROLLBACK');
        return null;
    }

    $wpdb->query('COMMIT');

    return linka_nko_get_payment_by_id($payment_id);
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
        $subscription = linka_nko_get_subscription_by_id($subscription_id);
        if ($subscription !== null && (string) $subscription->status === 'canceled') {
            return;
        }

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
