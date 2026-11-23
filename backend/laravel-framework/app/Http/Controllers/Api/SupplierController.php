<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierDocument;
use App\Models\SupplierProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

class SupplierController extends Controller
{
    public function registerAndApply(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],

            'business_name' => ['required', 'string', 'max:200'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['required', 'string', 'max:2000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            'commercial_register' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'tax_card' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'personal_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'national_id' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $storedPaths = [];

        try {
            $result = DB::transaction(function () use ($data, $request, &$storedPaths) {
                $user = User::create([
                    'name' => $data['full_name'],
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => Hash::make($data['password']),
                    'role' => 'supplier',
                ]);

                $supplier = $user->supplierProfile()->create([
                    'business_name' => $data['business_name'],
                    'business_type' => $data['business_type'] ?? null,
                    'description' => $data['description'] ?? null,
                    'address' => $data['address'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'status' => 'pending_approval',
                    'submitted_at' => now(),
                ]);

                foreach (SupplierDocument::TYPES as $type) {
                    $file = $request->file($type);
                    $path = $file->store("supplier-documents/{$supplier->id}", 'local');

                    if (!$path) {
                        throw new RuntimeException("Failed to store supplier document: {$type}");
                    }

                    $storedPaths[] = $path;

                    $supplier->documents()->create([
                        'document_type' => $type,
                        'file_path' => $path,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'status' => 'pending_review',
                        'submitted_at' => now(),
                    ]);
                }

                return [$user, $supplier];
            });
        } catch (Throwable $exception) {
            if ($storedPaths) {
                Storage::disk('local')->delete($storedPaths);
            }
            throw $exception;
        }

        [$user, $supplier] = $result;

        return response()->json([
            'message' => 'تم إنشاء حسابك واستلام طلب المورد والمستندات الأربعة، وجميعها قيد المراجعة.',
            'user' => $user->only(['id', 'name', 'full_name', 'email', 'phone', 'role']),
            'token' => $user->createToken('gozorx-client')->plainTextToken,
            'supplier' => [
                'id' => $supplier->id,
                'business_name' => $supplier->business_name,
                'status' => $supplier->status,
                'submitted_at' => $supplier->submitted_at,
                'documents' => $supplier->documents()->get([
                    'id', 'document_type', 'status', 'submitted_at',
                ]),
            ],
        ], 201);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:200'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['required', 'string', 'max:2000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            'commercial_register' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'tax_card' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'personal_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'national_id' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $user = $request->user();

        abort_unless(
            $user->role === 'supplier',
            403,
            'لا يمكن تحويل حساب المشتري إلى حساب مورد. يرجى تسجيل الخروج وإنشاء حساب مورد مستقل.'
        );

        if ($user->supplierProfile()->exists()) {
            return response()->json([
                'message' => 'يوجد طلب مورد مرتبط بهذا الحساب بالفعل.',
            ], 409);
        }

        $storedPaths = [];

        try {
            $supplier = DB::transaction(function () use ($user, $data, $request, &$storedPaths) {
                $supplier = $user->supplierProfile()->create([
                    'business_name' => $data['business_name'],
                    'business_type' => $data['business_type'] ?? null,
                    'description' => $data['description'] ?? null,
                    'address' => $data['address'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'status' => 'pending_approval',
                    'submitted_at' => now(),
                ]);

                foreach (SupplierDocument::TYPES as $type) {
                    $file = $request->file($type);

                    $path = $file->store(
                        "supplier-documents/{$supplier->id}",
                        'local'
                    );

                    if (!$path) {
                        throw new RuntimeException("Failed to store supplier document: {$type}");
                    }

                    $storedPaths[] = $path;

                    $supplier->documents()->create([
                        'document_type' => $type,
                        'file_path' => $path,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'status' => 'pending_review',
                        'submitted_at' => now(),
                    ]);
                }

                return $supplier;
            });
        } catch (Throwable $exception) {
            if (!empty($storedPaths)) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'تم استلام طلب المورد والمستندات الأربعة، وجميعها قيد المراجعة.',
            'supplier' => [
                'id' => $supplier->id,
                'business_name' => $supplier->business_name,
                'address' => $supplier->address,
                'latitude' => $supplier->latitude,
                'longitude' => $supplier->longitude,
                'status' => $supplier->status,
                'submitted_at' => $supplier->submitted_at,
                'documents' => $supplier->documents()->get([
                    'id',
                    'document_type',
                    'status',
                    'submitted_at',
                ]),
            ],
        ], 201);
    }

    public function application(Request $request)
    {
        $supplier = $request->user()->supplierProfile()
            ->with('documents')
            ->first();

        if (!$supplier) {
            return response()->json([
                'message' => 'لا يوجد طلب مورد مرتبط بهذا الحساب.',
            ], 404);
        }

        return response()->json([
            'supplier' => [
                'id' => $supplier->id,
                'business_name' => $supplier->business_name,
                'status' => $supplier->status,
                'address' => $supplier->address,
                'latitude' => $supplier->latitude,
                'longitude' => $supplier->longitude,
                'submitted_at' => $supplier->submitted_at,
                'documents' => $supplier->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'type' => $document->document_type,
                    'status' => $document->status,
                    'review_note' => $document->review_note,
                    'submitted_at' => $document->submitted_at,
                    'reviewed_at' => $document->reviewed_at,
                ]),
            ],
        ]);
    }

    public function show(SupplierProfile $supplier)
    {
        $supplier->load([
            'user:id,full_name,email,phone',
            'documents',
        ]);

        return response()->json([
            'supplier' => [
                'id' => $supplier->id,
                'business_name' => $supplier->business_name,
                'business_type' => $supplier->business_type,
                'description' => $supplier->description,
                'address' => $supplier->address,
                'latitude' => $supplier->latitude,
                'longitude' => $supplier->longitude,
                'status' => $supplier->status,
                'submitted_at' => $supplier->submitted_at,
                'reviewed_at' => $supplier->reviewed_at,
                'review_note' => $supplier->review_note,
                'user' => $supplier->user ? [
                    'full_name' => $supplier->user->full_name,
                    'email' => $supplier->user->email,
                    'phone' => $supplier->user->phone,
                ] : null,
                'documents' => $supplier->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'type' => $document->document_type,
                    'status' => $document->status,
                    'original_filename' => $document->original_filename,
                    'mime_type' => $document->mime_type,
                    'file_size' => $document->file_size,
                    'review_note' => $document->review_note,
                    'submitted_at' => $document->submitted_at,
                    'reviewed_at' => $document->reviewed_at,
                ]),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending_approval');
        $allowed = ['pending_approval', 'approved', 'rejected', 'suspended'];

        abort_unless(in_array($status, $allowed, true), 422, 'Invalid supplier status.');

        return SupplierProfile::with('user:id,full_name,email,phone')
            ->where('status', $status)
            ->latest()
            ->paginate(25);
    }

    public function decide(Request $request, SupplierProfile $supplier)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['decision'] === 'approved') {
            $approvedCount = $supplier->documents()
                ->whereIn('document_type', SupplierDocument::TYPES)
                ->where('status', 'approved')
                ->count();

            abort_unless(
                $approvedCount === count(SupplierDocument::TYPES),
                422,
                'لا يمكن اعتماد المورد قبل اعتماد المستندات الأربعة.'
            );
        }

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
                'details' => json_encode([
                    'review_note' => $data['review_note'] ?? null,
                ]),
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'تم تحديث قرار مراجعة المورد.',
            'supplier' => $supplier->fresh(),
        ]);
    }
}
