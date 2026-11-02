<section class="open-source" id="open-source" aria-labelledby="open-source-title">
  <h2 id="open-source-title">Открытый код и помощь разработкой</h2>
  <p><a href="https://github.com/linkasu">Наш GitHub — открытый код LINKa</a>. Код публичный: для чтения приглашение не требуется. Предложить исправление можно через issue или pull request; изменения проходят проверку. Для начала сотрудничества напишите на <a href="mailto:feedback@linka.su">feedback@linka.su</a>.</p>
  <?php if (!is_page('about')) : ?>
    <p>Исходники не заменяют готовые программы: ссылки для скачивания остаются выше. Версии и степень готовности различаются; требования и ограничения указаны в README каждого репозитория.</p>
    <ul>
      <?php if (is_page(['programs', 'looks'])) : ?>
      <li><a href="https://github.com/linkasu/linka.looks-electron">Исходный код на GitHub — Линка. смотри (Electron)</a></li>
      <?php endif; ?>
      <?php if (is_page(['programs', 'type'])) : ?>
      <li><a href="https://github.com/linkasu/linka_type_flutter">Исходный код на GitHub — Линка. напиши (Flutter)</a></li>
      <li><a href="https://github.com/linkasu/linka-type-pwa">Исходный код на GitHub — LINKa Type (веб-версия, PWA)</a></li>
      <?php endif; ?>
      <?php if (is_page(['programs', 'play'])) : ?>
      <li><a href="https://github.com/linkasu/linka.plays-electron">Исходный код на GitHub — Линка. играй (Electron)</a></li>
      <li><a href="https://github.com/linkasu/linka.plays-unity">Исходный код на GitHub — Линка. играй (Unity)</a></li>
      <?php endif; ?>
      <?php if (is_page('programs')) : ?>
      <li><a href="https://github.com/linkasu/linka.predictor-rust">Исходный код на GitHub — офлайн-предиктор (Rust, компонент для разработчиков)</a></li>
      <?php endif; ?>
    </ul>
  <?php endif; ?>
</section>
