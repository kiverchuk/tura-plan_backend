# TuraPlan — план реализации полного приложения

Актуализировано: 17 октября 2026 г.
Редакция: 1.2 — уточнены design freeze, экраны облачных потоков и серверные сущности.  
Основание: TuraPlan_TZ_Shift_Calendar_RU.txt и решения владельца продукта.  
Цель: один первый публичный релиз — полноценное приложение iOS/Android со всем согласованным функционалом ТЗ и референса. Этапы ниже задают инженерную последовательность и критерии готовности, а не публичные MVP/урезанные релизы.

## 1. Утверждённые решения

- Рабочее название — TuraPlan; перед публикацией проверить бренд, домены и магазины.
- Полный согласованный объём выходит в первой публичной версии. P0/P1/P2 — порядок разработки и риск, а не обещание «сделать остальное потом». Все P1 функции из референса и дополнений этого плана обязательны перед публикацией.
- Румынский язык один: ro. Страна/регион RO, MD и другие поддерживаемые регионы выбираются отдельно и определяют праздники/форматы, а не отдельный перевод.
- Семь языков первого релиза: en, ro, ru, uk, de, fr, es. Язык при запуске берётся с устройства; неподдерживаемый язык переключается на English; пользовательский override сохраняется.
- Offline-first: приложение и календарь работают без регистрации и интернета. Локальная SQLite и серверная Laravel/MySQL — хранилища продукта. Firebase — только минимальная аналитика; Firebase Database/Firestore/Storage не использовать.
- React Native/Expo клиент находится в отдельном Git-репозитории rn-proj/, Laravel API и Filament admin — в отдельном Git-репозитории backend/. Общего Git-корня и третьего репозитория нет. ТЗ и этот план остаются общими документами рабочего каталога; копии с номером версии/датой включаются в backend/docs/product как версионируемые требования, а rn-proj README ссылается на соответствующую версию документа. Рабочий корень: C:\SuperPrograms\OSPanel\home\work-calendar.
- Технологический baseline на дату плана: Expo SDK 58 + React Native 0.88, Laravel 13, MySQL 9.7 LTS, Filament 5. Перед созданием приложений повторно проверить официальный latest stable, совместимость библиотек и требования магазинов; зафиксировать lock-файлы.
- Базовые тарифы в Румынии: 17,99 RON/месяц, 44,99 RON/год, 179,99 RON Lifetime. На устройстве цену получать от StoreKit/Google Play; локальную цену storefront сверить вручную. Для загрузки прайса не полагаться на универсальный CSV: Apple позволяет экспортировать цены и управлять ими через App Store Connect/API, а Google Play Console с 19.05.2025 больше не поддерживает CSV импорт/экспорт продуктов; использовать доступные price templates и текущую консоль. Free сохраняет работающий календарь, просмотр/редактирование основных данных и полный локальный экспорт; Premium открывает расширенные инструменты и облако с ясной квотой.
- Сервер размещается в Болгарии. Это размещение в ЕС, но само по себе не является подтверждением соблюдения GDPR.

## 2. Целевая структура

work-calendar/ — рабочий контейнер, не Git-репозиторий
- rn-proj/ — отдельный Git-репозиторий: Expo + React Native + TypeScript
- backend/ — отдельный Git-репозиторий: Laravel REST API + Filament 5; также версионируемые общие product docs и OpenAPI-контракт
- TuraPlan_TZ_Shift_Calendar_RU.txt — рабочая копия ТЗ
- TuraPlan_Implementation_Plan_RU.md — рабочая копия плана

Инициализировать два независимых репозитория в rn-proj/ и backend/; не создавать .git в work-calendar/. Каждый репозиторий имеет собственные README, .gitignore, lock-файлы, CI, правила веток, версии и release tags. В backend/docs/product хранить синхронизированные копии ТЗ/плана с версией/датой; обновление документов в корне и копий выполнять вместе в одном изменении и проверять diff. backend/docs/openapi — источник API-контракта. rn-proj закрепляет совместимую версию контракта/сгенерированного типизированного клиента и не редактирует контракт независимо. Перед релизом backend должен быть обратно совместим с опубликованными версиями клиента; расширение API — сначала сервер, удаление/изменение полей — только после окна совместимости и обновления клиента. У каждого репозитория отдельный PR/CI; общий локальный скрипт может запускать обе проверки, но не объединяет репозитории. Никогда не коммитить .env, ключи, токены, store receipts, пользовательские дампы и production secrets. OSPanel должен указывать сайт на backend/public, а не на корень Laravel.

