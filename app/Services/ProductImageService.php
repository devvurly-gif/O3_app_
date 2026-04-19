<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductImageService
{
    public function __construct(private ProductImageRepositoryInterface $images)
    {
    }

    public function upload(Product $product, UploadedFile $file, ?string $title, ?string $altContent, bool $isPrimary): ProductImage
    {
        $path = $file->store('products', 'public');

        if ($isPrimary) {
            $this->images->clearPrimary($product);
        }

        $isFirstImage = $this->images->countForProduct($product) === 0;

        /** @var ProductImage $image */
        $image = $this->images->createForProduct($product, [
            'url'        => '/storage/' . $path,
            'title'      => $title ?? pathinfo($path, PATHINFO_FILENAME),
            'altContent' => $altContent,
            'isPrimary'  => $isPrimary || $isFirstImage,
        ]);

        return $image;
    }

    public function setPrimary(Product $product, ProductImage $image): ProductImage
    {
        /** @var ProductImage $image */
        $image = $this->images->setPrimary($product, $image);
        return $image;
    }

    public function uploadMultiple(Product $product, array $files, ?string $title, ?string $altContent, bool $isPrimary): array
    {
        $uploadedImages = [];

        foreach ($files as $index => $file) {
            // Only first image is marked as primary if requested
            $isFirst = $index === 0;
            $image = $this->upload(
                $product,
                $file,
                $title ? "{$title} {$index + 1}" : null,
                $altContent,
                ($isPrimary && $isFirst) || (!$this->images->countForProduct($product) && $index === 0)
            );
            $uploadedImages[] = $image;
        }

        return $uploadedImages;
    }

    public function delete(ProductImage $image): void
    {
        $this->images->delete($image);
    }
}
