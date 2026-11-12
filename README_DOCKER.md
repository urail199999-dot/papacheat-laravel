# PAPACHEAT Laravel 13 (local Docker)

This is the local Laravel 13 foundation for the PAPACHEAT migration.

## Start

```bash
docker compose build
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Open http://localhost:8080.

Services: PHP 8.4/FPM, Nginx, MySQL 8.4, Redis 7 and Node 22/Vite.

The existing Cloudflare Worker site remains the production version until the Laravel application is fully ported. The next migration layers are authentication, catalog/products, checkout/orders, T‑Bank manual SBP, order chat/media, notifications, and the `urail1999` admin policy.
