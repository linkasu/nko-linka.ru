# Экстренное переключение nkolinka.ru на CRM VM — 13.10.2026

Эта запись описывает фактическое состояние после переноса **только WordPress**, без переноса MariaDB. Не применять старый Gateway rollback или восстановление старой firewall-конфигурации без проверки регулярных платежей ниже.

## Вход и данные

- `nkolinka.ru.`: A `51.250.34.165`, TTL 60; прежде ANAME `d5dmjh8ur6ogqs55jbqn.iwzqm34r.apigw.yandexcloud.net.`, TTL 300. MX, TXT, NS и `crm.nkolinka.ru` не менялись. Изменение внесено вручную через `yc dns zone`; `infra/yandex/api-gateway.yaml` остаётся описанием старого Gateway, не текущего публичного входа.
- VM `linka-crm-vm-direct` (`fv4ec5kh75l6h6k5cnar`, зона `ru-central1-d`) обслуживает CRM и `linka-wordpress-vm`. WordPress из протестированного в GitHub CI образа `ghcr.io/linkasu/nko-linka-wordpress@sha256:73daf13ea756a9c84abdcf92087dd9ed8994052270d470bfb48155880d7d163c`. **Незакоммиченные изменения** в соседних разделах репозитория не вошли в образ.
- MariaDB остаётся на VPS `37.230.192.57` и доступна WordPress через `10.60.0.10:3306`/WireGuard. Последний backup перед переключением: `/home/aacidov/nko-linka-db/backups/nko-linka-wordpress-20261013T085136Z.sql.gz`, mode 600, gzip проверен, SHA-256 `51dcd6d732c6e6da3e97212af1c3871a6dbf3afd68acbf7fac68ff8fbe29a6e3`. Восстановление в изолированную БД в этом окне не выполнялось.
- Загруженные файлы остаются в `nko-linka-ru-uploads`; root-only `/etc/linka/site-uploads.rclone.conf` на VM содержит отдельный static access key `nko-linka-runtime` для FUSE mount `/srv/linka/site-uploads`. Ключ и значения `/etc/linka/site.env`/`site-internal.curl` не хранить в git и не выводить в логи. WordPress service связан с mount через `Requires`/`BindsTo` и слушает только `127.0.0.1:8090`. Caddy обслуживает `nkolinka.ru` вторым vhost с отдельным сертификатом `fpqfb4bbj47ppclem208`; сертификат синхронизируется таймером.

## Регулярные пожертвования — приоритетная граница

Старый YC trigger API был недоступен, поэтому на выделенном DB-gateway заменена только `/etc/linka-private-network/firewall.nft`: из трёх правил убран источник Serverless `198.19.0.0/16`, разрешение подсети VM `10.60.0.0/24` сохранено. Оригинал root-only: `/etc/linka-private-network/firewall.nft.pre-wordpress-vm-20261013`. Старый контейнер может ещё получать timer-события, **но не может читать MariaDB и списывать**. Не восстанавливать старый файл вслепую.

После firewall cutover в `wp_options.linka_nko_recurring_cutover_at` единожды записано `2026-10-13 08:56:45` UTC. VM runner с `LINKA_NKO_FUTURE_ONLY=1` обрабатывает только активные подписки с будущим относительно этой границы сроком. На контрольных срезах: `active=94`, **2 старых просроченных** оставлены на ручную сверку, `charging=0`; запуск runner и последующий 15-минутный timer вернули `skipped_before_cutoff=2`, `processed=0`, `failed=0`. Не сдвигать двум подпискам даты и не подавать новый charge без сверки с YooKassa и решения владельца.

VM timers: recurring каждые 15 минут и сводка пожертвований ежечасно **включены**; импорт реестров в 02:30 UTC **выключен**. Причина: POP3 `registry_mail_authentication_failed`; VM env совпадает с закреплённой активной версией Lockbox. Импорт не дошёл до чтения UIDL и записи S3. После восстановления почтового доступа вручную повторить импорт и только потом включить timer. Сводка YooKassa прошла (`payments_synced=true`), но отправка пяти ранее накопленных fundraising-outbox событий в metrics пока дала `delivered=0`; сами события остаются в БД.

## Проверки и восстановление

- Публично через проверенный TLS: `/`, `/readyz.php`, `/documents/`, `/donate/`, `/donation-subscription/` — 200; `/internal/run-recurring-donations` — 404. Четыре опубликованных PDF со страницы документов скачиваются с кодом 200. CRM `/readyz` остаётся 200.
- С VM подтверждены MariaDB auth, реальная запись и чтение S3 от `www-data` (синтетический объект удалён), WordPress SMTP с синтетическим письмом, read-only YooKassa `GET /v3/me`, bearer 401 без ключа и успешный локальный recurring runner без новых списаний.
- VM имеет 2 vCPU / 8 ГБ RAM / 20 ГБ HDD; после запуска WordPress доступно около 15 ГБ диска. Контролировать память, диск, `/readyz.php`, последующие `processed/failed/recovered` и состояние mount. Не собирать Docker-образы на VM.
- Для rollback сначала остановить **VM recurring timer**, убедиться, что старый trigger остановлен или старый контейнер соблюдает тот же cutoff; только потом можно вернуть его DB-доступ и ANAME Gateway. Сейчас старый trigger API не подтверждает остановку: если VM-сайт ломается, безопаснее исправлять VM вперёд, чем открывать старому коду путь к двум пропущенным списаниям.
