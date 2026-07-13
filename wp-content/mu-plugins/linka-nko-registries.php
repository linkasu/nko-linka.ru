<?php
/**
 * Private YooKassa registry archive and monthly accounting exports.
 */

const LINKA_NKO_REGISTRY_SCHEMA_VERSION = '20260713-3';
const LINKA_NKO_REGISTRY_IMPORT_PATH = '/internal/import-registries';
const LINKA_NKO_REGISTRY_MAX_FILE_SIZE = 5242880;
const LINKA_NKO_REGISTRY_CONTRACT = 'НЭК.451387.01';
const LINKA_NKO_REGISTRY_SHOP_ID = '1403902';

add_action('init', 'linka_nko_ensure_registry_schema', 2);
add_action('admin_menu', 'linka_nko_register_registries_admin_page');
add_action('admin_post_linka_nko_upload_registry', 'linka_nko_upload_registry');
add_action('admin_post_linka_nko_download_registry', 'linka_nko_download_registry');
add_action('admin_post_linka_nko_download_monthly_registry', 'linka_nko_download_monthly_registry');
add_action('admin_post_linka_nko_import_registries', 'linka_nko_admin_import_registries');
add_action('template_redirect', 'linka_nko_run_internal_registry_import', 1);

function linka_nko_registry_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'linka_yookassa_registries';
}

function linka_nko_ensure_registry_schema(): void
{
    $previous_version = (string) get_option('linka_nko_registry_schema_version');
    if ($previous_version === LINKA_NKO_REGISTRY_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $table = linka_nko_registry_table();
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        register_date date NOT NULL,
        register_type varchar(20) NOT NULL,
        contract_number varchar(40) NOT NULL,
        shop_id varchar(32) NOT NULL,
        original_filename varchar(255) NOT NULL,
        object_key varchar(512) NOT NULL,
        content_sha256 char(64) NOT NULL,
        storage_status varchar(20) NOT NULL DEFAULT 'pending',
        row_count int(10) unsigned NOT NULL DEFAULT 0,
        gross_total decimal(14,2) NOT NULL DEFAULT 0,
        net_total decimal(14,2) NOT NULL DEFAULT 0,
        commission_total decimal(14,2) NOT NULL DEFAULT 0,
        commission_vat_total decimal(14,2) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY content_sha256 (content_sha256),
        UNIQUE KEY register_date_type (register_date,register_type),
        KEY storage_status (storage_status)
    ) {$charset_collate};");

    if (in_array($previous_version, ['20260713-1', '20260713-2'], true)) {
        $wpdb->query("UPDATE {$table} SET storage_status = 'stored' WHERE storage_status = 'pending'");
    }

    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    $columns = $table_exists === $table ? $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0) : [];
    $indexes = $table_exists === $table ? $wpdb->get_results("SHOW INDEX FROM {$table}") : [];
    $required_columns = ['id', 'register_date', 'register_type', 'object_key', 'content_sha256', 'storage_status', 'created_at', 'updated_at'];
    $has_unique_date_type = false;
    foreach ($indexes as $index) {
        if ((string) $index->Key_name === 'register_date_type' && (int) $index->Non_unique === 0) {
            $has_unique_date_type = true;
            break;
        }
    }
    if ($table_exists === $table && array_diff($required_columns, $columns) === [] && $has_unique_date_type) {
        update_option('linka_nko_registry_schema_version', LINKA_NKO_REGISTRY_SCHEMA_VERSION, false);
    } else {
        error_log('YooKassa registry schema migration failed.');
    }
}

function linka_nko_register_registries_admin_page(): void
{
    add_menu_page(
        'Реестры YooKassa',
        'Реестры',
        'manage_options',
        'linka-nko-registries',
        'linka_nko_render_registries_admin_page',
        'dashicons-media-spreadsheet',
        59
    );
}

