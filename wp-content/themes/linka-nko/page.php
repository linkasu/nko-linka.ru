<?php get_header(); ?>
<main class="content">
  <div class="wrap">
    <?php
    while (have_posts()) {
        the_post();
        echo '<article>';
        echo '<h1>' . esc_html(get_the_title()) . '</h1>';
        the_content();
        echo '</article>';
    }
    ?>
  </div>
</main>
<?php get_footer(); ?>
