<?php

namespace App\Observers;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class ProductImageObserver
{
    public function deleted(ProductImage $image): void
    {
        if ($image->url) {
            $this->deleteFile($image->url);
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
            \Log::warning("Failed to delete product image file: {$url}", ['error' => $e->getMessage()]);
        }
    }
}
