<?php
/**
 * Project-level WordPress defaults for nko-linka.ru.
 */

add_action('init', static function (): void {
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
        }
        if (post_type_supports($post_type, 'trackbacks')) {
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
});

add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);
add_filter('comments_array', '__return_empty_array', 10, 2);

add_action('admin_menu', static function (): void {
    remove_menu_page('edit-comments.php');
});

add_action('wp_before_admin_bar_render', static function (): void {
    global $wp_admin_bar;
    if ($wp_admin_bar !== null) {
        $wp_admin_bar->remove_menu('comments');
    }
});

add_action('phpmailer_init', static function (PHPMailer\PHPMailer\PHPMailer $phpmailer): void {
    $host = getenv('POSTBOX_SMTP_HOST') ?: '';
    $username = getenv('POSTBOX_SMTP_USERNAME') ?: '';
    $password = getenv('POSTBOX_SMTP_PASSWORD') ?: '';
    $from = getenv('POSTBOX_FROM_EMAIL') ?: '';

    if ($host === '' || $username === '' || $password === '' || $from === '') {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = (int) (getenv('POSTBOX_SMTP_PORT') ?: 587);
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = $username;
    $phpmailer->Password = $password;
    $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $phpmailer->setFrom($from, getenv('POSTBOX_FROM_NAME') ?: 'АНО Линка', false);
});

add_filter('wp_mail_from', static function (string $email): string {
    return getenv('POSTBOX_FROM_EMAIL') ?: $email;
});

add_filter('wp_mail_from_name', static function (string $name): string {
    return getenv('POSTBOX_FROM_NAME') ?: $name;
});