function linka_nko_render_registries_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав.', '', ['response' => 403]);
    }

    global $wpdb;
    $table = linka_nko_registry_table();
    $registries = $wpdb->get_results("SELECT * FROM {$table} WHERE storage_status = 'stored' ORDER BY register_date DESC, register_type ASC LIMIT 200");
    $month = linka_nko_registry_parse_month((string) ($_GET['month'] ?? '')) ?: gmdate('Y-m');
    $notice = sanitize_key((string) ($_GET['registry_notice'] ?? ''));
    ?>
    <div class="wrap linka-registries-admin">
      <h1>Реестры YooKassa</h1>
      <p>Исходные CSV хранятся в приватном Object Storage. Скачивание доступно только администраторам сайта.</p>

      <?php if ($notice === 'uploaded') : ?>
        <div class="notice notice-success is-dismissible"><p>Реестр загружен.</p></div>
      <?php elseif ($notice === 'duplicate') : ?>
        <div class="notice notice-info is-dismissible"><p>Такой реестр уже сохранен.</p></div>
      <?php elseif ($notice === 'imported') : ?>
        <div class="notice notice-success is-dismissible"><p>Почта проверена, новые реестры импортированы.</p></div>
      <?php elseif ($notice === 'error') : ?>
        <div class="notice notice-error is-dismissible"><p>Не удалось обработать реестр. Проверьте формат файла и конфигурацию.</p></div>
      <?php endif; ?>

      <div class="linka-registries-admin__cards">
        <section>
          <h2>Сводный реестр за месяц</h2>
          <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="linka_nko_download_monthly_registry">
            <?php wp_nonce_field('linka_nko_download_monthly_registry', 'linka_nko_registry_nonce'); ?>
            <label>Месяц <input type="month" name="month" value="<?php echo esc_attr($month); ?>" required></label>
            <?php submit_button('Скачать сводный CSV', 'primary', 'submit', false); ?>
          </form>
          <p class="description">В файл включаются все операции из ежедневных реестров платежей и возвратов за выбранный месяц.</p>
        </section>

        <section>
          <h2>Загрузить реестр вручную</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="linka_nko_upload_registry">
            <?php wp_nonce_field('linka_nko_upload_registry', 'linka_nko_registry_nonce'); ?>
            <input type="file" name="registry_file" accept=".csv,text/csv" required>
            <?php submit_button('Загрузить CSV', 'secondary', 'submit', false); ?>
          </form>
        </section>

        <section>
          <h2>Получить из почты</h2>
          <p>Адрес: <code>reestry@nkolinka.ru</code></p>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="linka_nko_import_registries">
            <?php wp_nonce_field('linka_nko_import_registries', 'linka_nko_registry_nonce'); ?>
            <?php submit_button('Проверить почту', 'secondary', 'submit', false); ?>
          </form>
        </section>
      </div>

      <h2>Сохраненные реестры</h2>
      <table class="widefat striped">
        <thead><tr><th>Дата</th><th>Тип</th><th>Операций</th><th>Сумма</th><th>За вычетом комиссии</th><th>Комиссия</th><th>НДС с комиссии</th><th>Файл</th></tr></thead>
        <tbody>
          <?php if ($registries === []) : ?>
            <tr><td colspan="8">Реестров пока нет.</td></tr>
          <?php else : ?>
            <?php foreach ($registries as $registry) : ?>
              <tr>
                <td><?php echo esc_html((string) $registry->register_date); ?></td>
                <td><?php echo esc_html(linka_nko_registry_type_label((string) $registry->register_type)); ?></td>
                <td><?php echo esc_html((string) $registry->row_count); ?></td>
                <td><?php echo esc_html(number_format((float) $registry->gross_total, 2, ',', ' ')); ?> ₽</td>
                <td><?php echo esc_html(number_format((float) $registry->net_total, 2, ',', ' ')); ?> ₽</td>
                <td><?php echo esc_html(number_format((float) $registry->commission_total, 2, ',', ' ')); ?> ₽</td>
                <td><?php echo esc_html(number_format((float) $registry->commission_vat_total, 2, ',', ' ')); ?> ₽</td>
                <td><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=linka_nko_download_registry&id=' . (int) $registry->id), 'linka_nko_download_registry_' . (int) $registry->id)); ?>"><?php echo esc_html((string) $registry->original_filename); ?></a></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <style>
      .linka-registries-admin__cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin:20px 0 28px; }
      .linka-registries-admin__cards section { background:#fff; border:1px solid #c3c4c7; border-radius:8px; padding:16px; }
      .linka-registries-admin__cards h2 { margin-top:0; }
      .linka-registries-admin__cards form { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
    </style>
    <?php
}

function linka_nko_upload_registry(): void
{
    linka_nko_registry_require_admin('linka_nko_upload_registry');
    $file = $_FILES['registry_file'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        linka_nko_registry_admin_redirect('error');
    }

    $size = (int) ($file['size'] ?? 0);
    $tmp_name = (string) ($file['tmp_name'] ?? '');
    $name = sanitize_file_name((string) ($file['name'] ?? 'registry.csv'));
    if ($size <= 0 || $size > LINKA_NKO_REGISTRY_MAX_FILE_SIZE || $tmp_name === '' || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
        linka_nko_registry_admin_redirect('error');
    }

    $content = file_get_contents($tmp_name);
    if (!is_string($content)) {
        linka_nko_registry_admin_redirect('error');
    }

    $result = linka_nko_store_registry($name, $content);
    linka_nko_registry_admin_redirect(is_wp_error($result) ? 'error' : ($result['duplicate'] ? 'duplicate' : 'uploaded'));
}

function linka_nko_admin_import_registries(): void
{
    linka_nko_registry_require_admin('linka_nko_import_registries');
    $result = linka_nko_import_registries_from_mail();
    linka_nko_registry_admin_redirect(is_wp_error($result) ? 'error' : 'imported');
}

function linka_nko_download_registry(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав.', '', ['response' => 403]);
    }

    $id = (int) ($_GET['id'] ?? 0);
    check_admin_referer('linka_nko_download_registry_' . $id);
    global $wpdb;
    $registry = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . linka_nko_registry_table() . ' WHERE id = %d', $id));
    if (!is_object($registry)) {
        wp_die('Реестр не найден.', '', ['response' => 404]);
    }

    $content = linka_nko_registry_get_content($registry);
    if (is_wp_error($content)) {
        wp_die('Не удалось получить реестр из хранилища.', '', ['response' => 502]);
    }

    linka_nko_registry_send_download((string) $registry->original_filename, $content);
}

