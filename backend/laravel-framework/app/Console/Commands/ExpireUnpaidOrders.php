<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'app:expire-unpaid-orders';

    protected $description = 'Cancel expired unpaid orders and release reserved stock';

    public function handle(): int
    {
        $expiredCount = 0;
        $failedCount = 0;

        Order::query()
            ->where('status', 'pending_payment')
            ->whereNotNull('deposit_expires_at')
            ->where('deposit_expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$expiredCount, &$failedCount) {
                foreach ($orders as $candidate) {
                    try {
                        $expired = DB::transaction(function () use ($candidate) {
                            $order = Order::query()
                                ->whereKey($candidate->id)
                                ->lockForUpdate()
                                ->first();

                            if (
                                !$order ||
                                $order->status !== 'pending_payment' ||
                                !$order->deposit_expires_at ||
                                $order->deposit_expires_at->isFuture()
                            ) {
                                return false;
                            }

                            $depositTransaction = $order->paymentTransactions()
                                ->where('payment_type', 'deposit')
                                ->lockForUpdate()
                                ->first();

                            if (
                                $depositTransaction &&
                                $depositTransaction->status === 'pending' &&
                                $depositTransaction->receipt_path &&
                                $depositTransaction->receipt_uploaded_at &&
                                $depositTransaction->receipt_uploaded_at->lte(
                                    $order->deposit_expires_at
                                )
                            ) {
                                return false;
                            }

                            $individualItem = $order->individualItem()
                                ->lockForUpdate()
                                ->first();

                            if ($individualItem) {
                                if ($order->stock_released_at === null) {
                                    if ($individualItem->product_id !== null) {
                                        $product = Product::query()
                                            ->whereKey($individualItem->product_id)
                                            ->lockForUpdate()
                                            ->first();

                                        if ($product) {
                                            $quantity = (int) $individualItem->quantity;
                                            $reserved = (int) $product->reserved_quantity;

                                            if ($reserved < $quantity) {
                                                throw new RuntimeException(
                                                    "Insufficient reserved stock for product {$product->id}"
                                                );
                                            }

                                            $product->decrement(
                                                'reserved_quantity',
                                                $quantity
                                            );
                                        }
                                    }

                                    $order->stock_released_at = now();
                                }

                                $order->status = 'cancelled';
                                $order->save();

                                return true;
                            }

                            // Preserve the legacy supplier-order expiry workflow.
                            $supplierOrders = $order->supplierOrders()
                                ->lockForUpdate()
                                ->get();

                            foreach ($supplierOrders as $supplierOrder) {
                                if ($supplierOrder->stock_released_at !== null) {
                                    continue;
                                }

                                $items = $supplierOrder->items()->get();

                                foreach ($items as $item) {
                                    if ($item->product_id === null) {
                                        continue;
                                    }

                                    $product = Product::query()
                                        ->whereKey($item->product_id)
                                        ->lockForUpdate()
                                        ->first();

                                    if (!$product) {
                                        continue;
                                    }

                                    $quantity = (int) $item->quantity;
                                    $reserved = (int) $product->reserved_quantity;

                                    if ($reserved < $quantity) {
                                        throw new RuntimeException(
                                            "Insufficient reserved stock for product {$product->id}"
                                        );
                                    }

                                    $product->decrement(
                                        'reserved_quantity',
                                        $quantity
                                    );
                                }

                                $supplierOrder->status = 'cancelled';
                                $supplierOrder->stock_released_at = now();
                                $supplierOrder->save();
                            }

                            $order->status = 'cancelled';
                            $order->save();

                            return true;
                        });

                        if ($expired) {
                            $expiredCount++;
                            $this->info("Expired order: {$candidate->order_number}");
                        }
                    } catch (Throwable $e) {
                        $failedCount++;
                        $this->error(
                            "Failed order {$candidate->id}: {$e->getMessage()}"
                        );
                    }
                }
            });

        $this->info("Expired orders: {$expiredCount}");
        $this->info("Failed orders: {$failedCount}");

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
