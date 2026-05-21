<?php

namespace App\Services;

class InvoiceParserService
{
    /**
     * Parse extracted text from a Moroccan purchase invoice.
     * Returns structured data with confidence score.
     */
    public function parse(string $text): array
    {
        $supplier  = $this->parseSupplier($text);
        $metadata  = $this->parseMetadata($text);
        $priceType = $this->detectPriceType($text);
        $lines     = $this->parseLineItems($text, $priceType);
        $totals    = $this->parseTotals($text);

        $confidence = $this->calculateConfidence($supplier, $metadata, $lines, $totals);

        return [
            'supplier'       => $supplier,
            'invoice_number' => $metadata['invoice_number'],
            'invoice_date'   => $metadata['invoice_date'],
            'due_date'       => $metadata['due_date'],
            'lines'          => $lines,
            'totals'         => $totals,
            'price_type'     => $priceType,
            'raw_text'       => $text,
            'confidence'     => $confidence,
        ];
    }

    // ── Supplier ────────────────────────────────────────────────────

    private function parseSupplier(string $text): array
    {
        return [
            'name'    => $this->extractSupplierName($text),
            'ice'     => $this->extractPattern($text, '/ICE\s*[:\-]?\s*(\d{15})/i'),
            'rc'      => $this->extractPattern($text, '/R\.?C\.?\s*[:\-]?\s*(\d+)/i'),
            'if'      => $this->extractPattern($text, '/I\.?F\.?\s*[:\-]?\s*(\d+)/i'),
            'patente' => $this->extractPattern($text, '/Patente\s*[:\-]?\s*(\d+)/i'),
        ];
    }

    private function extractSupplierName(string $text): ?string
    {
        // Try common patterns for company names in Moroccan invoices
        $patterns = [
            '/(?:Société|Ste|Sté|Ets|SARL|SA|SAS)\s+([A-ZÀ-Ü][A-Za-zÀ-ü\s&\-\.]{2,40})/u',
            '/^([A-ZÀ-Ü][A-ZÀ-Ü\s&\-\.]{3,40})\s*$/mu', // All-caps line (company name)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return trim($m[1] ?? $m[0]);
            }
        }

