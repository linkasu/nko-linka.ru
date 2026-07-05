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
