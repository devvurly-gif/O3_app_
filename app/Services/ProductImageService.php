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

    public function delete(ProductImage $image): void
    {
        $this->images->delete($image);
    }
}
