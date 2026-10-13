<?php
// A synthetic delivery check; no subscriber data or payment action.
$recipient = getenv('LINKA_VM_MAIL_CANARY_TO');
if (!is_string($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    exit(1);
}
require '/var/www/html/wp-load.php';
if (!wp_mail($recipient, 'Проверка почты nkolinka.ru на VM', 'Синтетическая проверка. Пожертвования не затрагивались.')) {
    fwrite(STDERR, "WordPress mail was not accepted\n");
    exit(1);
}
echo "WordPress SMTP accepted synthetic mail\n";
