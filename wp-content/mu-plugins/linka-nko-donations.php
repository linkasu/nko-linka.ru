<?php
/**
 * Voluntary donation form and YooKassa redirect integration.
 */

const LINKA_NKO_DONATION_AMOUNTS = [500, 1000, 3000, 5000];
const LINKA_NKO_DONATION_MIN_AMOUNT = 100;
const LINKA_NKO_DONATION_MAX_AMOUNT = 300000;

add_shortcode('linka_donation_form', static function (): string {
    $shop_id = trim((string) getenv('YOOKASSA_SHOP_ID'));
    $secret_key = trim((string) getenv('YOOKASSA_SECRET_KEY'));
    $is_configured = $shop_id !== '' && $secret_key !== '';
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
          <span>Я ознакомился с <a href="<?php echo esc_url(home_url('/donation-offer/')); ?>" target="_blank" rel="noopener">публичной офертой</a> и <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>" target="_blank" rel="noopener">политикой обработки персональных данных</a>, согласен на обработку персональных данных и понимаю, что пожертвование не является оплатой товаров или услуг.</span>
        </label>

        <p class="donation-form__note">После нажатия кнопки откроется платежная страница YooKassa.</p>

        <button class="button" type="submit" <?php disabled(!$is_configured); ?>>Пожертвовать</button>
      </form>
    </section>
    <?php
    return (string) ob_get_clean();
});

add_action('admin_post_nopriv_linka_nko_create_donation', 'linka_nko_create_donation');
add_action('admin_post_linka_nko_create_donation', 'linka_nko_create_donation');

function linka_nko_create_donation(): void
{
    if (!isset($_POST['linka_nko_donation_nonce']) || !wp_verify_nonce((string) $_POST['linka_nko_donation_nonce'], 'linka_nko_create_donation')) {
        wp_die('Некорректный запрос.', '', ['response' => 400]);
    }

    $shop_id = trim((string) getenv('YOOKASSA_SHOP_ID'));
    $secret_key = trim((string) getenv('YOOKASSA_SECRET_KEY'));
    if ($shop_id === '' || $secret_key === '') {
        wp_die('Прием пожертвований еще настраивается.', '', ['response' => 503]);
    }

    $request = wp_unslash($_POST);
    $amount = linka_nko_get_donation_amount($request);
    $donor_name = linka_nko_sanitize_donor_name((string) ($request['donor_name'] ?? ''));
    $donor_email = sanitize_email((string) ($request['donor_email'] ?? ''));
    $has_consent = isset($_POST['donor_consent']) && (string) $_POST['donor_consent'] === '1';

    if ($amount === null || $donor_name === '' || $donor_email === '' || !is_email($donor_email) || !$has_consent) {
        wp_die('Проверьте сумму, ФИО, email и согласие с условиями пожертвования.', '', ['response' => 400]);
    }

    $payload = linka_nko_build_yookassa_payload($amount, $donor_name, $donor_email);
    $response = wp_remote_post('https://api.yookassa.ru/v3/payments', [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($shop_id . ':' . $secret_key),
            'Content-Type' => 'application/json',
            'Idempotence-Key' => wp_generate_uuid4(),
        ],
        'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    if (is_wp_error($response)) {
        error_log('YooKassa donation error: ' . $response->get_error_message());
        wp_die('Не удалось создать платеж. Попробуйте позже.', '', ['response' => 502]);
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
        error_log('YooKassa donation bad response: HTTP ' . $status_code);
        wp_die('Платежный провайдер вернул ошибку. Попробуйте позже.', '', ['response' => 502]);
    }

    $confirmation_url = $body['confirmation']['confirmation_url'] ?? '';
    if (!is_string($confirmation_url) || $confirmation_url === '') {
        error_log('YooKassa donation missing confirmation_url');
        wp_die('Платеж создан без ссылки подтверждения. Напишите нам на feedback@linka.su.', '', ['response' => 502]);
    }

    wp_redirect(esc_url_raw($confirmation_url), 303);
    exit;
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

function linka_nko_sanitize_donor_name(string $name): string
{
    $name = trim(wp_strip_all_tags($name));
    $name = preg_replace('/\s+/u', ' ', $name) ?: '';
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 160);
    }

    return substr($name, 0, 160);
}

function linka_nko_build_yookassa_payload(int $amount, string $donor_name, string $donor_email): array
{
    $formatted_amount = number_format($amount, 2, '.', '');
    $description = 'Добровольное пожертвование на уставную деятельность АНО Линка';
    $payload = [
        'amount' => [
            'value' => $formatted_amount,
            'currency' => 'RUB',
        ],
        'capture' => true,
        'confirmation' => [
            'type' => 'redirect',
            'return_url' => getenv('YOOKASSA_RETURN_URL') ?: home_url('/donate/?donation=started'),
        ],
        'description' => $description,
        'metadata' => [
            'purpose' => 'statutory_voluntary_donation',
            'donor_name' => $donor_name,
            'donor_email' => $donor_email,
            'site' => home_url('/'),
        ],
    ];

    if (filter_var(getenv('YOOKASSA_SEND_RECEIPT'), FILTER_VALIDATE_BOOLEAN)) {
        $payload['receipt'] = [
            'customer' => [
                'full_name' => $donor_name,
                'email' => $donor_email,
            ],
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

        $tax_system_code = getenv('YOOKASSA_TAX_SYSTEM_CODE');
        if ($tax_system_code !== false && $tax_system_code !== '') {
            $payload['receipt']['tax_system_code'] = (int) $tax_system_code;
        }
    }

    return $payload;
}
