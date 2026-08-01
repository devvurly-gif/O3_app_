<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Renders the product-label designer template into raw TSPL/TSC commands and
 * ships them to the printer.
 *
 * Why bypass the browser at all: printing through `window.print()` means the
 * page is rasterised at the browser's own resolution and then rescaled by the
 * driver to the printer's 203 dpi grid. Barcode bars land on fractional dots,
 * come out uneven, and scanners choke on them. TSPL is the printer's native
 * language — coordinates are dots, the barcode is drawn by the firmware, and
 * what we compute here is exactly what the head burns.
 *
 * The template is the same JSON blob the designer stores in the `labels`
 * settings domain, so the on-screen layout and the printed label share one
 * source of truth. Millimetres in, dots out.
 */
class TsplLabelService
{
    /** 203 dpi head → 7.992 dots/mm. Overridable for 300 dpi models. */
    public const DEFAULT_DPI = 203;

    private const PT_TO_MM = 0.352778;

    /**
     * Bitmap fonts every TSPL firmware ships with: id => [width, height] in
     * dots. These are fixed dot matrices, not scalable outlines — a 300 dpi
     * printer draws them at the same dot count, hence physically smaller.
     * pickFont() compensates by sizing against the configured dpi.
     */
    private const FONTS = [
        '1' => [8, 12],
        '2' => [12, 20],
        '3' => [16, 24],
        '4' => [24, 32],
        '5' => [32, 48],
    ];

    /** Dots the firmware adds below a barcode for the human-readable line. */
    private const HRI_HEIGHT = 22;

    /**
     * EAN13 is a fixed 95-module symbol plus the mandatory quiet zones
     * (11 modules left, 7 right). Used to derive the module width from the
     * width in millimetres the designer asked for.
     */
    private const EAN13_MODULES = 113;

    /**
     * Build the full TSPL job for a batch of labels.
     *
     * @param  array<string,mixed>            $template  designer blob: {label, layout}
     * @param  Collection<int,array{product:Product,qty:int}>  $items
     * @param  array<string,mixed>            $printer   dpi/darkness/speed/gap/direction
     */
    public function render(array $template, Collection $items, array $printer): string
    {
        $dpi     = (int) ($printer['dpi'] ?? self::DEFAULT_DPI);
        $label   = $template['label'] ?? [];
        $layout  = $template['layout'] ?? [];
        $widthMm  = (float) ($label['width'] ?? 50);
        $heightMm = (float) ($label['height'] ?? 30);

        $out = [
            sprintf('SIZE %s mm,%s mm', $this->num($widthMm), $this->num($heightMm)),
            sprintf('GAP %s mm,0 mm', $this->num((float) ($printer['gap'] ?? 2))),
            // DIRECTION flips the image end-for-end. Which value reads the right
            // way up depends on how the roll is threaded, so it stays a setting:
            // if labels come out upside down, flip this rather than the layout.
            sprintf('DIRECTION %d,0', (int) ($printer['direction'] ?? 1)),
            'REFERENCE 0,0',
            sprintf('SPEED %s', $this->num((float) ($printer['speed'] ?? 4))),
            sprintf('DENSITY %d', (int) ($printer['darkness'] ?? 10)),
            // Windows-1252 so French product names keep their accents; the
            // firmware's default codepage would drop them to blanks.
            'CODEPAGE 1252',
        ];

        $body = [];
        foreach ($items as $item) {
            $product = $item['product'];
            $qty     = max(1, $item['qty']);

            $body[] = 'CLS';
            if (!empty($label['border'])) {
                $body[] = sprintf(
                    'BOX 0,0,%d,%d,2',
                    $this->dots($widthMm, $dpi) - 1,
                    $this->dots($heightMm, $dpi) - 1
                );
            }
            foreach ($this->fields($layout, $product, $dpi) as $cmd) {
                $body[] = $cmd;
            }
            // One PRINT with a copy count, not N identical jobs: the firmware
            // reuses the rendered bitmap, so 50 copies feed at full speed.
            $body[] = sprintf('PRINT %d,1', $qty);
        }

        // TSPL is line-oriented and the firmware expects CRLF terminators.
        return implode("\r\n", [...$out, ...$body]) . "\r\n";
    }

