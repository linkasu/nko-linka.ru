<?php get_header(); ?>
<main class="content">
  <div class="wrap">
    <?php if (have_posts()) : ?>
      <?php while (have_posts()) : the_post(); ?>
        <article class="card">
          <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
          <?php the_excerpt(); ?>
        </article>
      <?php endwhile; ?>
      <?php the_posts_pagination(); ?>
    <?php else : ?>
      <p>Материалы пока не опубликованы.</p>
    <?php endif; ?>
  </div>
</main>
<?php get_footer(); ?>
