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

- При первоначальной проверке screenshot write в repository worktree был отклонён Playwright root guard. Та операция не повторялась, файлы за allowed roots не копировались. Свежие скриншоты при повторной проверке сохранены штатным MCP в текущем workspace; см. ниже.
- Не проверены полный WordPress runtime, screen reader и реальный production-контент с этой версией темы. Текущий публичный `/programs/` прочитан только для подтверждения slug дочерних страниц; он не содержит новые изменения.
- Локальный preview сначала не стартовал на занятом 18081; существующий процесс не трогали, для собственного временного preview использован 18573. Его вкладка/процесс закрываются после проверки.
- Цель публикации — только draft PR. CI, merge и deployment сообщаются отдельно по фактическому результату; approval, merge и production rollout не выполнялись.

## Повторная проверка 2 ноября 2026, 11:38–11:40 UTC

- `php tests/open-source-links.php` и `git diff --check HEAD^ HEAD` — PASS. Код реализации `cc95d0c` не изменён; добавлены только доказательства проверки.
- GitHub API повторно подтвердил существование организации и шести целевых репозиториев: все public, nonempty, not archived.
- Свежая локальная theme-only fixture загружает реальные шаблоны темы и CSS, подменяя WordPress API. Синтетическое содержимое явно помечено на скриншотах; это не проверка развёрнутого WordPress.
- Chromium, 1440×900 и 390×844: `scrollWidth === innerWidth`; десять ссылок блока/футера высотой минимум 44 px; Tab достигает GitHub, focus outline solid 3 px / offset 3 px. Проверена точная матрица исходников на about/looks/type/play и сохранение двух hero CTA главной.
- Переход через ссылку футера на `/about/#open-source` — PASS. Первый click получил timeout при `document.visibilityState === 'hidden'`; после `bringToFront()` обычный click прошёл, без изменения кода или принудительного клика.
- Скриншоты созданы нативным screenshot-инструментом в разрешённом текущем workspace, просмотрены и скопированы внутри того же workspace. Логи браузера не публикуются.

### Скриншоты темы

- [Программы, desktop 1440 px](screenshots/github-links/programs-desktop.png).
- [Программы, mobile 390 px, клавиатурный фокус](screenshots/github-links/programs-mobile.png).
- [О Линке, desktop](screenshots/github-links/about-desktop.png).
- [Главная, mobile](screenshots/github-links/home-mobile.png).