function linka_nko_download_monthly_registry(): void
{
    linka_nko_registry_require_admin('linka_nko_download_monthly_registry');
    $month = linka_nko_registry_parse_month((string) ($_GET['month'] ?? ''));
    if ($month === '') {
        wp_die('Некорректный месяц.', '', ['response' => 400]);
    }

    global $wpdb;
    $table = linka_nko_registry_table();
    $registries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE storage_status = 'stored' AND register_date >= %s AND register_date < DATE_ADD(%s, INTERVAL 1 MONTH) ORDER BY register_date ASC, register_type ASC",
        $month . '-01',
        $month . '-01'
    ));
    if ($registries === []) {
        wp_die('За выбранный месяц реестров нет.', '', ['response' => 404]);
    }

    $parsed_registries = [];
    $all_headers = [];
    foreach ($registries as $registry) {
        $content = linka_nko_registry_get_content($registry);
        if (is_wp_error($content)) {
            wp_die('Не удалось получить один из реестров.', '', ['response' => 502]);
        }
        $parsed = linka_nko_parse_registry_csv((string) $registry->original_filename, $content);
        if (is_wp_error($parsed)) {
            wp_die('Один из сохраненных реестров поврежден.', '', ['response' => 500]);
        }
        foreach ($parsed['headers'] as $header) {
            if (!in_array($header, $all_headers, true)) {
                $all_headers[] = $header;
            }
        }
        $parsed_registries[] = [$registry, $parsed];
    }

    $stream = fopen('php://temp', 'w+');
    if ($stream === false) {
        wp_die('Не удалось сформировать файл.', '', ['response' => 500]);
    }
    fwrite($stream, "\xEF\xBB\xBF");
    $export_headers = array_map('linka_nko_registry_safe_csv_value', array_merge(['Тип реестра', 'Дата реестра', 'Исходный файл'], $all_headers));
    fputcsv($stream, $export_headers, ';', '"', '\\');
    foreach ($parsed_registries as [$registry, $parsed]) {
        foreach ($parsed['rows'] as $row) {
            $values = array_map('linka_nko_registry_safe_csv_value', [linka_nko_registry_type_label((string) $registry->register_type), (string) $registry->register_date, (string) $registry->original_filename]);
            foreach ($all_headers as $header) {
                $values[] = linka_nko_registry_safe_csv_value((string) ($row[$header] ?? ''));
            }
            fputcsv($stream, $values, ';', '"', '\\');
        }
    }
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);
    if (!is_string($content)) {
        wp_die('Не удалось сформировать файл.', '', ['response' => 500]);
    }

    linka_nko_registry_send_download('yookassa-registries-' . $month . '.csv', $content);
}

function linka_nko_run_internal_registry_import(): void
{
    $path = (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path !== LINKA_NKO_REGISTRY_IMPORT_PATH) {
        return;
    }
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        wp_send_json(['ok' => false, 'error' => 'method not allowed'], 405);
    }

    $result = linka_nko_import_registries_from_mail();
    if (is_wp_error($result)) {
        error_log('YooKassa registry import failed: ' . $result->get_error_code());
        wp_send_json(['ok' => false, 'error' => $result->get_error_code()], 502);
    }
    wp_send_json(array_merge(['ok' => true], $result), 200);
}