    /**
     * Turn every enabled field of the layout into its TSPL command(s).
     *
     * @param  array<string,mixed> $layout
     * @return list<string>
     */
    private function fields(array $layout, Product $product, int $dpi): array
    {
        $cmds = [];

        foreach ($layout as $key => $f) {
            if (!is_array($f) || empty($f['enabled'])) {
                continue;
            }

            $x = $this->dots((float) ($f['x'] ?? 0), $dpi);
            $y = $this->dots((float) ($f['y'] ?? 0), $dpi);

            if ($key === 'barcode') {
                $cmd = $this->barcode($product, $f, $x, $y, $dpi);
                if ($cmd !== null) {
                    $cmds[] = $cmd;
                }
                continue;
            }

            $text = $this->fieldText($key, $product);
            if ($text === '') {
                continue;
            }

            $font  = $this->pickFont((float) ($f['size'] ?? 8), $dpi);
            $bold  = !empty($f['bold']);
            $boxed = !empty($f['boxed']);

            $textW = strlen($text) * $font['w'] * $font['mul'];
            $textH = $font['h'] * $font['mul'];

            if ($boxed) {
                // Mirrors the designer's 0.3mm × 1mm padding and 1.5pt border.
                $padX = $this->dots(1.0, $dpi);
                $padY = $this->dots(0.3, $dpi);
                $cmds[] = sprintf(
                    'BOX %d,%d,%d,%d,%d',
                    max(0, $x - $padX),
                    max(0, $y - $padY),
                    $x + $textW + $padX,
                    $y + $textH + $padY,
                    max(2, $this->dots(0.5, $dpi))
                );
            }

            $cmds[] = $this->text($x, $y, $font, $text);
            if ($bold) {
                // Bitmap fonts have no bold cut. Overprinting one dot to the
                // right thickens every stem — the same trick the firmware's
                // own "bold" does on models that expose one.
                $cmds[] = $this->text($x + 1, $y, $font, $text);
            }
        }

        return $cmds;
    }

    /** @param array{id:string,mul:int,w:int,h:int} $font */
    private function text(int $x, int $y, array $font, string $value): string
    {
        return sprintf(
            'TEXT %d,%d,"%s",0,%d,%d,"%s"',
            $x,
            $y,
            $font['id'],
            $font['mul'],
            $font['mul'],
            $this->escape($value)
        );
    }

    /**
     * @param  array<string,mixed> $f  field layout (size = width in mm, height = mm)
     */
    private function barcode(Product $product, array $f, int $x, int $y, int $dpi): ?string
    {
        $value = trim((string) ($product->p_ean13 ?? ''));
        if ($value === '') {
            return null;
        }

        $widthDots  = $this->dots((float) ($f['size'] ?? 34), $dpi);
        $heightDots = $this->dots((float) ($f['height'] ?? 11), $dpi);
        // The designer's height covers the whole symbol including the digits
        // underneath; TSPL's height parameter is the bars alone.
        $barHeight  = max(16, $heightDots - self::HRI_HEIGHT);

        if ($this->isEan13($value)) {
            // Feeding 12 digits lets the firmware compute the check digit
            // itself, which some builds insist on even when 13 are supplied.
            $data   = substr($value, 0, 12);
            $narrow = $this->clamp((int) round($widthDots / self::EAN13_MODULES), 1, 10);

            return sprintf(
                'BARCODE %d,%d,"EAN13",%d,1,0,%d,%d,"%s"',
                $x,
                $y,
                $barHeight,
                $narrow,
                $narrow * 2,
                $data
            );
        }

        // Not a valid EAN13 — same fallback as the on-screen preview so the
        // label still carries a scannable code, just not as an EAN13.
        // Code128: start + data + checksum + stop = 11n + 35 modules, plus
        // the 10-module quiet zone on each side.
        $modules = 11 * strlen($value) + 55;
        $narrow  = $this->clamp((int) round($widthDots / $modules), 1, 10);

        return sprintf(
            'BARCODE %d,%d,"128",%d,1,0,%d,%d,"%s"',
            $x,
            $y,
            $barHeight,
            $narrow,
            $narrow * 2,
            $this->escape($value)
        );
    }

