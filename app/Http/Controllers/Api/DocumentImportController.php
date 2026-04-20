<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentHeader;
use App\Services\DocumentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentImportController extends Controller
{
    public function __construct(
        private DocumentImportService $importService,
    ) {
    }

    /**
     * Import document lines from XLS file.
     * POST /documents/{document}/import-lines
     */
    public function importLines(Request $request, DocumentHeader $document): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $file = $request->file('file');
        $filePath = $file->store('temp');

        try {
            $result = $this->importService->importLines(
                storage_path("app/{$filePath}"),
                $document->id
            );

            return response()->json([
                'success' => true,
                'message' => sprintf('Imported %d lines, %d errors', $result['created_count'], $result['error_count']),
                'data' => $result,
            ]);
        } finally {
            // Clean up temp file
            if (file_exists(storage_path("app/{$filePath}"))) {
                unlink(storage_path("app/{$filePath}"));
            }
        }
    }
}
