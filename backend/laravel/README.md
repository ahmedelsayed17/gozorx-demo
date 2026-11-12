# GOZORX Laravel API foundation

This is a Laravel API starter layer, not a deployed backend. It is intended to be copied into a fresh Laravel 12 application.

## Create the Laravel app
```bash
composer create-project laravel/laravel gozorx-api
cd gozorx-api
php artisan install:api
```
Configure PostgreSQL in the .env file, then copy the files from this folder into the matching Laravel paths. Merge the middleware aliases from bootstrap/app.middleware-snippet.php into bootstrap/app.php.

## Configure
1. Copy routes/api.php to the Laravel project's routes/api.php.
2. Copy the controller, model, middleware, and migrations into their matching paths.
3. Ensure bootstrap/app.php loads API routes and registers the role and supplier.approved middleware aliases.
4. Review the users migration for conflicts, then run php artisan migrate.
5. Create an admin user through a secure, one-time seeder or tinker command; never expose public admin registration.
6. Run php artisan serve for local testing.

## Included endpoints
- POST /api/v1/register — buyer account registration
- POST /api/v1/login — token login
- POST /api/v1/logout — revoke current token
- GET /api/v1/me — authenticated account
- POST /api/v1/supplier/apply — submit supplier application
- GET /api/v1/admin/suppliers — admin-only pending/filtered supplier list
- PATCH /api/v1/admin/suppliers/{supplier}/decision — admin approve/reject
- POST /api/v1/supplier/products — approved suppliers only; creates draft product

## Important
- This is starter code; it has not been installed, migrated, or integration-tested against a live PostgreSQL database.
- Set APP_DEBUG=false and a strong unique APP_KEY in production.
- Use HTTPS, rate limiting, email/phone verification, password reset, secure file storage, audit logging, and backups before launch.
- Do not store card data or claim payment collection until a licensed payment provider is integrated.
- The frontend demo is static and is not connected to these endpoints yet.