## 3. Мобильная архитектура

Expo SDK 58, React Native 0.88, TypeScript strict, Expo Router, development builds/EAS. Expo Go не является production окружением: widgets, покупки и системные API требуют native configuration.

Feature-first модули: calendar, shifts, rotations, employers, compensation, reports, holidays, notifications, widgets, sharing, sync, billing, localization, privacy. Общий UI layer: типизированные design tokens (цвета, типографика, размеры, spacing, радиусы), формы, кнопки, карточки, календарные компоненты, пустые/ошибочные/offline/loading состояния, light/dark тема. Не дублировать правила расчёта внутри экранов.

Доменная логика — независимые TS модули: календарь/DST, цикл и preview, разрешение конфликтов, часы/доход, экспорт, уведомления. SQLite с версионированными миграциями — быстрый локальный источник истины. Сущности имеют UUID, владельца, revision, timestamps и tombstone; локальные записи атомарные и не ждут сети. SQLCipher включить в production build, ключ базы хранить в Keychain/Android Keystore. Typed API client и очередь offline mutations отдельно от UI. Native adapters для подтверждённых feature matrix виджетов и прочих системных функций; StoreKit/Play Billing, системного календаря и уведомлений. Каждый адаптер привязан к минимальной версии ОС, capability check, permission, тарифу и fallback. Alpha API не брать как production dependency.

## 4. Backend Laravel: модули и контракт

Laravel 13 — модульный монолит, API-first REST JSON /api/v1, MySQL 9.7 LTS. Mobile auth: поддерживаемая bearer-token схема с короткоживущим access, безопасной ротацией refresh session, отзывом устройства/токенов, email verification и reset password; optional Apple/Google login после отдельного решения. Каждый read/write проверяется policy по user/owner/role; UUID не заменяет авторизацию. Тонкие controllers, Form Requests/DTO, domain Actions/Services, API Resources, единая схема ошибок, пагинация, rate limits, idempotency keys, optimistic concurrency. Описание API вести в OpenAPI, типы синхронизировать с RN клиентом.

Модули:
- Identity/Access: аккаунт, верификация, вход/выход, устройства, сессии, роли.
- Calendar: работодатели, календари, типы/экземпляры смен, циклы, события, теги, напоминания, архив/восстановление.
- Compensation: версии ставок, перерывы, overtime/ночные/праздничные user rules, платёжные периоды и отчёты. Доход всегда оценка, не налоговый расчёт.
- Sync: pull/push, cursor, revision, tombstones, retry, конфликты, перенос accountless данных на аккаунт.
- Sharing: ссылки с expiry/revoke, scope и permissions, read-only по умолчанию.
- Billing/Entitlements: server verify Apple/Google, подписанные store notifications/webhooks, restore, trial/grace/expired/refunded/revoked.
- Calendar Content: версионируемые праздничные наборы, источники/лицензии/дата проверки/переводы.
- Privacy/Data Lifecycle: доступ, исправление, ограничение, экспорт, удаление, consent history и сроки хранения.
- Notifications/Jobs: очередь, email, экспорт, webhook retry, очистка по retention.
- Support/Operations: обращения, агрегированная диагностика, health, feature flags, audit trail.

### Группы обязательных методов API

- Auth: POST /auth/register, /auth/login, /auth/logout, /auth/refresh, /auth/verify-email, /auth/password/forgot, /auth/password/reset.
- Профиль/устройства: GET/PATCH /me; GET/DELETE /me/devices/{id}.
- CRUD /employers, /calendars, /shift-types, /shifts, /personal-events, /rotation-templates с архивированием, версиями и проверкой владельца.
- Циклы: POST /rotations/preview, /rotations/apply, /rotations/undo; preview перечисляет конфликты до атомарного применения.
- Sync: POST /sync/push, GET /sync/pull?cursor=, POST /sync/conflicts/{id}/resolve; повтор запроса не создаёт дубль.
- Search: GET /search?q=&from=&to=&employer=&type=&tag=; только авторизованные данные, pagination, rate limit.
- Reports: GET /reports/hours, /reports/earnings, /reports/overtime с фильтрами периода/работодателя/валюты и breakdown правил.
- Sharing: POST /shares; GET/PATCH/DELETE /shares/{id}; GET /shared/{token}; токен отзываемый, scope-ограниченный, не содержит зарплату/заметки по умолчанию.
- Import/export: POST /exports, GET /exports/{id}, POST /imports/validate, POST /imports; полный JSON backup, ICS/CSV/PDF как форматы обмена/печати.
- Billing: POST /billing/apple/verify, /billing/google/verify, /billing/restore; GET /billing/entitlements; официальные подписанные store webhooks.
- Holidays/config: GET /holiday-packs?country=&region=&year=, GET /app-config, GET /feature-flags.
- Privacy: POST /privacy/export, /privacy/rectify, /privacy/erase; GET /privacy/requests/{id}.
- Support/ops: POST /support/tickets; раздельные ограниченные health/readiness endpoints.

