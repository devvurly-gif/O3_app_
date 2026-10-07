<?php

namespace App\Services\Agents;

use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lit un relevé bancaire Excel ou CSV, localement : AUCUNE IA, rien ne quitte O3.
 *
 * Les banques marocaines n'ont pas de format commun : la ligne d'en-têtes est donc cherchée (date, libellé, débit /
 * crédit ou montant signé), les séparateurs et l'encodage du CSV sont devinés, et les nombres (« 1 234,56 », « 1.234,56 »,
 * « 1234.56 ») comme les dates (« 31/10/2026 », « 2026-10-31 », « 31-10-26 ») sont lus de façon stricte : une ligne dont
 * la date ou le montant n'est pas sûr est ignorée et comptée, jamais devinée.
 */
class BankStatementParser
{
    public const MAX_ROWS = 2000;

    private ?string $failure = null;

    public function failure(): ?string
    {
        return $this->failure;
    }

    /**
     * @return array{lines: array<int, array{date: string, label: string, credit: float, debit: float}>, ignored: int}|null null si le fichier n'est pas un relevé lisible (voir failure())
     */
    public function parse(string $path, string $extension): ?array
    {
        $this->failure = null;
        if (strtolower($extension) === 'xlsx' && !class_exists(\ZipArchive::class)) {
            $this->failure = "l'extension PHP « zip » n'est pas activée sur ce serveur : exportez le relevé en CSV, ou activez cette extension";

            return null;
        }
        try {
            $rows = in_array(strtolower($extension), ['xlsx', 'xls'], true) ? $this->spreadsheetRows($path) : $this->csvRows($path);
        } catch (\Throwable $e) {
            $this->failure = 'le fichier est illisible (' . Str::limit($e->getMessage(), 80) . ')';

            return null;
        }

        return $this->lines($rows);
    }

