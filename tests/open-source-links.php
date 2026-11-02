<?php

function is_page(string|array $slug): bool
{
    return in_array($GLOBALS['page_slug'], (array) $slug, true);
}

function home_url(string $path): string
{
    return 'https://nkolinka.ru' . $path;
}

function esc_url(string $url): string
{
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

function wp_footer(): void {}

function expect_source_link(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$theme = dirname(__DIR__) . '/wp-content/themes/linka-nko';
$repositories = ['linka.looks-electron', 'linka.plays-electron', 'linka.plays-unity', 'linka-type-pwa', 'linka_type_flutter', 'linka.predictor-rust'];
$product_sources = [
    'about' => [],
    'programs' => $repositories,
    'looks' => ['linka.looks-electron'],
    'type' => ['linka_type_flutter', 'linka-type-pwa'],
    'play' => ['linka.plays-electron', 'linka.plays-unity'],
];
foreach ($product_sources as $slug => $expected_sources) {
    $GLOBALS['page_slug'] = $slug;
    ob_start();
    require $theme . '/open-source.php';
    $html = ob_get_clean();
    expect_source_link(str_contains($html, 'id="open-source"'), 'Missing contribution anchor');
    expect_source_link(str_contains($html, 'aria-labelledby="open-source-title"') && str_contains($html, '<h2 id="open-source-title">'), 'Missing accessible section heading');
    expect_source_link(str_contains($html, 'href="https://github.com/linkasu"'), 'Missing labelled organization link');
    foreach (['приглашение не требуется', 'issue', 'pull request', 'проверку', 'mailto:feedback@linka.su'] as $text) {
        expect_source_link(str_contains($html, $text), 'Missing contribution guidance: ' . $text);
    }
    foreach ($repositories as $repository) {
        $count = substr_count($html, 'href="https://github.com/linkasu/' . $repository . '"');
        expect_source_link($count === (in_array($repository, $expected_sources, true) ? 1 : 0), 'Incorrect product source coverage on ' . $slug . ': ' . $repository);
    }
    expect_source_link(substr_count($html, 'href="https://github.com/') === count($expected_sources) + 1, 'Unexpected source repository');
    expect_source_link(!str_contains($html, 'target='), 'Source navigation should not force a new tab');
}

ob_start();
require $theme . '/footer.php';
$footer = ob_get_clean();
expect_source_link(str_contains($footer, 'href="https://github.com/linkasu"'), 'Footer must link directly to the organization');
expect_source_link(str_contains($footer, 'Наш GitHub / Открытый код'), 'Footer label must make sense without an icon');
$page = file_get_contents($theme . '/page.php');
expect_source_link(str_contains($page, "is_page(['about', 'programs', 'looks', 'type', 'play'])"), 'Render guidance only on the relevant pages');
expect_source_link(str_contains($page, "get_template_part('open-source')"), 'Render guidance from the actual WordPress template');
$css = file_get_contents($theme . '/style.css');
expect_source_link(str_contains($css, 'a:focus { outline:'), 'Keep visible keyboard focus');
expect_source_link(str_contains($css, 'min-height: 44px'), 'Keep comfortable mobile source-link targets');
fwrite(STDOUT, "Open source link tests passed\n");