Схема включает users/devices, employers/calendars/shift_types/shifts, rotation_templates, personal_events, pay_rules/versions/pay_periods, shares/members, subscriptions/entitlements, sync_cursors/mutations, holiday_packs/entries, privacy_requests/consents, support_tickets и admin_audit_logs. Хранить денежные значения целыми минимальными единицами + ISO currency, не float. Смену хранить с исходным локальным временем и IANA timezone, UTC использовать для синхронизации/сортировки без потери DST семантики. Поиск начать с MySQL full-text и индексов owner/calendar/employer + start_at; не добавлять внешний поисковый кластер без подтверждённой нагрузки. Всегда ограничивать поиск scope пользователя, не допускать утечек через подсказки/counts.

## 5. Filament admin и эксплуатация

Filament 5 panel на /admin, отдельная защита/MFA и роли: Super Admin, Support, Billing, Content Manager, Privacy Officer, Auditor read-only. Не показывать расписание, заметки и зарплаты обычной поддержке. Панель включает пользователей с маскированными полями, верификацию, устройства/сессии, entitlement и store webhook ошибки, sync conflicts, версии приложения/feature flags, праздничные пакеты и переводы, privacy requests, поддержку, очередь/failed jobs, health, уведомления, роли и audit log, только агрегированные продуктовые метрики. Impersonation выключен; если понадобится, ограничить ролью, причиной, сроком и полным аудитом.

Local OSPanel, staging и production разделить БД, доменами, store apps и ключами. MySQL не публичен. TLS, firewall, least privilege, шифрование volumes/backups, secret manager, ротация ключей, MFA admins, ограниченное хранение логов, мониторинг, backup restore drill и incident runbook. Redis включать, если есть конкретные задачи очередей/rate limit/cache и измеренная потребность, не «на всякий случай».

## 6. GDPR, безопасность и Firebase Analytics

До публикации подготовить карту данных, purposes/legal basis, сроки хранения, список processors/subprocessors и DPA, применимые механизмы передачи данных за пределы ЕЭЗ, privacy notice, terms, UX согласий, процесс обращений и incident/breach runbook; финальные тексты и модель обработки проверить с профильным юристом. Размещение в Болгарии остаётся в ЕС, но не заменяет такой анализ.

Пользователь может запросить доступ, исправление, ограничение, машиночитаемый полный JSON экспорт, отзыв согласия/интеграций и удаление аккаунта. Запросы обрабатывать в установленные законом сроки (как правило, GDPR предусматривает один месяц; о правомерном продлении уведомлять в первоначальный срок) после проверки личности. Удаление доступно в приложении, требует повторной аутентификации, показывает последствия и статус. Оно отзывает сессии, токены, устройства и общие ссылки сразу; ставит полное удаление облачных сущностей, вложений и индексов; tombstone не даёт данным воскреснуть при sync/backup. Политика отдельно описывает удаление из backup по циклу ротации и ограниченные сроки хранения обязательных платежных записей. Удаление аккаунта не отменяет подписку в Apple/Google — приложение должно дать инструкцию/переход к отмене в магазине.

Firebase Analytics — allowlist минимальных событий: onboarding завершён, агрегированное использование функций, показ paywall и результат покупки. Не передавать email, профиль ID, работодателя, заметки, даты смен, поисковые фразы, зарплату, геолокацию, токены или пользовательский текст. Давать отключение/управление аналитикой; конфигурацию сбора до согласия проверить для целевых рынков и платформ. Не включать рекламную персонализацию/ID, если они не нужны. Проверить реальные payload сетевым инспектором. Firebase не используется как база, auth или backup.

