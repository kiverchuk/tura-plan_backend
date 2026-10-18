# TuraPlan backend

Laravel 13 API (`/api/v1`) and Filament 5 admin. This is a separate repository from `rn-proj/`; the root directory is only a workspace container. Set the web document root to `backend/public`. Never commit `.env`, store receipts, credentials, or user data.

## Requirements

PHP 8.3+, Composer 2, MySQL 9.x. The development host uses PHP 8.4 and Composer 2 through OSPanel. SQLite is supported for fast local tests; production and staging must use the configured MySQL version. The app skeleton and locked dependencies are included in this repository.

## Local site

OSPanel serves this API the same way as QuickPost. The workspace folder `work-calendar` contains `.osp/project.ini`: PHP 8.4, document root `{base_dir}/backend/public`. After an OSPanel restart the host is `http://work-calendar`.

The web client uses `EXPO_PUBLIC_API_URL_WEB` (`http://work-calendar`). iOS and Android builds use `EXPO_PUBLIC_API_URL_NATIVE` (`https://wc.bruisergames.online`).

1. `composer install`
2. Copy `.env.example` to `.env`, keep `APP_URL=http://work-calendar`, set the OSPanel MySQL root password, then `php artisan key:generate`
3. `php artisan migrate --seed`

Run `composer lint` and `composer test` before opening a backend PR. CI runs both against an isolated SQLite database.

## Store integrations

Apple App Store Connect and Google Play verification are implemented behind server-side credentials. Set the `APPLE_*` and `GOOGLE_PLAY_*` values from `.env.example` in the deployment secret store, along with `GOOGLE_PUBSUB_AUDIENCE` and `GOOGLE_PUBSUB_SERVICE_ACCOUNT` for Google webhook OIDC verification. Credential and certificate file paths must point outside the public web root. Do not add real values to this repository. Until configured, store verification returns a deliberate service-unavailable response and does not grant entitlement.

The versioned product requirements are in `docs/product/`; the API source of truth is `docs/openapi/openapi.yaml`.

## Production deploy

Pushing `main` runs `.github/workflows/deploy.yml`. It SSHes to every target and runs `deploy/deploy.sh`. The script installs Composer and npm dependencies, builds Vite assets, migrates, caches config, and restarts the queue worker. It does not edit `.env` or cron.

Repository secrets, same shape as the QuickPost deploy:

- `SSH_PRIVATE_KEY` — private key that can pull the repo and log in to each server.
- `DEPLOY_TARGETS` — JSON array. Example:

```json
[
  {
    "name": "bulgaria",
    "host": "example.com",
    "port": 22,
    "user": "deploy",
    "owner": "turaplan",
    "path": "/var/www/turaplan",
    "php": "/opt/php84/bin/php",
    "node": "24",
    "branch": "main",
    "repo": "git@github.com:ORG/turaplan-backend.git",
    "queue_service": "turaplan-queue"
  }
]
```

On the server, create the site the same way: the nginx root is this repository's `public` directory. Nginx reaches PHP-FPM directly. The deploy script only updates files, dependencies, migrations, and the queue worker. It does not start a web server. Clone the repository once before the first deploy. Store credentials stay in the server `.env`, not in git.
