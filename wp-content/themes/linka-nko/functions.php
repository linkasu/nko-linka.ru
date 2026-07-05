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