function linka_nko_store_registry(string $filename, string $content)
{
    if (strlen($content) > LINKA_NKO_REGISTRY_MAX_FILE_SIZE) {
        return new WP_Error('registry_too_large', 'Registry file is too large.');
    }
    $parsed = linka_nko_parse_registry_csv($filename, $content);
    if (is_wp_error($parsed)) {
        return $parsed;
    }

    $content = $parsed['normalized_content'];
    $hash = hash('sha256', $content);
    global $wpdb;
    $table = linka_nko_registry_table();
    $date = $parsed['date'];
    $key = sprintf('yookassa/%s/%s/%s/%s-%s.csv', substr($date, 0, 4), substr($date, 5, 2), $parsed['type'], $date, substr($hash, 0, 12));
    $now = gmdate('Y-m-d H:i:s');
    $record = [
        'register_date' => $date,
        'register_type' => $parsed['type'],
        'contract_number' => $parsed['contract'],
        'shop_id' => $parsed['shop_id'],
        'original_filename' => sanitize_file_name($filename),
        'object_key' => $key,
        'content_sha256' => $hash,
        'storage_status' => 'pending',
        'row_count' => count($parsed['rows']),
        'gross_total' => number_format($parsed['gross_total'], 2, '.', ''),
        'net_total' => number_format($parsed['net_total'], 2, '.', ''),
        'commission_total' => number_format($parsed['commission_total'], 2, '.', ''),
        'commission_vat_total' => number_format($parsed['commission_vat_total'], 2, '.', ''),
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s', '%s'];

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE register_date = %s AND register_type = %s LIMIT 1",
        $date,
        $parsed['type']
    ));
    if (is_object($existing)) {
        if (!hash_equals((string) $existing->content_sha256, $hash)) {
            return new WP_Error('registry_date_conflict', 'A different registry already exists for this date and type.');
        }
        if ((string) $existing->storage_status === 'stored') {
            return ['duplicate' => true, 'id' => (int) $existing->id];
        }
        $status = (string) $existing->storage_status;
        $claimable = $status === 'failed';
        if ($status === 'pending') {
            $updated_at = strtotime((string) $existing->updated_at . ' UTC');
            $claimable = $updated_at !== false && $updated_at <= time() - 15 * MINUTE_IN_SECONDS;
        }
        if (!$claimable) {
            return new WP_Error('registry_in_progress', 'Registry storage is already in progress.');
        }
        $claimed = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET storage_status = 'pending', updated_at = %s WHERE id = %d AND storage_status = %s AND updated_at = %s",
            $now,
            (int) $existing->id,
            $status,
            (string) $existing->updated_at
        ));
        if ($claimed !== 1) {
            return new WP_Error('registry_in_progress', 'Registry storage is already in progress.');
        }
        $registry_id = (int) $existing->id;
    } else {
        $inserted = $wpdb->insert($table, $record, $formats);
        if (!$inserted) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE register_date = %s AND register_type = %s LIMIT 1",
                $date,
                $parsed['type']
            ));
            if (is_object($existing) && hash_equals((string) $existing->content_sha256, $hash) && (string) $existing->storage_status === 'stored') {
                return ['duplicate' => true, 'id' => (int) $existing->id];
            }
            return new WP_Error(is_object($existing) ? 'registry_in_progress' : 'registry_database_error', 'Could not reserve registry metadata.');
        }
        $registry_id = (int) $wpdb->insert_id;
    }

    $uploaded = linka_nko_registry_s3_request('PUT', $key, $content, 'text/csv; charset=utf-8');
    if (is_wp_error($uploaded)) {
        $wpdb->update($table, ['storage_status' => 'failed', 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $registry_id, 'storage_status' => 'pending'], ['%s', '%s'], ['%d', '%s']);
        return $uploaded;
    }
    $stored = $wpdb->update($table, ['storage_status' => 'stored', 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $registry_id, 'storage_status' => 'pending'], ['%s', '%s'], ['%d', '%s']);
    if ($stored !== 1) {
        return new WP_Error('registry_database_error', 'Could not finalize registry metadata.');
    }

    return ['duplicate' => false, 'id' => $registry_id];
}

