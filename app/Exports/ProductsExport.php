<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class ProductsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    use Exportable;

    /** Rendered size of the embedded thumbnail, in pixels. */
    private const THUMB_PX = 64;

    /** Row height in points (1pt ≈ 1.333px) — leaves a small margin around the thumbnail. */
    private const ROW_HEIGHT_PT = 54;

    /** Width of the image column, in Excel character units. */
    private const IMAGE_COL_WIDTH = 11;

    /** Files bigger than this are never opened/copied (defensive, in bytes). */
    private const MAX_SOURCE_BYTES = 20 * 1024 * 1024;

    /** Per-image budget when the row points at an externally hosted picture. */
    private const DOWNLOAD_TIMEOUT = 8;

    /** After this many failures in a row, a host is considered down for this export. */
    private const HOST_FAILURE_LIMIT = 3;

    /** Directory (relative to the local disk) holding the generated thumbnails. */
    private const TMP_DIR = 'tmp/product-image-exports';

    private array $filters;

    private bool $withImages;

    /** Temp directory of this export run, created lazily. */
    private ?string $tmpPath = null;

    /** url => readable path (or null), so a repeated/broken url costs one lookup. */
    private array $sources = [];

    /** host => consecutive download failures, used to stop hammering a dead host. */
    private array $hostFailures = [];

    public function __construct(array $filters = [], bool $withImages = false)
    {
        $this->filters    = $filters;
        $this->withImages = $withImages;
    }

    public function query()
    {
        $q = Product::query()->with(['category', 'brand']);

        if ($this->withImages) {
            $q->with(['images' => fn ($q2) => $q2->orderByDesc('isPrimary')->orderBy('id')]);
        }

        if (!empty($this->filters['search'])) {
            $q->where(function ($q2) {
                $q2->where('p_title', 'like', '%' . $this->filters['search'] . '%')
                   ->orWhere('p_sku', 'like', '%' . $this->filters['search'] . '%');
            });
        }

        if (!empty($this->filters['category_id'])) {
            $q->where('category_id', $this->filters['category_id']);
        }

        // The products list sends `status`; keep `p_status` working for older callers.
        $status = $this->filters['p_status'] ?? $this->filters['status'] ?? null;
        if ($status !== null && $status !== '') {
            $q->where('p_status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        // p_title alone is not unique: the id keeps the order stable between the
        // row pass and the image pass, so every drawing lands on the right row.
        return $q->orderBy('p_title')->orderBy('id');
    }

    public function headings(): array
    {
        $headings = [
            'ID', 'Code', 'Titre', 'SKU', 'EAN13',
            'Catégorie', 'Marque',
            'Prix Achat', 'Prix Vente', 'Coût', 'TVA %',
            'Unité', 'Statut',
        ];

        if ($this->withImages) {
            array_unshift($headings, 'Image');
            $headings[] = 'URL Image';
        }

        return $headings;
    }

    public function map($product): array
    {
        $row = [
            $product->id,
            $product->p_code,
            $product->p_title,
            $product->p_sku,
            $product->p_ean13,
            $product->category?->ctg_title,
            $product->brand?->br_title,
            $product->p_purchasePrice,
            $product->p_salePrice,
            $product->p_cost,
            $product->p_taxRate,
            $product->p_unit,
            $product->p_status ? 'Actif' : 'Inactif',
        ];

        if ($this->withImages) {
            // Column A is left empty: the picture is anchored onto it afterwards.
            array_unshift($row, '');
            $row[] = $this->absoluteUrl($this->primaryImage($product)?->url);
        }

        return $row;
    }

    public function registerEvents(): array
    {
        if (!$this->withImages) {
            return [];
        }

        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // ShouldAutoSize already ran; opt column A out so the thumbnails fit.
                $sheet->getColumnDimension('A')->setAutoSize(false);
                $sheet->getColumnDimension('A')->setWidth(self::IMAGE_COL_WIDTH);
                $sheet->freezePane('B2');

                $this->pruneStaleThumbnails();
                $this->drawImages($sheet);
            },
        ];
    }

    /** Anchor one thumbnail per product row, in the exact order the rows were written. */
    private function drawImages(Worksheet $sheet): void
    {
        $row = 1; // row 1 holds the headings

        $this->query()->chunk(200, function ($products) use ($sheet, &$row) {
            foreach ($products as $product) {
                $row++;
                $sheet->getRowDimension($row)->setRowHeight(self::ROW_HEIGHT_PT);

                $source = $this->resolveSource($this->primaryImage($product)?->url);
                if ($source === null) {
                    continue;
                }

                $file = $this->thumbnail($source);
                if ($file === null) {
                    continue;
                }

                $drawing = new Drawing();
                $drawing->setName((string) $product->p_sku);
                $drawing->setDescription((string) $product->p_title);
                $drawing->setPath($file);
                // Constrain the longest side so landscape shots stay inside column A.
                if ($drawing->getWidth() > $drawing->getHeight()) {
                    $drawing->setWidth(self::THUMB_PX);
                } else {
                    $drawing->setHeight(self::THUMB_PX);
                }
                $drawing->setOffsetX(4);
                $drawing->setOffsetY(4);
                $drawing->setCoordinates('A' . $row);
                $drawing->setWorksheet($sheet);
            }
        });
    }

    private function primaryImage($product): ?ProductImage
    {
        $images = $product->relationLoaded('images')
            ? $product->images
            : $product->images()->orderByDesc('isPrimary')->orderBy('id')->get();

        return $images->firstWhere('isPrimary', true) ?? $images->first();
    }

    /**
     * product_images.url comes in three shapes depending on who wrote the row:
     * "/storage/products/x.jpg" (uploads), "products/x.jpg" (Jadever importer) and
     * a full "https://…" (importer fallbacks when the download failed). Return a
     * readable path on disk for all three, or null when nothing can be reached.
     */
    private function resolveSource(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (array_key_exists($url, $this->sources)) {
            return $this->sources[$url];
        }

        $isRemote = (bool) preg_match('#^https?://#i', $url);

        // An absolute url may still point at our own /storage: try the disk first,
        // it is both faster and works when the host is unreachable from the server.
        $path = $this->localPath($url) ?? ($isRemote ? $this->download($url) : null);

        return $this->sources[$url] = $path;
    }

    /** Look the url up on the public disk (tenant-suffixed) or under public/. */
    private function localPath(string $url): ?string
    {
        $relative = ltrim(parse_url($url, PHP_URL_PATH) ?: $url, '/');
        $relative = preg_replace('#^storage/#', '', $relative);

        $candidates = [
            Storage::disk('public')->path($relative),
            public_path(ltrim(parse_url($url, PHP_URL_PATH) ?: $url, '/')),
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && filesize($path) > 0 && filesize($path) <= self::MAX_SOURCE_BYTES) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Fetch an externally hosted image into the temp folder. Rows pointing at a
     * remote url are the importer fallback path, so a slow or dead host must never
     * take the whole export down: short timeout, size cap, failures return null and
     * the row keeps only its URL column.
     */
    private function download(string $url): ?string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        // A catalog imported from one source can hold hundreds of rows on the same
        // host; once it has proved unreachable, stop paying the timeout for each.
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (($this->hostFailures[$host] ?? 0) >= self::HOST_FAILURE_LIMIT) {
            return null;
        }

        $ext    = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $target = $this->tmpDir() . '/remote_' . md5($url) . ($ext !== '' ? '.' . $ext : '');

        if (is_file($target)) {
            return $target;
        }

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(self::DOWNLOAD_TIMEOUT)
                ->get($url);

            if (!$response->successful()) {
                $this->hostFailures[$host] = ($this->hostFailures[$host] ?? 0) + 1;

                return null;
            }

            $body = $response->body();

            // Guard against an oversized payload or an HTML error page served with a 200.
            if ($body === '' || strlen($body) > self::MAX_SOURCE_BYTES || @getimagesizefromstring($body) === false) {
                return null;
            }

            file_put_contents($target, $body);
            $this->hostFailures[$host] = 0;
        } catch (Throwable) {
            $this->hostFailures[$host] = ($this->hostFailures[$host] ?? 0) + 1;

            return null;
        }

        return is_file($target) ? $target : null;
    }

    /**
     * Downscale the source image to a small PNG/JPEG in a temp folder. Keeping the
     * originals out of the workbook is what stops a 500-product export from
     * ballooning to hundreds of megabytes. Falls back to the original file when GD
     * cannot read the format, and to null when even that is unusable.
     */
    private function thumbnail(string $source): ?string
    {
        $fallback = in_array(strtolower(pathinfo($source, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'], true)
            ? $source
            : null;

        if (!function_exists('imagecreatetruecolor')) {
            return $fallback;
        }

        $target = $this->tmpDir() . '/' . md5($source . filemtime($source)) . '.png';
        if (is_file($target)) {
            return $target;
        }

        try {
            $image = @imagecreatefromstring(file_get_contents($source));
            if ($image === false) {
                return $fallback;
            }

            $width  = imagesx($image);
            $height = imagesy($image);
            $scale  = min(1, self::THUMB_PX * 2 / max($width, $height)); // 2x for crisp rendering
            $w      = max(1, (int) round($width * $scale));
            $h      = max(1, (int) round($height * $scale));

            $thumb = imagecreatetruecolor($w, $h);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $w, $h, $width, $height);
            imagepng($thumb, $target, 8);

            imagedestroy($thumb);
            imagedestroy($image);
        } catch (Throwable) {
            return $fallback;
        }

        return is_file($target) ? $target : $fallback;
    }

    private function tmpDir(): string
    {
        if ($this->tmpPath === null) {
            $this->tmpPath = Storage::disk('local')->path(self::TMP_DIR . '/' . now()->format('YmdHis') . '_' . getmypid());
            if (!is_dir($this->tmpPath)) {
                mkdir($this->tmpPath, 0775, true);
            }
        }

        return $this->tmpPath;
    }

    /**
     * The thumbnails have to outlive this request (PhpSpreadsheet reads them while
     * saving), so they are swept on the next export instead of right away.
     */
    private function pruneStaleThumbnails(): void
    {
        $root = Storage::disk('local')->path(self::TMP_DIR);
        if (!is_dir($root)) {
            return;
        }

        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if ($dir === $this->tmpPath || filemtime($dir) > now()->subHour()->getTimestamp()) {
                continue;
            }

            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function absoluteUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        // Rows written as a bare "products/x.jpg" are served from /storage.
        if (!str_starts_with($url, '/')) {
            $url = '/storage/' . $url;
        }

        return url($url);
    }
}
