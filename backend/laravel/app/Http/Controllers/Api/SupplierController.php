<?php

namespace App\\Http\\Controllers\\Api;

use App\\Http\\Controllers\\Controller;
use App\\Models\\SupplierProfile;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;

class SupplierController extends Controller
{
    public function apply(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required','string','max:200'],
            'business_type' => ['nullable','string','max:100'],
            'description' => ['nullable','string','max:5000'],
        ]);

        if ($request->user()->supplierProfile()->exists()) {
            return response()->json(['message' => 'يوجد طلب مورد مرتبط بهذا الحساب بالفعل.'], 409);
        }

        $supplier = $request->user()->supplierProfile()->create($data + [
            'status' => 'pending_approval',
            'submitted_at' => now(),
        ]);

        return response()->json(['message' => 'تم استلام طلب الانضمام للمراجعة.', 'supplier' => $supplier], 201);
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending_approval');
        $allowed = ['pending_approval','approved','rejected','suspended'];
        abort_unless(in_array($status, $allowed, true), 422, 'Invalid supplier status.');

        return SupplierProfile::with('user:id,full_name,email,phone')
            ->where('status', $status)->latest()->paginate(25);
    }

    public function decide(Request $request, SupplierProfile $supplier)
    {
        $data = $request->validate([
            'decision' => ['required','in:approved,rejected'],
            'review_note' => ['nullable','string','max:2000'],
        ]);

        DB::transaction(function () use ($request, $supplier, $data) {
            $supplier->update([
                'status' => $data['decision'],
                'review_note' => $data['review_note'] ?? null,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id,
                'action' => 'supplier.' . $data['decision'],
                'entity_type' => 'supplier_profile',
                'entity_id' => $supplier->id,
                'details' => json_encode(['review_note' => $data['review_note'] ?? null]),
                'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'تم تحديث قرار مراجعة المورد.', 'supplier' => $supplier->fresh()]);
    }
}