Безопасность: TLS клиент/API/DB, закрытый доступ MySQL, SQLCipher local DB, шифрование дисков/резервных копий, секреты не в коде/репозитории, ключи отдельно в Keychain/Keystore/KMS, least privilege, MFA, rate limiting, ограниченные логи, аудит privileged действий без личных payload, мониторинг и тест восстановления. Envelope/field encryption оценить по threat model для выбранных чувствительных данных с учётом того, что это затрудняет поиск. План инцидентов назначает владельца и реализует сроки/уведомления, требуемые применимым правом.

## 7. Полный объём первого публичного релиза

До публикации закрыть весь функционал исходного ТЗ и feature matrix референса: month/week/agenda/day/today; несколько смен за день; работодатели/типы/цвета/иконки; rotations, preview/conflicts/undo/bulk; личные события, holidays и выбранные внешние календари; отчёты, часы и оценки дохода; breaks и pay rules; поиск и фильтры; напоминания и платформенно корректные сменные будильники; JSON/ICS/CSV/PDF/печать; системная интеграция; widgets/lockscreen и только подтверждённые для выбранной платформы дополнительные system integrations; облачная синхронизация/backup; share links/permissions/revoke; покупки/restore; Shortcuts/App Intents и прочие системные интеграции только если включены в проверенную feature matrix и поддерживаются конкретной платформой/версией ОС; seven locales; аккаунт/data rights; Filament operations panel. Функциональная матрица обязательна по платформе, версии OS, тарифу, API, offline поведению, разрешениям и acceptance test. Недоступная системная возможность должна иметь проверенный fallback.

## 8. Пошаговая последовательность и gates

### Этап 0 — требования и два репозитория
Разобрать ТЗ в requirement IDs и traceability matrix (требование → экран/domain → таблица/API → тест → store permission). Повторно исследовать актуальный референс на конкретных платформах/устройствах, собрать requirement-by-requirement feature matrix по бесплатным/платным функциям и зафиксировать дату, версию приложения, OS, locale и доказательство каждого пункта. Устранить противоречия по языкам/ценам/релизу. Утвердить free limits, cloud quota/Lifetime economics, data map, retention. Создать отдельные Git-репозитории rn-proj и backend, записать общие документы в backend/docs/product, определить OpenAPI ownership, release/version compatibility и независимые CI.
Gate: все требования имеют ID и acceptance criteria; нет неразрешённых противоречий; подтверждённая матрица референса покрывает обе платформы; оба репозитория собираются независимо; документация и OpenAPI имеют версии; открытые внешние зависимости назначены владельцам; секретов/пользовательских данных в Git нет.

### Этап 1 — продуктовые потоки и единый UI
Сначала закрыть screen inventory/requirement traceability и user flows для accountless, account/link/merge, calendar, cycle, jobs/pay, reports/search, paywall/restore, import/export, sync/conflicts/backup, sharing/revoke, holiday packs, privacy/delete, support и OS permissions. Подготовить reviewable макеты каждого экрана и применимых состояний для iOS/Android, token/component library, оригинальные assets и все 7 локалей с проверкой длинных строк. Провести UX/accessibility review на минимальных поддерживаемых экранах, light/dark, Dynamic Type, VoiceOver/TalkBack и Reduce Motion. UI кодирование начинается после design freeze; domain/backend работы могут идти параллельно с утверждёнными контрактами и без визуальных заглушек.
Gate: ни одного requirement ID без экрана/потока; все сценарии и состояния из ТЗ имеют рассмотренные макеты обеих платформ; UI kit/tokens/assets/licences и тексты локалей закреплены версией; accessibility и длинные переводы прошли ревью; нет блокирующих UX-развилок.

### Этап 2 — технический фундамент и доменная модель
Создать приложения в отдельных репозиториях, типы Node/PHP, окружения OSPanel, migrations и OpenAPI skeleton. Спроектировать SQLite↔MySQL schema, auth/devices, support/privacy/billing/historical records, versioning и timestamps. Реализовать тестируемые чистые domain engines: даты/DST, cycles, conflict policy, часы, доход/округления, импорт/экспорт.
Gate: миграции версионируются, fixtures воспроизводимы, математические и временные edge cases определены.

