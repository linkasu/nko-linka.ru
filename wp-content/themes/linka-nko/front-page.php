<?php get_header(); ?>
<main>
  <section class="hero">
    <div class="wrap">
      <h1>Помогаем людям общаться, учиться и быть услышанными</h1>
      <p class="lead">АНО «Линка» развивает доступную среду и коммуникативные навыки людей с ОВЗ. Мы создаем и поддерживаем бесплатные программы для альтернативной и дополнительной коммуникации.</p>
      <div class="hero-actions">
        <a class="button" href="<?php echo esc_url(home_url('/programs/')); ?>">Смотреть программы</a>
        <a class="button button-secondary" href="<?php echo esc_url(home_url('/donate/')); ?>">Пожертвовать</a>
      </div>
    </div>
  </section>
  <section class="content">
    <div class="wrap card-grid">
      <article class="card">
        <h2>Программы</h2>
        <p>Инструменты для коммуникации, обучения и выбора: взглядом, мышью, клавиатурой, планшетом или карточками.</p>
      </article>
      <article class="card">
        <h2>Документы</h2>
        <p>Устав, регистрационные документы, выписка ЕГРЮЛ и публичные сведения об организации.</p>
      </article>
      <article class="card">
        <h2>Материалы</h2>
        <p>Статьи и научные публикации об альтернативной коммуникации и ассистивных технологиях.</p>
      </article>
      <article class="card">
        <h2>Пожертвования</h2>
        <p>Добровольные пожертвования помогают поддерживать уставную деятельность АНО «Линка». Это не оплата товаров или услуг.</p>
      </article>
    </div>
  </section>
</main>
<?php get_footer(); ?>