function linka_nko_parse_registry_csv(string $filename, string $content)
{
    $content = linka_nko_registry_utf8($content);
    if ($content === '') {
        return new WP_Error('registry_empty', 'Registry file is empty.');
    }

    $stream = fopen('php://temp', 'w+');
    if ($stream === false || fwrite($stream, $content) === false) {
        return new WP_Error('registry_invalid', 'Registry file cannot be read.');
    }
    rewind($stream);
    $title_line = fgets($stream);
    $date_line = fgets($stream);
    $headers = fgetcsv($stream, 0, ';', '"', '\\');
    if (!is_string($title_line) || !is_string($date_line) || !is_array($headers)) {
        fclose($stream);
        return new WP_Error('registry_invalid', 'Registry file is invalid.');
    }

    $title = trim($title_line);
    if (!preg_match('/^РЕЕСТР\s+(ПЛАТЕЖЕЙ|ВОЗВРАТОВ)\s+ПО\s+ДОГОВОРУ\s+(.+?)\s+\((\d+)\)$/ui', $title, $matches)) {
        fclose($stream);
        return new WP_Error('registry_title_invalid', 'Registry title is invalid.');
    }
    $type = mb_strtoupper($matches[1], 'UTF-8') === 'ПЛАТЕЖЕЙ' ? 'payments' : 'refunds';
    $contract = trim($matches[2]);
    $shop_id = trim($matches[3]);
    if ($contract !== LINKA_NKO_REGISTRY_CONTRACT || $shop_id !== LINKA_NKO_REGISTRY_SHOP_ID) {
        fclose($stream);
        return new WP_Error('registry_identity_mismatch', 'Registry contract or shop does not match.');
    }

    if (!preg_match('/^Дата\s+(?:платежей|возвратов):\s*(\d{4}-\d{2}-\d{2})$/ui', trim($date_line), $date_matches)) {
        fclose($stream);
        return new WP_Error('registry_date_invalid', 'Registry date is invalid.');
    }
    $date = $date_matches[1];
    if (!linka_nko_registry_valid_date($date)) {
        fclose($stream);
        return new WP_Error('registry_date_invalid', 'Registry date is invalid.');
    }

    if (!preg_match('/^yoomoney-(payments|refunds)-' . preg_quote(LINKA_NKO_REGISTRY_SHOP_ID, '/') . '-(\d{4}-\d{2}-\d{2})\.csv$/i', basename($filename), $filename_matches)
        || strtolower($filename_matches[1]) !== $type
        || $filename_matches[2] !== $date) {
        fclose($stream);
        return new WP_Error('registry_filename_invalid', 'Registry filename does not match its contents.');
    }

    $headers = array_map(static fn($value): string => trim((string) $value), $headers);
    $required_headers = $type === 'payments'
        ? ['Идентификатор платежа', 'Сумма платежа', 'Валюта платежа', 'Сумма за вычетом комиссии и НДС', 'Сумма комиссии без НДС', 'НДС с комиссии']
        : ['Идентификатор возврата', 'Идентификатор платежа', 'Сумма возврата', 'Валюта возврата'];
    if ($headers === [] || count($headers) !== count(array_unique($headers)) || array_diff($required_headers, $headers) !== []) {
        fclose($stream);
        return new WP_Error('registry_headers_invalid', 'Registry headers are invalid.');
    }

    $rows = [];
    $identifiers = [];
    $identifier_column = $type === 'payments' ? 'Идентификатор платежа' : 'Идентификатор возврата';
    while (!feof($stream)) {
        $position = ftell($stream);
        $line = fgets($stream);
        if ($line === false || trim($line) === '') {
            break;
        }
        fseek($stream, $position);
        $values = fgetcsv($stream, 0, ';', '"', '\\');
        if (!is_array($values)) {
            fclose($stream);
            return new WP_Error('registry_row_invalid', 'Registry row cannot be read.');
        }
        if (count($values) !== count($headers)) {
            fclose($stream);
            return new WP_Error('registry_row_invalid', 'Registry row has an invalid number of columns.');
        }
        $row = array_combine($headers, array_map(static fn($value): string => (string) $value, $values));
        if (!is_array($row)) {
            fclose($stream);
            return new WP_Error('registry_row_invalid', 'Registry row cannot be mapped.');
        }
        $identifier = trim((string) $row[$identifier_column]);
        if ($identifier === '' || isset($identifiers[$identifier])) {
            fclose($stream);
            return new WP_Error('registry_identifier_invalid', 'Registry operation identifier is missing or duplicated.');
        }
        $identifiers[$identifier] = true;
        foreach ($row as $header => $value) {
            if (preg_match('/^(?:Сумма|НДС)/u', $header) === 1 && !linka_nko_registry_numeric($value)) {
                fclose($stream);
                return new WP_Error('registry_amount_invalid', 'Registry amount is invalid.');
            }
        }
        $currency_column = $type === 'payments' ? 'Валюта платежа' : 'Валюта возврата';
        if (isset($row[$currency_column]) && trim((string) $row[$currency_column]) !== 'RUB') {
            fclose($stream);
            return new WP_Error('registry_currency_invalid', 'Registry currency is invalid.');
        }
        $rows[] = $row;
    }

    $gross_candidates = $type === 'payments' ? ['Сумма платежа'] : ['Сумма возврата'];
    $gross_total_cents = linka_nko_registry_sum_columns_cents($rows, $gross_candidates);
    $footer = (string) stream_get_contents($stream);
    fclose($stream);
    $summary_label = $type === 'payments' ? 'Сумма принятых платежей' : 'Сумма возвратов';
    $count_label = $type === 'payments' ? 'Число платежей' : 'Число возвратов';
    if (!preg_match('/^' . preg_quote($summary_label, '/') . ':\s*([0-9]+(?:[.,][0-9]{1,2})?)\s+RUB\s*$/mi', $footer, $summary_match)
        || !preg_match('/^' . preg_quote($count_label, '/') . ':\s*(\d+)\s*$/mi', $footer, $count_match)
        || $gross_total_cents !== linka_nko_registry_amount_to_cents($summary_match[1])
        || count($rows) !== (int) $count_match[1]) {
        return new WP_Error('registry_summary_mismatch', 'Registry summary does not match its rows.');
    }

    return [
        'type' => $type,
        'contract' => $contract,
        'shop_id' => $shop_id,
        'date' => $date,
        'headers' => $headers,
        'rows' => $rows,
        'gross_total' => (float) ($gross_total_cents / 100),
        'net_total' => (float) (linka_nko_registry_sum_columns_cents($rows, ['Сумма за вычетом комиссии и НДС', 'Сумма возврата за вычетом комиссии и НДС']) / 100),
        'commission_total' => (float) (linka_nko_registry_sum_columns_cents($rows, ['Сумма комиссии без НДС']) / 100),
        'commission_vat_total' => (float) (linka_nko_registry_sum_columns_cents($rows, ['НДС с комиссии']) / 100),
        'normalized_content' => $content,
    ];
}

