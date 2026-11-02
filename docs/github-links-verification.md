# Проверка ссылок на открытый код — 2 ноября 2026

Задача: [#1](https://github.com/linkasu/nko-linka.ru/issues/1). Изолированная база: `cf13634e5c94dd141702909d2af839e731b02e00`.

Футер содержит прямую текстовую ссылку на организацию и переход к инструкции на `/about/#open-source`. После существующего содержимого страниц about/programs/looks/type/play тема добавляет общий блок; на продуктовых страницах показаны только соответствующие репозитории. WordPress content, кнопки скачивания, пожертвования, инфраструктура и legacy-сайт не меняются. Версия CSS темы повышена до 0.1.4 для сброса кеша, не для публикации релиза.

## Проверено

- Новый `php tests/open-source-links.php` сначала завершился с ошибкой отсутствующего шаблона, после реализации прошёл: organization/footer/contribution, доступный заголовок, точная матрица репозиториев для пяти страниц, отсутствие посторонних GitHub-целей и принудительных новых вкладок, сохранение focus и mobile targets.
- `sh scripts/check-local.sh` — PASS, exit 0, 2026-11-02 09:52 UTC: PHP syntax, readiness, source policy, source links, recurring state, donations security, retention, blue-green, private-network, registries, YAML/shell/Compose config. Docker images локально не собирались. После добавления проверки заголовка/точного количества ссылок и смены CSS version повторён только затронутый focused test.
- GitHub API 09:51 UTC: все шесть URL отвечают, repositories public, nonempty и не archived. Имена и варианты Electron/Unity/Flutter/PWA/Rust указаны отдельно; одинаковая готовность не обещается.
- Chromium через штатный Playwright MCP, **theme-only fixture** с настоящими `header.php`, `page.php`, `front-page.php`, `open-source.php`, `footer.php` и CSS. В fixture синтетический контент и короткое меню, без WordPress/DB/payment hooks: это не live WordPress E2E.
- Viewports 1440×900 и 390×844: горизонтального overflow нет, все новые ссылки имеют высоту не меньше 44 CSS px. Настоящие Tab-нажатия переводят фокус на ссылки; outline 3 px, offset 3 px виден на desktop/mobile. About anchor и отсутствие полного списка репозиториев на about проверены. Главная сохраняет обе hero CTA.
- Скриншоты `programs-desktop.png`, `programs-mobile.png`, `home-mobile.png`, `about-desktop.png` сохранены локально в evidence каталоге координатора, просмотрены при проверке. Скриншоты программ просмотрены визуально.

## Открытые ограничения

- Screenshot write в repository worktree отклонён Playwright root guard. Файлы **не скопированы** за allowed roots, roots не расширялись. Публичные screenshot-вложения в PR пока отсутствуют; это незакрытая часть acceptance, а не основание обходить защиту.
- Не проверены полный WordPress runtime, screen reader и реальный production-контент с этой версией темы. Текущий публичный `/programs/` прочитан только для подтверждения slug дочерних страниц; он не содержит новые изменения.
- Локальный preview сначала не стартовал на занятом 18081; существующий процесс не трогали, для собственного временного preview использован 18573. Его вкладка/процесс закрываются после проверки.
- PR только draft. CI, merge и deployment сообщаются отдельно по фактическому результату; approval, merge и production rollout не выполнялись.
