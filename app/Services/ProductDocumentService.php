<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductDocument;
use App\Repositories\Contracts\ProductDocumentRepositoryInterface;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class ProductDocumentService
{
    public function __construct(private ProductDocumentRepositoryInterface $documents)
    {
    }

    public function upload(Product $product, UploadedFile $file, ?string $title = null): ProductDocument
    {
        $path = $file->store('products/documents', 'public');

        if ($path === false) {
            throw new RuntimeException('Failed to store product document on the public disk.');
        }

        /** @var ProductDocument $document */
        $document = $this->documents->createForProduct($product, [
            'url'       => '/storage/' . $path,
            'title'     => $title ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size'      => $file->getSize(),
        ]);

        return $document;
    }

    public function delete(ProductDocument $document): void
    {
        $this->documents->delete($document);
    }
}