function linka_nko_registry_numeric(string $value): bool
{
    return linka_nko_registry_amount_to_cents($value) !== null;
}

function linka_nko_registry_amount_to_cents(string $value): ?int
{
    $value = trim(str_replace(',', '.', $value));
    if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
        return null;
    }
    $cents = ((int) $matches[2] * 100) + (int) str_pad((string) ($matches[3] ?? ''), 2, '0');
    return $matches[1] === '-' ? -$cents : $cents;
}

function linka_nko_registry_sum_columns_cents(array $rows, array $candidates): int
{
    $total = 0;
    foreach ($rows as $row) {
        foreach ($candidates as $column) {
            if (isset($row[$column])) {
                $cents = linka_nko_registry_amount_to_cents((string) $row[$column]);
                $total += $cents ?? 0;
                break;
            }
        }
    }
    return $total;
}

function linka_nko_registry_utf8(string $content): string
{
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }
    if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
        $converted = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
        return is_string($converted) ? $converted : '';
    }
    return $content;
}

function linka_nko_registry_valid_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
    return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date;
}

function linka_nko_registry_s3_request(string $method, string $key, string $body = '', string $content_type = '')
{
    $bucket = trim((string) getenv('REGISTRY_S3_BUCKET'));
    $access_key = trim((string) getenv('REGISTRY_S3_ACCESS_KEY_ID'));
    $secret_key = trim((string) getenv('REGISTRY_S3_SECRET_ACCESS_KEY'));
    $region = trim((string) (getenv('REGISTRY_S3_REGION') ?: 'ru-central1'));
    if ($bucket === '' || $access_key === '' || $secret_key === '') {
        return new WP_Error('registry_s3_not_configured', 'Registry storage is not configured.');
    }

    $method = strtoupper($method);
    $host = $bucket . '.storage.yandexcloud.net';
    $encoded_key = implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
    $uri = '/' . $encoded_key;
    $url = 'https://' . $host . $uri;
    $amz_date = gmdate('Ymd\THis\Z');
    $date = substr($amz_date, 0, 8);
    $payload_hash = hash('sha256', $body);
    $headers = [
        'host' => $host,
        'x-amz-content-sha256' => $payload_hash,
        'x-amz-date' => $amz_date,
    ];
    if ($content_type !== '') {
        $headers['content-type'] = $content_type;
    }
    ksort($headers);

    $canonical_headers = '';
    foreach ($headers as $name => $value) {
        $canonical_headers .= $name . ':' . trim($value) . "\n";
    }
    $signed_headers = implode(';', array_keys($headers));
    $canonical_request = $method . "\n" . $uri . "\n\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;
    $scope = $date . '/' . $region . '/s3/aws4_request';
    $string_to_sign = "AWS4-HMAC-SHA256\n{$amz_date}\n{$scope}\n" . hash('sha256', $canonical_request);
    $date_key = hash_hmac('sha256', $date, 'AWS4' . $secret_key, true);
    $region_key = hash_hmac('sha256', $region, $date_key, true);
    $service_key = hash_hmac('sha256', 's3', $region_key, true);
    $signing_key = hash_hmac('sha256', 'aws4_request', $service_key, true);
    $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
    $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $access_key . '/' . $scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;

    $response = wp_remote_request($url, [
        'method' => $method,
        'timeout' => 30,
        'headers' => $headers,
        'body' => in_array($method, ['PUT', 'POST'], true) ? $body : null,
    ]);
    if (is_wp_error($response)) {
        return $response;
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    if ($status < 200 || $status >= 300) {
        error_log('Registry S3 request failed with status ' . $status);
        return new WP_Error('registry_s3_error', 'Registry storage request failed.');
    }
    return $method === 'GET' ? (string) wp_remote_retrieve_body($response) : true;
}

function linka_nko_registry_get_content(object $registry)
{
    $content = linka_nko_registry_s3_request('GET', (string) $registry->object_key);
    if (is_wp_error($content)) {
        return $content;
    }
    if (!hash_equals((string) $registry->content_sha256, hash('sha256', $content))) {
        error_log('YooKassa registry object failed integrity verification for registry ' . (int) $registry->id);
        return new WP_Error('registry_integrity_error', 'Registry object failed integrity verification.');
    }
    return $content;
}

function linka_nko_import_registries_from_mail()
{
    if (!function_exists('imap_open')) {
        return new WP_Error('registry_imap_unavailable', 'IMAP extension is unavailable.');
    }
    $host = trim((string) (getenv('REGISTRY_IMAP_HOST') ?: 'imap.yandex.ru'));
    $port = (int) (getenv('REGISTRY_IMAP_PORT') ?: 993);
    $username = trim((string) getenv('REGISTRY_IMAP_USERNAME'));
    $password = (string) getenv('REGISTRY_IMAP_PASSWORD');
    if ($username === '' || $password === '') {
        return new WP_Error('registry_imap_not_configured', 'Registry mailbox is not configured.');
    }

    $mailbox = sprintf('{%s:%d/imap/ssl}INBOX', $host, $port);
    $imap = @imap_open($mailbox, $username, $password, OP_READONLY, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
    if ($imap === false) {
        imap_errors();
        return new WP_Error('registry_imap_connection_failed', 'Could not connect to registry mailbox.');
    }

    $since = gmdate('d-M-Y', time() - 45 * DAY_IN_SECONDS);
    $messages = imap_search($imap, 'FROM "reports@yoomoney.ru" SINCE "' . $since . '"', SE_UID) ?: [];
    $imported = 0;
    $duplicates = 0;
    $failed = 0;
    foreach ($messages as $uid) {
        $overview = imap_fetch_overview($imap, (string) $uid, FT_UID);
        $raw_headers = (string) imap_fetchheader($imap, (string) $uid, FT_UID | FT_PEEK);
        if (!linka_nko_registry_authenticated_sender((string) ($overview[0]->from ?? ''), $raw_headers)) {
            $failed++;
            continue;
        }
        $structure = imap_fetchstructure($imap, (string) $uid, FT_UID);
        if (!is_object($structure)) {
            $failed++;
            continue;
        }
        foreach (linka_nko_registry_imap_attachments($imap, (int) $uid, $structure) as $attachment) {
            if (!preg_match('/^yoomoney-(payments|refunds)-' . preg_quote(LINKA_NKO_REGISTRY_SHOP_ID, '/') . '-\d{4}-\d{2}-\d{2}\.csv$/i', $attachment['filename'])) {
                continue;
            }
            $result = linka_nko_store_registry($attachment['filename'], $attachment['content']);
            if (is_wp_error($result)) {
                $failed++;
            } elseif ($result['duplicate']) {
                $duplicates++;
            } else {
                $imported++;
            }
        }
    }
    imap_close($imap);

    return ['messages_checked' => count($messages), 'imported' => $imported, 'duplicates' => $duplicates, 'failed' => $failed];
}

function linka_nko_registry_imap_attachments($imap, int $uid, object $structure, string $prefix = ''): array
{
    $attachments = [];
    $parts = isset($structure->parts) && is_array($structure->parts) ? $structure->parts : [];
    foreach ($parts as $index => $part) {
        $part_number = $prefix === '' ? (string) ($index + 1) : $prefix . '.' . ($index + 1);
        if (isset($part->parts) && is_array($part->parts)) {
            $attachments = array_merge($attachments, linka_nko_registry_imap_attachments($imap, $uid, $part, $part_number));
        }
        $filename = linka_nko_registry_imap_filename($part);
        if ($filename === '') {
            continue;
        }
        $content = (string) imap_fetchbody($imap, (string) $uid, $part_number, FT_UID | FT_PEEK);
        if ((int) ($part->encoding ?? 0) === ENCBASE64) {
            $content = base64_decode($content, true) ?: '';
        } elseif ((int) ($part->encoding ?? 0) === ENCQUOTEDPRINTABLE) {
            $content = quoted_printable_decode($content);
        }
        $attachments[] = ['filename' => $filename, 'content' => $content];
    }
    return $attachments;
}

function linka_nko_registry_imap_filename(object $part): string
{
    foreach (['dparameters', 'parameters'] as $property) {
        $parameters = isset($part->{$property}) && is_array($part->{$property}) ? $part->{$property} : [];
        foreach ($parameters as $parameter) {
            $attribute = strtolower((string) ($parameter->attribute ?? ''));
            if (in_array($attribute, ['filename', 'name'], true)) {
                $value = (string) ($parameter->value ?? '');
                return sanitize_file_name(function_exists('imap_utf8') ? imap_utf8($value) : $value);
            }
        }
    }
    return '';
}

function linka_nko_registry_authenticated_sender(string $from, string $headers): bool
{
    if (preg_match('/(?:^|<)reports@yoomoney\.ru>?$/i', trim($from)) !== 1) {
        return false;
    }

    if (preg_match('/^Authentication-Results:\s*(.+(?:\r?\n[ \t].+)*)/mi', $headers, $matches) !== 1) {
        return false;
    }
    $authentication_results = (string) $matches[1];
    $authentication_results = preg_replace('/\r?\n[ \t]+/', ' ', $authentication_results) ?: '';
    $trusted_yandex_server = preg_match('/^[a-z0-9.-]+\.yandex\.net\s*;/i', $authentication_results) === 1;
    $dkim_passed = preg_match('/\bdkim=pass\b[^;]*header\.i=@yoomoney\.ru\b/i', $authentication_results) === 1;
    $spf_passed = preg_match('/\bspf=pass\b[^;]*smtp\.mail=reports@yoomoney\.ru\b/i', $authentication_results) === 1;
    $return_path_matches = preg_match('/^Return-Path:\s*<?reports@yoomoney\.ru>?\s*$/mi', $headers) === 1;
    return $trusted_yandex_server && $dkim_passed && $spf_passed && $return_path_matches;
}

function linka_nko_registry_require_admin(string $action): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав.', '', ['response' => 403]);
    }
    check_admin_referer($action, 'linka_nko_registry_nonce');
}

function linka_nko_registry_admin_redirect(string $notice): void
{
    wp_safe_redirect(add_query_arg(['page' => 'linka-nko-registries', 'registry_notice' => $notice], admin_url('admin.php')));
    exit;
}

function linka_nko_registry_send_download(string $filename, string $content): void
{
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode(sanitize_file_name($filename)) . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

function linka_nko_registry_parse_month(string $month): string
{
    if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
        $date = DateTimeImmutable::createFromFormat('!Y-m', $month, new DateTimeZone('UTC'));
        if ($date instanceof DateTimeImmutable && $date->format('Y-m') === $month) {
            return $month;
        }
    }
    return '';
}

function linka_nko_registry_type_label(string $type): string
{
    return $type === 'refunds' ? 'Возвраты' : 'Платежи';
}

function linka_nko_registry_safe_csv_value(string $value): string
{
    return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
}
