<?php

namespace App\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

class EnsureSupplierApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $supplier = $request->user()?->supplierProfile;
        abort_unless($supplier && $supplier->status === 'approved', 403, 'يجب اعتماد حساب المورد أولًا.');
        return $next($request);
    }
}