### Этап 3 — полный offline-first клиент
Реализовать дизайн-систему, автодетект языка/region, SQLite/миграции; Today/month/week/agenda/day, смены, работодатели, типы, события, циклы/preview/undo/bulk, праздники, локальные напоминания, доступность и system calendar.
Gate: весь основной календарь работает без сети/аккаунта, данные сохраняются после перезапуска, DST/границы периодов пройдены.

### Этап 4 — доход, отчёты, поиск, импорт/экспорт
Реализовать ставки и snapshots, breaks, planned/actual, overtime rules, отчёты, полнотекстовый поиск, filters, JSON backup/restore, ICS/CSV/PDF/print. Зафиксировать free/premium gates без потери данных.
Gate: суммы прозрачны и раздельны по валютам, история не пересчитывается молча, импорт имеет preview и атомарность.

### Этап 5 — Laravel API и Filament
Реализовать auth, profile, CRUD, API policies, search/reports, API contract, sync primitives, holiday content, support jobs, billing webhooks. Настроить роли/MFA/audit/admin resources.
Gate: интеграционные и авторизационные проверки проходят; другой пользователь не может прочитать чужой объект; Firebase не участвует в сохранении.

### Этап 6 — облачная синхронизация, sharing и GDPR lifecycle
Добавить offline outbox, pull/push/cursor, retries, conflict preview, tombstones, account linking, cloud backup quotas, share scopes/expiry/revoke, export/delete workflows и backup retention.
Gate: несколько устройств синхронизируются; удалённое не воскресает; экспорт/удаление проверены до конца, включая обработку резервных копий.

### Этап 7 — Billing и системные интеграции
Подключить StoreKit/Google billing, server verify, restore/refunds/grace/trial/cancel; widgets, Watch и другие системные интеграции из подтверждённой feature matrix — с адаптерами/fallbacks. Не обещать Siri/AI/alarm parity без проверки конкретных ОС и версии референса.
Gate: sandbox end-to-end и физические устройства обеих платформ; покупка восстановима, capability matrix соблюдена.

### Этап 8 — Firebase Analytics, hardening, content
Добавить allowlist событий, consent/opt-out и проверку payload. Настроить TLS, secrets, DB firewall, backups и restore drill, monitoring, crash payload filtering, security review. Подготовить праздничные данные с официальными источниками/лицензиями/годами и переводы.
Gate: сетевой payload не содержит приватных полей; восстановление из backup выполнено; admin actions аудируются.

### Этап 9 — регрессия и выпуск полного приложения
Unit/integration/API/E2E tests, устройства, локали, сети/permissions, physical widgets/notifications/purchases/Watch. Проверить privacy manifests, App Store/Google Play disclosures, account deletion, юридические тексты, IAP, локализованные listing/screenshots, поддержку, staged rollout, мониторинг и rollback.
Gate публикации: все requirement IDs закрыты; нет открытых блокирующих/P1-дефектов; store sandbox, privacy, export/delete, backups и production alerts проверены.

## 9. Дополнительные приёмочные сценарии

1. Accountless локальные данные без сети; позже вход на втором устройстве объединяет данные без потери/дублей.
2. Несколько смен в день, через полночь, DST, поездка/timezone, два работодателя с пересекающимися сменами.
3. Изменение шаблона не переписывает прошлые экземпляры; future bulk update даёт preview, выбранную стратегию конфликтов и undo.
4. Смена ставки не меняет исторические snapshots; breaks/overtime/rounding воспроизводимы, валюты не суммируются молча.
5. Search/share не открывает чужие записи и не создаёт утечек через snippets/counts.
6. Повторный sync push после timeout не дублирует; delete tombstone сохраняется через offline/restore.
7. Полный JSON export восстанавливается на новом устройстве; повреждённый импорт не частично портит данные.
8. Удаление отзывается на всех устройствах/ссылках, сообщает статус backup retention и отделяет отмену Store подписки.
9. Analytics consent/opt-out меняет сбор; проверен реальный payload и запрещённые поля отсутствуют.
10. Filament роли ограничены; каждое чтение/изменение чувствительных данных имеет audit trail.
11. Язык ro общий, RO/MD отличаются региональным content; auto-detect, fallback en и override переживают restart.
12. Отказ в OS permissions не отключает календарь и имеет понятный fallback.
13. Покупка проходит pending/verified/grace/expired/refunded/restore сценарии и не блокирует базовые данные.
14. Длинные переводы, крупный шрифт, VoiceOver/TalkBack и 12/24-hour/date formats работают на обеих платформах.
15. Регистрация и подтверждение email, восстановление пароля, привязка локальных записей, preview merge и ошибка переноса проходят без потери исходных данных.
16. Import валидирует и показывает изменения до commit; ошибка/повтор не создают частичный импорт или дубликаты.
17. Search/filter не попадает в аналитику; sharing имеет минимальный scope/expiry/revoke; истёкшая или отозванная ссылка закрывает доступ.
18. Privacy export/delete показывает статус; отзыв доступа немедленный, завершение backup cleanup укладывается в заявленную retention policy.
19. До UI-кодирования все requirement IDs связаны с утверждёнными iOS/Android макетами и states; зафиксированные tokens и компоненты повторно используются без экранных локальных копий стилей.

