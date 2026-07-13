<?php

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);

    register_nav_menus([
        'primary' => 'Главное меню',
        'footer' => 'Меню в подвале',
    ]);
});

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style('linka-nko-style', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
});

add_action('wp_head', static function (): void {
    $description = '';
    if (is_front_page()) {
        $description = 'АНО «Линка» развивает доступную среду и бесплатные программы альтернативной и дополнительной коммуникации для людей с ОВЗ.';
    } elseif (is_singular()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $description = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags(strip_shortcodes((string) $post->post_content)), 28, '');
        }
    }

    if ($description === '') {
        $description = (string) get_bloginfo('description');
    }
    if ($description === '') {
        return;
    }

    $title = wp_get_document_title();
    $url = is_singular() ? get_permalink() : home_url('/');
    ?>
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:type" content="<?php echo is_singular('post') ? 'article' : 'website'; ?>">
    <meta property="og:site_name" content="АНО «Линка»">
    <meta property="og:title" content="<?php echo esc_attr($title); ?>">
    <meta property="og:description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:url" content="<?php echo esc_url($url); ?>">
    <?php
}, 5);

add_action('wp_head', static function (): void {
    if (!is_front_page()) {
        return;
    }

    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'NGO',
        'name' => 'АНО «Линка»',
        'legalName' => 'Автономная некоммерческая организация в сфере развития доступной среды и повышения коммуникативных навыков людей с ОВЗ «Линка»',
        'url' => home_url('/'),
        'email' => 'feedback@linka.su',
        'taxID' => '7840128432',
        'foundingDate' => '2026-06-26',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Санкт-Петербург',
            'addressCountry' => 'RU',
        ],
    ];
    ?>
    <script type="application/ld+json"><?php echo wp_json_encode($organization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php
}, 6);
