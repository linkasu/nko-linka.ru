<?php get_header(); ?>
<main class="content">
  <div class="wrap">
    <?php
    while (have_posts()) {
        the_post();
        echo '<article>';
        echo '<h1>' . esc_html(get_the_title()) . '</h1>';
        the_content();
        if (is_page(['about', 'programs', 'looks', 'type', 'play'])) {
            get_template_part('open-source');
        }
        echo '</article>';
    }
    ?>
  </div>
</main>
<?php get_footer(); ?>
