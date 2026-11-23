<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerRfqController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'expires_at' => ['required', 'date', 'after:now'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.specifications' => ['nullable', 'string', 'max:10000'],
        ]);

        if ($request->user()->role !== 'buyer') {
            abort(403, 'إنشاء طلبات عروض الأسعار متاح لحسابات المشترين فقط.');
        }

        $rfq = DB::transaction(function () use ($request, $data) {
            $rfq = $request->user()->rfqRequests()->create([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'open',
                'expires_at' => $data['expires_at'],
            ]);

            foreach ($data['items'] as $item) {
                $rfq->items()->create([
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? 'قطعة',
                    'specifications' => $item['specifications'] ?? null,
                ]);
            }

            return $rfq;
        });

        $rfq->load('items');

        return response()->json([
            'message' => 'تم إنشاء طلب عرض السعر بنجاح.',
            'data' => $rfq,
        ], 201);
    }
}
