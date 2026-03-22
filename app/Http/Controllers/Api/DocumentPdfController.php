<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentHeader;
use App\Services\DocumentPdfService;
use Illuminate\Http\Response;

class DocumentPdfController extends Controller
{
    public function __construct(private DocumentPdfService $pdfService)
    {
    }

    /**
     * GET /api/documents/{documentHeader}/pdf/download
     * Force-download the PDF.
     */
    public function download(DocumentHeader $documentHeader): Response
    {
        $pdf      = $this->pdfService->generate($documentHeader);
        $filename = $this->pdfService->filename($documentHeader);

        return $pdf->download($filename);
    }

    /**
     * GET /api/documents/{documentHeader}/pdf/stream
     * Stream the PDF inline (browser preview).
     */
    public function stream(DocumentHeader $documentHeader): Response
    {
        $pdf      = $this->pdfService->generate($documentHeader);
        $filename = $this->pdfService->filename($documentHeader);

        return $pdf->stream($filename);
    }
}
