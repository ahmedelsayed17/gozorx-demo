<?php

use App\Http\Controllers\Api\AdminOrderController;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::connection();

if (
    $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'gozorx_concurrency_test'
) {
    fwrite(
        STDERR,
        "ERROR: This test can only run on the isolated gozorx_concurrency_test MySQL database.\n"
    );

    exit(2);
}

DB::purge();
DB::reconnect();

$tag = 'RACE-' . strtoupper(bin2hex(random_bytes(4)));

$admin = User::create([
    'name' => 'Concurrency Admin',
    'full_name' => 'Concurrency Admin',
    'email' => strtolower($tag) . '-admin@example.test',
    'password' => Hash::make('temporary-test-password'),
    'role' => 'admin',
]);

$supplierUser = User::create([
    'name' => 'Concurrency Supplier',
    'email' => strtolower($tag) . '-supplier@example.test',
    'password' => Hash::make('temporary-test-password'),
    'role' => 'buyer',
]);

$supplier = $supplierUser->supplierProfile()->create([
    'business_name' => 'Concurrency Test Nursery',
    'status' => 'approved',
]);

$product = Product::create([
    'supplier_id' => $supplier->id,
    'name_ar' => 'منتج اختبار التزامن',
    'unit_name' => 'شتلة',
    'unit_price' => 100,
    'minimum_order_quantity' => 1,
    'available_quantity' => 1,
    'status' => 'published',
]);

$product->reserved_quantity = 0;
$product->save();

$orders = [];

for ($i = 1; $i <= 2; $i++) {
    $buyer = User::create([
        'name' => "Concurrency Buyer {$i}",
        'email' => strtolower($tag) . "-buyer{$i}@example.test",
        'password' => Hash::make('temporary-test-password'),
        'role' => 'buyer',
    ]);

    $order = Order::create([
        'buyer_id' => $buyer->id,
        'supplier_id' => $supplier->id,
        'order_number' => "{$tag}-{$i}",
        'status' => 'pending_shipping_cost',
        'payment_method' => 'instapay',
        'payment_status' => 'pending',
        'recipient_name' => "Test Buyer {$i}",
        'recipient_phone' => '01000000000',
        'governorate' => 'Cairo',
        'city' => 'Nasr City',
        'address' => 'Concurrency Test Address',
        'subtotal' => 100,
        'shipping_total' => 0,
        'grand_total' => 100,
        'deposit_amount' => 30,
        'balance_amount' => 70,
    ]);

    $order->individualItem()->create([
        'product_id' => $product->id,
        'product_name_ar' => 'منتج اختبار التزامن',
        'product_name_en' => 'Concurrency Test Product',
        'unit_name' => 'شتلة',
        'unit_price' => 100,
        'quantity' => 1,
        'line_total' => 100,
    ]);

    $orders[] = $order;
}

$adminId = $admin->id;
$productId = $product->id;
$resultFiles = [];
$workers = [];

DB::disconnect();

foreach ($orders as $index => $order) {
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

    if ($pair === false) {
        throw new RuntimeException('Could not create worker synchronization socket.');
    }

    $resultFile = sys_get_temp_dir() . "/gozorx-race-result-{$tag}-{$index}.json";
    $resultFiles[] = $resultFile;

    $pid = pcntl_fork();

    if ($pid === -1) {
        throw new RuntimeException('Could not fork worker process.');
    }

    if ($pid === 0) {
        fclose($pair[0]);

        fwrite($pair[1], 'R');
        fread($pair[1], 1);

        try {
            DB::disconnect();
            DB::purge();
            DB::reconnect();

            $workerAdmin = User::findOrFail($adminId);
            $workerOrder = Order::findOrFail($order->id);

            $request = Request::create('/', 'PATCH', [
                'shipping_cost' => 10,
                'internal_notes' => 'Isolated MySQL concurrency test',
            ]);

            $request->setUserResolver(fn () => $workerAdmin);

            $startedAt = microtime(true);

            $response = app(AdminOrderController::class)
                ->setShippingCost($request, $workerOrder);

            $result = [
                'order_id' => $workerOrder->id,
                'http_status' => $response->getStatusCode(),
                'outcome' => $response->getStatusCode() === 200 ? 'success' : 'rejected',
                'elapsed_seconds' => round(microtime(true) - $startedAt, 4),
            ];
        } catch (ValidationException $e) {
            $result = [
                'order_id' => $order->id,
                'http_status' => 422,
                'outcome' => 'rejected',
                'errors' => $e->errors(),
            ];
        } catch (Throwable $e) {
            $result = [
                'order_id' => $order->id,
                'http_status' => 500,
                'outcome' => 'error',
                'error' => get_class($e) . ': ' . $e->getMessage(),
            ];
        }

        file_put_contents($resultFile, json_encode($result, JSON_PRETTY_PRINT));
        fclose($pair[1]);
        exit(0);
    }

    fclose($pair[1]);

    $workers[] = [
        'pid' => $pid,
        'socket' => $pair[0],
    ];
}

foreach ($workers as $worker) {
    fread($worker['socket'], 1);
}

foreach ($workers as $worker) {
    fwrite($worker['socket'], 'G');
    fclose($worker['socket']);
}

foreach ($workers as $worker) {
    pcntl_waitpid($worker['pid'], $status);
}

DB::purge();
DB::reconnect();

$results = [];

foreach ($resultFiles as $file) {
    $results[] = file_exists($file)
        ? json_decode(file_get_contents($file), true)
        : ['outcome' => 'missing_result'];
}

$freshProduct = Product::findOrFail($productId);

$orderStates = Order::whereIn('id', collect($orders)->pluck('id'))
    ->orderBy('id')
    ->get(['id', 'order_number', 'status', 'shipping_total', 'deposit_amount']);

$successCount = collect($results)
    ->where('outcome', 'success')
    ->count();

$summary = [
    'database_driver' => DB::connection()->getDriverName(),
    'test_tag' => $tag,
    'product' => [
        'id' => $freshProduct->id,
        'available_quantity' => $freshProduct->available_quantity,
        'reserved_quantity' => $freshProduct->reserved_quantity,
        'remaining_quantity' => $freshProduct->available_quantity - $freshProduct->reserved_quantity,
    ],
    'workers' => $results,
    'successful_reservations' => $successCount,
    'orders' => $orderStates,
    'shipping_history_count' => DB::table('order_shipping_histories')
        ->whereIn('order_id', collect($orders)->pluck('id'))
        ->count(),
    'deposit_transactions_count' => DB::table('payment_transactions')
        ->whereIn('order_id', collect($orders)->pluck('id'))
        ->where('payment_type', 'deposit')
        ->count(),
    'expected' => [
        'successful_reservations' => 1,
        'reserved_quantity' => 1,
        'shipping_history_count' => 1,
        'deposit_transactions_count' => 1,
    ],
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

foreach ($resultFiles as $file) {
    @unlink($file);
}

$passed = $successCount === 1
    && (int) $freshProduct->reserved_quantity === 1
    && $summary['shipping_history_count'] === 1
    && $summary['deposit_transactions_count'] === 1;

exit($passed ? 0 : 1);
