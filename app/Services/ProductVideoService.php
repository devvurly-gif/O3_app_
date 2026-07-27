<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVideo;
use App\Repositories\Contracts\ProductVideoRepositoryInterface;

class ProductVideoService
{
    public function __construct(private ProductVideoRepositoryInterface $videos)
    {
    }

    public function add(Product $product, string $url, ?string $title = null): ProductVideo
    {
        /** @var ProductVideo $video */
        $video = $this->videos->createForProduct($product, [
            'url'   => $url,
            'title' => $title,
        ]);

        return $video;
    }

    public function delete(ProductVideo $video): void
    {
        $this->videos->delete($video);
    }
}