## 10. Внешние решения, нужные до соответствующих этапов

До локальной разработки не нужны production secrets. К моменту signing/billing понадобятся Apple Developer и Google Play Console accounts, bundle IDs и sandbox products. До staging/production нужны домен, болгарский хостинг, email provider, DPA/subprocessor список и бюджет cloud backup. До store submission нужны legal controller/contact details, support address, privacy/terms и утверждённые сроки хранения. До заморозки native features выбрать минимальные версии ОС и список поддерживаемых Watch/widgets/alarms; зафиксировать на каждую функцию entitlement, API ограничения, fallback и поддерживаемые устройства. Секреты передавать только через защищённые кабинеты/secret manager, не через чат.

### Решения, которые блокируют соответствующий gate

Эти вопросы не мешают проектированию и локальной разработке, но должны быть закрыты владельцем продукта/техническим владельцем до перехода к указанному этапу:
- До feature freeze: минимальные версии iOS/Android и поустройственная матрица widgets, notifications/alarms, calendar integration и прочих native features; измеримые fallbacks.
- До staging: болгарский hosting/provider, email, объектное хранилище при необходимости, cloud quota, backup retention, утверждённые RPO/RTO, бюджет и restore drill.
- До billing: точные лимиты Free/Premium, trial, store product IDs, ценовые ступени каждого storefront, Lifetime cloud economics и правила возвратов/отмены. RON — ценовая база, а не гарантия идентичной суммы на каждом storefront.
- До store submission: controller/legal contacts, support, legal review, privacy/terms, data map, retention, processor/DPA disclosures и store declarations.
- До holiday content release: владелец контента, официальные данные и разрешение на использование для каждой страны/города, частота обновления и политика устаревших наборов.
- До брендового запуска: проверка торгового имени, доменов, store listings и оригинальности визуальных материалов.
- В этапе 0: датированная проверка референса на реальных поддерживаемых платформах; зафиксировать паритет функций/тарифов, а не выводить его из одной карточки магазина.
## 11. Официальные технические источники

- Expo SDK 58 / React Native 0.88: https://expo.dev/changelog/sdk-58 и https://docs.expo.dev/versions/v58.0.0/
- Laravel 13: https://laravel.com/framework/docs/releases
- Filament 5: https://filamentphp.com/docs/5.x/introduction/installation
- MySQL 9.7: https://dev.mysql.com/doc/relnotes/mysql/9.7/en/ и https://dev.mysql.com/doc/refman/9.7/en/mysql-releases.html
- Laravel API routing/rate limit: https://laravel.com/framework/docs/13.x/routing и https://laravel.com/framework/docs/rate-limiting
- Цены App Store / Google Play: https://developer.apple.com/help/app-store-connect/manage-subscriptions/manage-pricing-for-auto-renewable-subscriptions/ и https://support.google.com/googleplay/android-developer/answer/1153481
- Firebase Analytics collection controls: https://firebase.google.com/docs/analytics/ios/configure-data-collection?platform=ios и https://firebase.google.com/docs/analytics/android/configure-data-collection
- GDPR individual rights (European Commission): https://commission.europa.eu/law/law-topic/data-protection/reform/rights-citizens/how-my-personal-data-protected/how-should-my-consent-be-requested_en

План продуктово-технический, не юридическое заключение. Перед публикацией проверить финальную схему обработки данных, интеграции/SDK, условия хостинга и store disclosures с профильным специалистом.
## 12. Карта покрытия исходного ТЗ

