<?php

namespace App\Observers;

use App\Models\ProductDocument;
use Illuminate\Support\Facades\Storage;

class ProductDocumentObserver
{
    public function deleted(ProductDocument $document): void
    {
        if ($document->url) {
            $this->deleteFile($document->url);
        }
    }

    private function deleteFile(string $url): void
    {
        try {
            // Remove leading slash and /storage/ prefix to get the actual path
            $path = str_replace('/storage/', '', $url);

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the deletion
            \Log::warning("Failed to delete product document file: {$url}", ['error' => $e->getMessage()]);
        }
    }
}
