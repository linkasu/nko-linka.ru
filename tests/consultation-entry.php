<?php

declare(strict_types=1);

$filters = [];
function add_action(...$args): void {}
function add_filter(string $name, callable $callback, ...$args): void
{
    global $filters;
    $filters[$name] = $callback;
}
function home_url(string $path): string { return 'https://nkolinka.ru' . $path; }
function esc_url(string $url): string { return htmlspecialchars($url, ENT_QUOTES); }
function get_header(): void {}
function get_footer(): void {}
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require __DIR__ . '/../wp-content/themes/linka-nko/functions.php';
$menu = $filters['wp_nav_menu_items'];
$existing = '<li>Существующий пункт</li>';
check($menu($existing, (object) ['theme_location' => 'footer']) === $existing, 'Footer menu must stay unchanged');
$primary = $menu($existing, (object) ['theme_location' => 'primary']);
check(str_contains($primary, 'https://nkolinka.ru/#consultations'), 'Primary menu must link to the home section');
check(str_ends_with($primary, $existing), 'Existing menu items must remain intact');

ob_start();
require __DIR__ . '/../wp-content/themes/linka-nko/front-page.php';
$html = ob_get_clean();
check(substr_count($html, 'id="consultations"') === 1, 'Section anchor must be unique');
check(str_contains($html, 'aria-labelledby="consultations-title"'), 'Section must have an accessible name');
check(str_contains($html, 'id="consultations-title"'), 'Accessible section heading must exist');
check(str_contains($html, 'href="https://crm.nkolinka.ru/apply">Подать заявку на консультацию</a>'), 'CTA must use the existing form in the same tab');
check(str_contains($html, 'Консультации платные.'), 'Paid nature must be explicit');
check(str_contains($html, 'не означает бронирование времени'), 'Application must not promise a booked appointment');
check(!preg_match('/<(?:form|iframe|input)\b/i', $html), 'Home page must not collect data or embed CRM');
echo "consultation-entry: OK\n";
