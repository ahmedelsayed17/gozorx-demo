# GOZORX Laravel API foundation

This is a Laravel API starter layer, not a deployed backend. It is intended to be copied into a fresh Laravel 12 application.

## Create the Laravel app
```bash
composer create-project laravel/laravel gozorx-api
cd gozorx-api
php artisan install:api
```
Choose Sanctum when prompted if the installer asks. Configure PostgreSQL in `.env` (DB_CONNECTION=pgsql and credentials), then copy the files from this folder into the matching Laravel paths.

## Configure
1. Copy `routes/api.php` to the Laravel project's `routes/api.php`.
2. Copy the controller, middleware, and migrations into their matching paths.
3. Ensure `bootstrap/app.php` loads API routes (Laravel 12's `install:api` does this).
4. Run `php artisan migrate`.
5. Create an admin user through a secure, one-time seeder or tinker command; never expose public admin registration.
6. Run `php artisan serve` for local testing.

## Included endpoints
- POST /api/register — buyer account registration
- POST /api/login — token login
- POST /api/logout — revoke current token
- GET /api/me — authenticated account
- POST /api/supplier/apply — submit supplier application
- GET /api/admin/suppliers — admin-only pending/filtered supplier list
- PATCH /api/admin/suppliers/{supplier}/decision — admin approve/reject
- POST /api/supplier/products — approved suppliers only; creates draft product

## Important
- This is starter code; it has not been installed, migrated, or integration-tested against a live PostgreSQL database.
- Set `APP_DEBUG=false` and a strong unique `APP_KEY` in production.
- Use HTTPS, rate limiting, email/phone verification, password reset, secure file storage, audit logging, and backups before launch.
- Do not store card data or claim payment collection until a licensed payment provider is integrated.
- The frontend demo is static and is not connected to these endpoints yet.
