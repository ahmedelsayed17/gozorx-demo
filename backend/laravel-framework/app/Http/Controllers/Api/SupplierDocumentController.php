<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class SupplierDocumentController extends Controller
{
    public function reupload(Request $request, string $documentType)
    {
        if (!in_array($documentType, SupplierDocument::TYPES, true)) {
            abort(404, 'نوع المستند غير صحيح.');
        }

        $fileRules = in_array($documentType, ['personal_photo', 'national_id'], true)
            ? ['required', 'image', 'mimes:jpg,jpeg,png', 'max:10240']
            : ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

        $data = $request->validate([
            'document' => $fileRules,
        ]);

        $supplier = $request->user()->supplierProfile;

        abort_unless($supplier, 404, 'لا يوجد طلب مورد مرتبط بهذا الحساب.');

        $document = $supplier->documents()
            ->where('document_type', $documentType)
            ->firstOrFail();

        if ($document->status !== 'rejected') {
            throw ValidationException::withMessages([
                'document' => ['يمكن إعادة رفع المستندات المرفوضة فقط.'],
            ]);
        }

        $oldPath = $document->file_path;

        $newPath = $request->file('document')->store(
            "supplier-documents/{$supplier->id}",
            'local'
        );

        if (!$newPath) {
            throw ValidationException::withMessages([
                'document' => ['تعذر حفظ الملف، حاول مرة أخرى.'],
            ]);
        }

        try {
            $document->update([
                'file_path' => $newPath,
                'original_filename' => $request->file('document')->getClientOriginalName(),
                'mime_type' => $request->file('document')->getMimeType(),
                'file_size' => $request->file('document')->getSize(),
                'status' => 'pending_review',
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'submitted_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'message' => 'تم إعادة رفع المستند بنجاح، وهو الآن قيد المراجعة.',
            'document' => [
                'type' => $document->document_type,
                'status' => $document->status,
                'submitted_at' => $document->submitted_at,
            ],
        ]);
    }
    public function showFile(SupplierDocument $document)
    {
        $expectedPrefix = "supplier-documents/{$document->supplier_profile_id}/";

        abort_unless(
            str_starts_with($document->file_path, $expectedPrefix),
            404
        );

        $disk = Storage::disk('local');

        abort_unless($disk->exists($document->file_path), 404);

        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ];

        abort_unless(
            in_array($document->mime_type, $allowedMimeTypes, true),
            404
        );

        return response()->file($disk->path($document->file_path), [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

public function decide(Request $request, SupplierDocument $document)
{
    $data = $request->validate([
        'decision' => ['required', 'in:approved,rejected'],
        'review_note' => [
            'required_if:decision,rejected',
            'nullable',
            'string',
            'max:2000',
        ],
    ]);

    DB::transaction(function () use ($request, $document, $data) {
        $document = SupplierDocument::whereKey($document->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($document->status !== 'pending_review') {
            throw ValidationException::withMessages([
                'document' => ['تمت مراجعة هذا المستند بالفعل.'],
            ]);
        }

        $document->update([
            'status' => $data['decision'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $supplier = $document->supplierProfile()
            ->lockForUpdate()
            ->firstOrFail();

        DB::table('audit_logs')->insert([
            'actor_user_id' => $request->user()->id,
            'action' => 'supplier_document.' . $data['decision'],
            'entity_type' => 'supplier_document',
            'entity_id' => $document->id,
            'details' => json_encode([
                'supplier_profile_id' => $supplier->id,
                'document_type' => $document->document_type,
                'review_note' => $data['review_note'] ?? null,
            ]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        $allDocumentsApproved = $supplier->documents()
            ->whereIn('document_type', SupplierDocument::TYPES)
            ->where('status', 'approved')
            ->count() === count(SupplierDocument::TYPES);

        if ($allDocumentsApproved) {
            $supplier->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'review_note' => null,
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id,
                'action' => 'supplier.approved',
                'entity_type' => 'supplier_profile',
                'entity_id' => $supplier->id,
                'details' => json_encode([
                    'reason' => 'all_required_documents_approved',
                ]),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        }
    });

    return response()->json([
        'message' => 'تم تحديث مراجعة المستند.',
        'document' => [
            'id' => $document->id,
            'type' => $document->document_type,
            'status' => $data['decision'],
            'review_note' => $data['review_note'] ?? null,
        ],
    ]);
}
    }