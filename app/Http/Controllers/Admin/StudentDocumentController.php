<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\StudentDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Serves KYC files from the private disk. The model's institute scope returns 404
 * for documents of other institutes; the route requires students.view.
 */
class StudentDocumentController extends Controller
{
    public function show(StudentDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
