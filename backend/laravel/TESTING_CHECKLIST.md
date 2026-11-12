# GOZORX API — setup and verification checklist

This folder is starter code, not a complete Laravel application. It must be installed into a fresh Laravel 12 project before it can run.

## Required setup

1. Create the application with Composer: composer create-project laravel/laravel gozorx-api
2. Run php artisan install:api to install Sanctum and enable API routes.
3. Configure PostgreSQL in the .env file.
4. Copy this starter folder into the matching Laravel application paths.
5. Merge the aliases from bootstrap/app.middleware-snippet.php into the existing withMiddleware callback in bootstrap/app.php.
6. Check that the default Laravel users migration does not conflict with the GOZORX user-fields migration.
7. Run php artisan migrate and resolve migration issues before exposing the API.

## Effective API routes

Laravel adds /api to routes/api.php; this file adds the v1 prefix:

- POST /api/v1/register
- POST /api/v1/login
- POST /api/v1/logout
- GET /api/v1/me
- POST /api/v1/supplier/apply
- GET /api/v1/admin/suppliers
- PATCH /api/v1/admin/suppliers/{supplier}/decision
- POST /api/v1/supplier/products

## Mandatory verification before deployment

- Registration creates a buyer only; the browser cannot choose admin or supplier role.
- Unauthenticated requests receive 401.
- A buyer cannot access admin endpoints or supplier product creation.
- Pending, rejected, and suspended suppliers cannot create products.
- An approved supplier can create a draft product.
- Only an admin can approve or reject suppliers.
- Supplier decisions are written to audit logs.
- User responses never expose password hashes or private fields.
- Configure HTTPS, rate limits, CORS, secrets, backups, and production error handling.

## Current limitations

- No Laravel framework skeleton, Composer lockfile, .env, or hosted PostgreSQL database is included.
- No live integration tests have been run.
- Review migrations for duplicate/conflicting users columns before running them.
- Add private object storage for supplier documents and complete audit logging.
- Do not deploy until the checklist passes.