    /** @param array<int, array<int, mixed>> $rows */
    public function lines(array $rows): ?array
    {
        $headerAt = null;
        $map = [];
        foreach (array_slice($rows, 0, 20, true) as $i => $row) {
            $m = $this->columns(array_map(fn ($c) => $this->key($c), $row));
            if (isset($m['date']) && (isset($m['credit']) || isset($m['amount']))) {
                $headerAt = $i;
                $map = $m;
                break;
            }
        }
        if ($headerAt === null) {
            $this->failure = "je n'ai pas trouvé la ligne d'en-têtes (il faut au moins une colonne « Date » et une colonne « Crédit » ou « Montant »)";

            return null;
        }

        $lines = [];
        $ignored = 0;
        foreach (array_slice($rows, $headerAt + 1, self::MAX_ROWS, true) as $row) {
            if (count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $date = $this->date($row[$map['date']] ?? null);
            $label = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter(array_map(fn ($c) => trim((string) ($row[$c] ?? '')), $map['label'] ?? []))) ) ?? '');
            if ($date === null || preg_match('/^(total|solde|report|cumul)\b/i', Str::ascii($label))) {
                $ignored++;                                                            // total, solde, ligne de bas de page
                continue;
            }
            if (isset($map['amount']) && !isset($map['credit'])) {
                $amount = $this->number($row[$map['amount']] ?? null);
                $credit = $amount !== null && $amount > 0 ? $amount : 0.0;
                $debit = $amount !== null && $amount < 0 ? -$amount : 0.0;
                $valid = $amount !== null;
            } else {
                $c = $this->number($row[$map['credit']] ?? null);
                $d = isset($map['debit']) ? $this->number($row[$map['debit']] ?? null) : 0.0;
                $credit = $c !== null && $c > 0 ? $c : 0.0;
                $debit = $d !== null ? abs($d) : 0.0;
                $valid = ($c !== null || $d !== null) || trim((string) ($row[$map['credit']] ?? '')) === '';
            }
            if (!$valid || ($credit <= 0 && $debit <= 0)) {
                $ignored++;
                continue;
            }
            $lines[] = ['date' => $date, 'label' => $label, 'credit' => round($credit, 2), 'debit' => round($debit, 2)];
        }

        if ($lines === []) {
            $this->failure = 'aucune ligne exploitable sous les en-têtes (dates ou montants illisibles)';

            return null;
        }

        return ['lines' => $lines, 'ignored' => $ignored];
    }

    /** @return array<int, array<int, mixed>> */
    private function spreadsheetRows(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();

        return array_values($sheet->toArray(null, true, true, false));
    }

    /** @return array<int, array<int, string>> */
    private function csvRows(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }
        $lines = preg_split('/\r\n|\n|\r/', $raw) ?: [];
        $sample = implode("\n", array_slice($lines, 0, 15));
        $delimiter = collect([';', "\t", ',', '|'])->sortByDesc(fn ($d) => substr_count($sample, $d))->first();

        return array_map(fn ($l) => str_getcsv($l, $delimiter, '"', ''), array_values(array_filter($lines, fn ($l) => trim($l) !== '')));
    }

    private function key(mixed $cell): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $cell))) ?? '');
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    private function columns(array $keys): array
    {
        $m = ['label' => []];
        foreach ($keys as $i => $k) {
            if ($k === '') {
                continue;
            }
            if (preg_match('/^date( (operation|comptable|op))?$/', $k) && !isset($m['date'])) {
                $m['date'] = $i;
            } elseif (preg_match('/^(credit|versements?|depots?|entrees?|recettes?|encaissements?)$/', $k) && !isset($m['credit'])) {
                $m['credit'] = $i;
            } elseif (preg_match('/^(debit|retraits?|sorties?|depenses?)$/', $k) && !isset($m['debit'])) {
                $m['debit'] = $i;
            } elseif (preg_match('/^(montant|amount)$/', $k) && !isset($m['amount'])) {
                $m['amount'] = $i;
            } elseif (preg_match('/(libelle|designation|description|operation|detail|motif|reference|intitule|nature)/', $k) && !str_starts_with($k, 'date')) {
                $m['label'][] = $i;
            }
        }

        return $m;
    }

    private function date(mixed $v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return Carbon::instance($v)->toDateString();
        }
        if (is_numeric($v) && (float) $v > 30000 && (float) $v < 80000) {            // numéro de série Excel
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString();
        }
        $s = trim((string) $v);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4}|\d{2})(?!\d)/', $s, $m)) {
            $year = (int) $m[3] + (strlen($m[3]) === 2 ? 2000 : 0);
            if (checkdate((int) $m[2], (int) $m[1], $year)) {
                return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);
            }
        }

        return null;
    }

    /** « 1 234,56 », « 1.234,56 », « 1,234.56 », « 1234.5 », « -250 » ; null si ce n'est pas un nombre sûr. */
    public function number(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = trim(str_replace(["\u{a0}", "\u{202f}", ' ', 'MAD', 'DH', 'dh', 'mad'], '', (string) $v));
        if ($s === '') {
            return null;
        }
        $negative = str_starts_with($s, '-') || (str_starts_with($s, '(') && str_ends_with($s, ')'));
        $s = ltrim(trim($s, '()'), '-+');
        if (!preg_match('/^\d[\d.,]*$/', $s)) {
            return null;
        }
        $comma = strrpos($s, ',');
        $dot = strrpos($s, '.');
        if ($comma !== false && $dot !== false) {
            $decimal = $comma > $dot ? ',' : '.';
        } elseif ($comma !== false) {
            $decimal = strlen($s) - $comma - 1 <= 2 ? ',' : null;
        } elseif ($dot !== false) {
            $decimal = strlen($s) - $dot - 1 <= 2 && substr_count($s, '.') === 1 ? '.' : null;
        } else {
            $decimal = null;
        }
        $clean = $decimal === null ? preg_replace('/\D/', '', $s) : str_replace($decimal, '#', preg_replace('/[.,](?=.*[.,])/', '', $s) ?? $s);
        $value = (float) str_replace('#', '.', (string) $clean);

        return $negative ? -$value : $value;
    }
}
