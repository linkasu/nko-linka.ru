# nko-linka.ru

Официальный сайт АНО "Линка" на WordPress для домена `nko-linka.ru`.

## Назначение

Сайт должен заменить старый `linka.su` как главный публичный сайт проекта Linka и юридического лица АНО "Линка".

## Архитектура

- WordPress в Yandex Cloud Serverless Containers.
- Docker image собирается через GitHub Actions.
- MariaDB/MySQL работает в Docker на сервере `37.230.192.57`.
- Медиа WordPress хранятся в Yandex Object Storage.
- HTTPS через Yandex Certificate Manager.
- Runtime secrets хранятся вне репозитория.

## Документы

- `AGENTS.md` - правила работы для агентов.
- `docs/decisions.md` - принятые решения.
- `docs/content-plan.md` - структура и правила переноса контента.
- `docs/infrastructure.md` - целевая инфраструктура.
- `docs/safety.md` - правила безопасности и секретов.
- `docs/legal.md` - юридические публичные сведения и публикация адреса.
- `docs/runbook.md` - операционный порядок работ.

## Донаты

Раздел донатов, платежные формы, платежные реквизиты и CTA на пожертвования в первой версии запрещены.

## Быстрый Старт Для Разработки

Пока проект в начальной фазе. Перед любыми production-действиями сначала выполнить инвентарь из `docs/runbook.md`.