Эта карта связывает исходные разделы с реализацией. Все требования остаются в scope, кроме старого MVP-раздела 22, который явно заменён решением в разделе 23 исходного ТЗ. На этапе 0 каждому требованию присваивается ID и добавляется ссылка на конкретный экран, доменную функцию, endpoint/таблицу и проверку.

- Разделы 0–3: правила чтения, цели продукта, сравнение Supershift и ограничения самостоятельного дизайна → этап 0 и 1; контроль: полный список feature parity, бренд/правовая проверка.
- Раздел 4.1: версии и библиотеки клиента/backend → этап 2; контроль: official stable versions, совместимость, lock files.
- Раздел 4.2: структура React Native проекта и модульность → этап 2; контроль: feature-first границы, reusable UI и отсутствие дублирования доменной логики.
- Разделы 4.3–4.5: Laravel folders, окружения и независимые CI двух Git-репозиториев → этапы 0, 2 и 5; контроль: OSPanel document root backend/public, OpenAPI в backend, совместимость клиента, разделённые dev/staging/prod секреты и CI.
- Раздел 5: языки, регион, валюты, универсальные и региональные праздники → этапы 0, 3, 8; контроль: семь языков, ro общий, RO/MD отдельные пакеты, источники/лицензии/год данных.
- Раздел 6: информационная архитектура и вкладки → этап 1; контроль: every user flow mapped to screen/navigation and deep links.
- Раздел 7: визуальная система, темы, типографика, сетка, иконки, анимация → этап 1; контроль: tokens/shared components, light/dark, accessibility and original assets.
- Раздел 8: onboarding, manual shift, rotation and purchase journeys → этапы 1, 3, 4, 7; контроль: end-to-end сценарии с offline/error states.
- Раздел 9.1–9.8: запуск, locale, onboarding, Today, month/week/agenda/day → этап 3.
- Раздел 9.9–9.13: создание/редактирование смен, типы, cycles, preview, bulk edit → этапы 2–4.
- Разделы 9.14–9.17: работодатели, ставки/pay rules, reports, breaks → этап 4 и API/report modules на этапе 5.
- Разделы 9.18–9.20: события/календари, sharing, export/print → этапы 3, 4, 6.
- Разделы 9.21–9.24: widgets, notifications, shift alarm, cloud/sync → этапы 3, 6, 7.
- Разделы 9.25–9.27: paywall, settings/privacy, help/FAQ → этапы 1, 6–9.
- Раздел 10.1–10.7: циклы, ночь/DST, часы, зарплата, конфликты, удаление → domain engine на этапе 2, реализация на этапах 3–6, обязательные regression tests этапа 9.
- Разделы 11.1–11.12: work calendars, employer, shift type/instance, rotation, pay rules, events, reminders, settings, sync, subscription → SQLite/MySQL schema и migrations этапы 2, 5, 6, 7.
- Раздел 12: обязательный backend API → Laravel/OpenAPI на этапе 5 и конкретные endpoint groups раздела 4 этого плана.
- Раздел 13: freemium, цены, trial, purchase state, entitlement и paywall analytics → этапы 4, 7, 8; контроль: store sandbox и цена из StoreKit/Play.
- Разделы 14–15: data minimization, analytics, privacy/security → архитектура раздела 6 этого плана и этап 8; контроль: фактический Firebase payload, account rights, retention и backups.
- Раздел 16: ошибки, сеть, permissions, storage, DST, purchase и миграции → негативные сценарии этапов 3–9.
- Раздел 17: исходная приёмка → расширена разделом 9 этого плана; каждая приёмка получает автоматическую или ручную проверку и owner.
- Раздел 18: последовательность релиза → заменена этапами 0–9 этого плана; они не задают отдельные публичные версии.
- Раздел 19: ASO и market launch → этап 9; проверить локальные ключи, listing, реальные скриншоты и корректность store claims.
- Раздел 20: продуктовые/технические риски → risk register этапа 0 и регулярное обновление до store release.
- Раздел 21: источники исследования → повторная проверка актуальности на этапе 0 и перед store listing/feature parity freeze.
- Раздел 22: старый MVP-объём → superseded, не использовать для ограничения первого релиза.
- Раздел 23: решения владельца, backend/Filament/Firebase/privacy/цены/релиз → authoritative requirements на все этапы этого плана.
