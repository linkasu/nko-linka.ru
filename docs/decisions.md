# Decisions

Дата фиксации: 2026-07-05.

## Product

- `nko-linka.ru` становится официальным главным сайтом АНО "Линка" и проекта Linka.
- Старый `linka.su` будет выключен позже.
- Редиректы со старого сайта на первом этапе не делаем.
- Первая версия переносит контент старого сайта без комментариев.
- Сайт только на русском языке.
- Визуальный стиль: стандартный сайт НКО с символикой Линки.

## Content

- Донаты убрать полностью.
- Контактная форма не нужна; использовать email `feedback@linka.su`.
- Ссылку на `books.linka.su` пока убрать.
- Полный юридический адрес показывать в реквизитах и политике обработки персональных данных.
- Создать пустой раздел "Отчеты" с пояснением, что первые отчеты появятся после отчетного периода.

## Infrastructure

- GitHub repository: `linkasu/nko-linka.ru`.
- Yandex Cloud folder: `b1gn4stour811vgtjude`.
- Runtime: Yandex Cloud Serverless Containers.
- CMS: WordPress.
- DB: MySQL/MariaDB в Docker на `37.230.192.57`.
- Uploads: Yandex Object Storage.
- TLS: Yandex Certificate Manager.
- Secrets: GitHub Actions + YC runtime secrets.
- Analytics: later, not in v1.

## Non-Goals For V1

- Нет донатов.
- Нет контактных форм.
- Нет английской версии.
- Нет старых комментариев.
- Нет редиректов со старого сайта.
- Нет локальной или серверной сборки Docker images.