    private function fieldText(string $key, Product $product): string
    {
        return match ($key) {
            'title'         => (string) $product->p_title,
            'sku'           => (string) ($product->p_sku ?: $product->p_code),
            'imei'          => (string) ($product->p_imei ?? ''),
            'salePrice'     => $this->price($product->p_salePrice),
            'purchasePrice' => $this->price($product->p_purchasePrice),
            'category'      => (string) ($product->category->ctg_title ?? ''),
            'brand'         => (string) ($product->brand->br_title ?? ''),
            default         => '',
        };
    }

    /** Matches the preview's `1 234,56 dhs` formatting. */
    private function price(mixed $value): string
    {
        return number_format((float) $value, 2, ',', ' ') . ' dhs';
    }

    /**
     * Pick the bitmap font + multiplier whose height lands closest to the
     * requested point size.
     *
     * @return array{id:string,mul:int,w:int,h:int}
     */
    private function pickFont(float $pt, int $dpi): array
    {
        $target = $pt * self::PT_TO_MM * ($dpi / 25.4);

        $best = null;
        foreach (self::FONTS as $id => [$w, $h]) {
            for ($mul = 1; $mul <= 8; $mul++) {
                // Slight bias against overshooting: these bitmap fonts are
                // fixed-pitch and noticeably wider than the proportional web
                // font of the preview, so a size that rounds up overflows the
                // label far more often than one that rounds down.
                $err = abs($h * $mul - $target) + ($h * $mul > $target ? 0.5 : 0.0);
                if ($best === null || $err < $best['err']) {
                    $best = ['id' => (string) $id, 'mul' => $mul, 'w' => $w, 'h' => $h, 'err' => $err];
                }
            }
        }

        /** @var array{id:string,mul:int,w:int,h:int,err:float} $best */
        return ['id' => $best['id'], 'mul' => $best['mul'], 'w' => $best['w'], 'h' => $best['h']];
    }

    /**
     * TSPL string literals are double-quoted with backslash escapes, and the
     * firmware reads Windows-1252 — so accents must be transcoded, not sent
     * as raw UTF-8 (which would print as two garbage glyphs each).
     */
    private function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';

        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }

        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function isEan13(string $value): bool
    {
        if (!preg_match('/^\d{13}$/', $value)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $value[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === (int) $value[12];
    }

    private function dots(float $mm, int $dpi): int
    {
        return (int) round($mm * $dpi / 25.4);
    }

    /** TSPL wants a plain decimal; PHP's locale must not slip a comma in. */
    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return min(max($value, $min), $max);
    }

    /**
     * Open a raw socket to the printer's JetDirect port and push the job.
     *
     * Only usable when the app server can actually reach the printer — a
     * shop LAN printer is invisible to a VPS-hosted install, which is what
     * the browser-side agent transport is for.
     */
    public function sendRaw(string $payload, string $host, int $port, int $timeout = 5): void
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            throw new RuntimeException(
                sprintf('Connexion à l\'imprimante %s:%d impossible (%s).', $host, $port, $errstr ?: "erreur {$errno}")
            );
        }

        try {
            stream_set_timeout($socket, $timeout);
            $written = @fwrite($socket, $payload);

            if ($written === false || $written < strlen($payload)) {
                throw new RuntimeException('Envoi du travail d\'impression interrompu.');
            }
        } finally {
            fclose($socket);
        }
    }
}
