<?php
// Merge this alias registration into the withMiddleware() callback in
// the fresh Laravel 12 application's bootstrap/app.php. Do not replace that file.

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSupplierApproved;

$middleware->alias([
    'role' => EnsureRole::class,
    'supplier.approved' => EnsureSupplierApproved::class,
]);