        return null;
    }

    // ── Metadata ────────────────────────────────────────────────────

    private function parseMetadata(string $text): array
    {
        // Try standard patterns first, then reversed "date label" patterns (e.g. "10/11/25 Fés le :")
        $invoiceDate = $this->extractDate($text, '/(?:Date|Le|Du|Dat[ée]e?\s*(?:de\s*)?(?:facture|fact\.?))\s*[:\-]?\s*(\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4})/i');
        if (!$invoiceDate) {
            // Reversed: date comes before label — "10/11/25 ... le :"
            $invoiceDate = $this->extractDate($text, '/(\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4})\s+\S*\s*le\s*[:\-]?/i');
        }
        if (!$invoiceDate) {
            // Standalone date near "Facture" keyword
            $invoiceDate = $this->extractDate($text, '/Facture.*?(\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4})/is');
        }
        if (!$invoiceDate) {
            // Any dd/mm/yy or dd/mm/yyyy in the document (fallback)
            $invoiceDate = $this->extractDate($text, '/(\d{2}[\/.]\d{2}[\/.]\d{2,4})/');
        }

        return [
            'invoice_number' => $this->extractInvoiceNumber($text),
            'invoice_date'   => $invoiceDate,
            'due_date'       => $this->extractDate($text, '/(?:Ech[éeè]ance|Date\s*limite|Date\s*d\'?[éeè]ch[éeè]ance)\s*[:\-]?\s*(\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4})/i'),
        ];
    }

    private function extractInvoiceNumber(string $text): ?string
    {
        $patterns = [
            '/(?:Facture|Fact\.?|Facture\s*N[°o]?)\s*[:\-]?\s*#?\s*([A-Z0-9\-\/]+\d+)/i',
            '/N[°o]\s*[:\-]?\s*([A-Z0-9\-\/]+\d+)/i',
            '/(?:Réf|Ref|Référence)\s*[:\-]?\s*([A-Z0-9\-\/]+\d+)/i',
            // "Numéro : 25-30" or "Numéro :\n25-30"
            '/Num[ée]ro\s*[:\-]\s*\n?\s*([A-Z0-9][\w\-\/\.]*)/im',
            // Reversed: "25-30Numéro :" (value before label)
            '/(\d[\w\-\/\.]+)\s*Num[ée]ro\s*[:\-]/im',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $value = trim($m[1]);
                // Skip ICE-like numbers (pure digits >= 10 chars) — pdfparser
                // sometimes places the ICE number right after the Numéro label
                if (preg_match('/^\d{10,}$/', $value)) {
                    continue;
                }
                return $value;
            }
        }

        // Fallback: pdfparser can garble text order in multi-column PDFs.
        // Look for a short standalone line with digits+hyphens near "Facture"
        if (preg_match('/Facture\b.*?\n.*?\n\s*(\d{1,5}[\-\/]\d{1,5})\s*$/im', $text, $m)) {
            return trim($m[1]);
        }
        // Garbled digit order: "3025-" from "25-30" — try to find digit+hyphen fragments
        if (preg_match('/Facture\b.*?\n.*?\n\s*(\d{2,6}[\-])\s*$/im', $text, $m)) {
            $garbled = trim($m[1]);
            // Try to un-garble: "3025-" → split and rearrange as "25-30"
            if (preg_match('/^(\d{2})(\d{2})([\-])$/', $garbled, $gm)) {
                return $gm[2] . $gm[3] . $gm[1];
            }
            return $garbled;
        }

        return null;
    }

    private function extractDate(string $text, string $pattern): ?string
    {
        if (!preg_match($pattern, $text, $m)) {
            return null;
        }

        $raw = trim($m[1]);
        // Parse dd/mm/yyyy or dd.mm.yyyy
        $parts = preg_split('/[\/.\\-]/', $raw);
        if (count($parts) !== 3) return null;

        [$day, $month, $year] = $parts;
        if (strlen($year) === 2) {
            $year = (int)$year > 50 ? "19$year" : "20$year";
        }

        $day   = str_pad($day, 2, '0', STR_PAD_LEFT);
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);

        if (!checkdate((int)$month, (int)$day, (int)$year)) return null;

        return "$year-$month-$day";
    }

    // ── Price type detection ───────────────────────────────────────

    private function detectPriceType(string $text): string
    {
        $ttcPatterns = [
            '/P\.?\s*U\.?\s*T\.?T\.?C/i',
            '/Prix\s*(?:Unitaire|Unit\.?)\s*T\.?T\.?C/i',
            '/Montant\s*T\.?T\.?C/i',
        ];
        $htPatterns = [
            '/P\.?\s*U\.?\s*H\.?T/i',
            '/Prix\s*(?:Unitaire|Unit\.?)\s*H\.?T/i',
            '/Montant\s*H\.?T/i',
        ];

        $foundTtc = false;
        $foundHt = false;

        foreach ($ttcPatterns as $p) {
            if (preg_match($p, $text)) { $foundTtc = true; break; }
        }
        foreach ($htPatterns as $p) {
            if (preg_match($p, $text)) { $foundHt = true; break; }
        }

        if ($foundTtc && !$foundHt) return 'ttc';
        if ($foundHt) return 'ht';

        return 'ht';
    }

    // ── Line Items ──────────────────────────────────────────────────

    private function parseLineItems(string $text, string $priceType = 'ht'): array
    {
        $lines = [];

        // Strategy: Find table-like patterns with designation, qty, price, amount
        // Pattern 1: designation followed by numbers (qty, unit_price, total)
        $pattern = '/^[\s]*(.{5,60}?)\s+(\d+[\.,]?\d*)\s+(\d+[\s\.,]*\d*[\.,]\d{2})\s+(\d+[\s\.,]*\d*[\.,]\d{2})\s*$/m';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $designation = trim($m[1]);
                if ($this->isTableHeader($designation)) continue;

                $qty       = $this->parseNumber($m[2]);
                $unitPrice = $this->parseNumber($m[3]);
                $totalLine = $this->parseNumber($m[4]);

                if ($qty > 0 && $unitPrice > 0) {
                    $lines[] = $this->buildLine($designation, $qty, $unitPrice, $totalLine, $priceType);
                }
            }
        }

        // Pattern 2: Simpler — designation, qty, price on fewer columns
        if (empty($lines)) {
            $pattern2 = '/^[\s]*(.{5,80}?)\s+(\d+)\s+(\d+[\s\.,]*\d*[\.,]\d{2})\s*$/m';
            if (preg_match_all($pattern2, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $designation = trim($m[1]);
                    if ($this->isTableHeader($designation)) continue;

                    $qty       = $this->parseNumber($m[2]);
                    $unitPrice = $this->parseNumber($m[3]);

                    if ($qty > 0 && $unitPrice > 0) {
                        $lines[] = $this->buildLine($designation, $qty, $unitPrice, null, $priceType);
                    }
                }
            }
        }

        // Pattern 3: Reversed — qty, unitPrice, total THEN reference+designation
        if (empty($lines)) {
            $pattern3 = '/(\d+[\.,]\d+)\s+(\d+[\s\.,]*\d*[\.,]\d+)\s+(\d+[\s\.,]*\d*[\.,]\d+)([A-Z][A-Za-z0-9_\-]+\s+.+?)$/m';
            if (preg_match_all($pattern3, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $designation = trim($m[4]);
                    if ($this->isTableHeader($designation)) continue;

                    $qty       = $this->parseNumber($m[1]);
                    $unitPrice = $this->parseNumber($m[2]);
                    $totalLine = $this->parseNumber($m[3]);

                    if ($qty > 0 && $unitPrice > 0) {
                        $lines[] = $this->buildLine($designation, $qty, $unitPrice, $totalLine, $priceType);
                    }
                }
            }
        }

        // Pattern 4: Advanced fallback
        if (empty($lines)) {
            $lines = $this->parseLineItemsAdvanced($text, $priceType);
        }

        return $lines;
    }

    private function buildLine(string $designation, float $qty, float $unitPrice, ?float $totalLine, string $priceType): array
    {
        $taxPercent = 20;

        if ($priceType === 'ttc') {
            $unitPriceHt = round($unitPrice / (1 + $taxPercent / 100), 3);
            $totalHt = $totalLine !== null
                ? round($totalLine / (1 + $taxPercent / 100), 2)
                : round($qty * $unitPriceHt, 2);
        } else {
            $unitPriceHt = $unitPrice;
            $totalHt = $totalLine ?? round($qty * $unitPrice, 2);
        }

        return [
            'designation'      => $designation,
            'quantity'         => $qty,
            'unit_price'       => round($unitPriceHt, 3),
            'unit_price_ttc'   => $priceType === 'ttc' ? $unitPrice : null,
            'total_ht'         => $totalHt,
            'tax_percent'      => $taxPercent,
        ];
    }

    /**
     * Advanced line parsing: split text into blocks, find product ref codes + numbers.
     */
    private function parseLineItemsAdvanced(string $text, string $priceType = 'ht'): array
    {
        $lines = [];

        $textLines = explode("\n", $text);

        foreach ($textLines as $raw) {
            $raw = trim($raw);
            if (empty($raw) || mb_strlen($raw) < 10) continue;
            if ($this->isTableHeader($raw)) continue;

            preg_match_all('/(\d+[\s\.,]*\d*[\.,]\d+)/', $raw, $numMatches);
            $numbers = array_map(fn($n) => $this->parseNumber($n), $numMatches[1] ?? []);

            $textPart = preg_replace('/[\d\s\.,]+/', ' ', $raw);
            $textPart = trim(preg_replace('/\s+/', ' ', $textPart));

            if (count($numbers) >= 2 && preg_match('/[A-Z][A-Z0-9_\-]{2,}/', $raw)) {
                $qty = null;
                $unitPrice = null;
                $totalLine = null;

                if (count($numbers) >= 3) {
                    $qty       = $numbers[0];
                    $unitPrice = $numbers[1];
                    $totalLine = $numbers[2];
                } elseif (count($numbers) === 2) {
                    $qty       = $numbers[0];
                    $unitPrice = $numbers[1];
                }

                if ($qty > 0 && $unitPrice > 0 && !empty($textPart)) {
                    $lower = mb_strtolower($textPart);
                    if (str_contains($lower, 'total') || str_contains($lower, 'net a payer')
                        || str_contains($lower, 'tva') || str_contains($lower, 'montant')) {
                        continue;
                    }

                    $lines[] = $this->buildLine($textPart, $qty, $unitPrice, $totalLine, $priceType);
                }
            }
        }

        return $lines;
    }

    private function isTableHeader(string $text): bool
    {
        $headers = ['designation', 'désignation', 'description', 'article', 'produit',
                     'quantité', 'quantite', 'qté', 'qte', 'prix', 'montant',
                     'unit', 'unité', 'total', 'h.t', 'ht', 'ttc', 'tva'];
        $lower = mb_strtolower($text);
        foreach ($headers as $h) {
            if (str_contains($lower, $h)) return true;
        }
        return false;
    }

    // ── Totals ──────────────────────────────────────────────────────

    private function parseTotals(string $text): array
    {
        // Standard: label then value
        $totalHt = $this->extractAmount($text, '/Total\s*H\.?T\.?\s*[:\-]?\s*([\d\s\.,]+)/i');
        // Reversed: value then label (e.g. "16 666,68Total  HT  :")
        if (!$totalHt) {
            $totalHt = $this->extractAmount($text, '/([\d][\d\s\.,]+?)Total\s*H\.?T\.?\s*[:\-]?/i');
        }

        $totalTax = $this->extractAmount($text, '/(?:TVA|T\.V\.A\.?|Montant\s*TVA)\s*(?:\d{1,2}\s*%\s*)?[:\-]?\s*([\d\s\.,]+)/i');

        $totalTtc = $this->extractAmount($text, '/(?:Total\s*T\.?T\.?C\.?|Net\s*[àa]\s*payer|Montant\s*Total)\s*[:\-]?\s*([\d\s\.,]+)/i');
        // "NET A PAYER :" with amount possibly on next lines (skip non-digit lines)
        if (!$totalTtc) {
            // Look for a standalone large number after NET A PAYER block
            if (preg_match('/NET\s*A\s*PAYER\s*[:\-]?\s*\n.*?\n\s*([\d][\d\s\.,]+)/i', $text, $m)) {
                $totalTtc = $this->parseNumber($m[1]);
            }
        }
        if (!$totalTtc) {
            // Reversed: value before label
            $totalTtc = $this->extractAmount($text, '/([\d][\d\s\.,]+?)(?:Total\s*T\.?T\.?C\.?|Net\s*[àa]\s*payer)/i');
        }

        // Infer missing totals
        if ($totalHt !== null && $totalTax !== null && $totalTtc === null) {
            $totalTtc = round($totalHt + $totalTax, 2);
        }
        if ($totalHt !== null && $totalTtc !== null && $totalTax === null) {
            $totalTax = round($totalTtc - $totalHt, 2);
        }
        if ($totalTax !== null && $totalTtc !== null && $totalHt === null) {
            $totalHt = round($totalTtc - $totalTax, 2);
        }

        return [
            'total_ht'  => $totalHt,
            'total_tax' => $totalTax,
            'total_ttc' => $totalTtc,
        ];
    }

    private function extractAmount(string $text, string $pattern): ?float
    {
        if (!preg_match($pattern, $text, $m)) return null;
        return $this->parseNumber($m[1]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function extractPattern(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Parse a number string handling both European (1.234,56) and US (1,234.56) formats.
     * Moroccan invoices often use: 1 234,56 or 1.234,56 (comma = decimal)
     */
    private function parseNumber(string $raw): float
    {
        $raw = trim($raw);
        $raw = preg_replace('/\s+/', '', $raw); // Remove spaces (thousands separator in Moroccan)

        // Detect format by last separator
        $lastComma = strrpos($raw, ',');
        $lastDot   = strrpos($raw, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                // European: 1.234,56
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                // US: 1,234.56
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($lastComma !== false) {
            // Comma is always decimal in Moroccan context: 239,575 = 239.575
            // (both 2 and 3 decimal places are common for unit prices)
            $raw = str_replace(',', '.', $raw);
        }

        return (float) $raw;
    }

    private function calculateConfidence(array $supplier, array $metadata, array $lines, array $totals): float
    {
        $score = 0;
        $maxScore = 7;

        if (!empty($supplier['name'])) $score++;
        if (!empty($supplier['ice'])) $score++;
        if (!empty($metadata['invoice_number'])) $score++;
        if (!empty($metadata['invoice_date'])) $score++;
        if (!empty($lines)) $score++;
        if ($totals['total_ht'] !== null) $score++;
        if ($totals['total_ttc'] !== null) $score++;

        return round($score / $maxScore, 2);
    }
}
