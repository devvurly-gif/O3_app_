<?php

namespace App\Services\Agents;

use App\Models\OrchestratorMessage;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * « Exporte en Excel », « télécharge ça en PDF » : transforme la dernière lecture affichée en fichier à télécharger.
 *
 * On exporte ce qui est affiché, tel quel : le titre, les lignes (« • élément — champ — champ » devient une ligne à
 * colonnes) et les notes. Les montants « 1 200,00 MAD » deviennent de vrais nombres dans Excel. Aucune donnée nouvelle
 * n'est lue : le fichier reprend la réponse que l'administrateur a sous les yeux.
 *
 * Le fichier est rangé à part de la base, au nom de l'administrateur qui l'a demandé (nul autre ne peut le télécharger) et
 * supprimé au bout de 24 heures. Aucun envoi : rien n'est transmis à quelqu'un d'autre.
 */
class ExportAssistant
{
    private const DIR = 'agent-exports';
    private const TTL_HOURS = 24;
    private const MAX_ROWS = 2000;

    /** @param string $format xlsx, csv ou pdf */
    public function export(User $admin, string $format): array
    {
        $last = OrchestratorMessage::where('user_id', $admin->id)->where('role', OrchestratorMessage::ROLE_ORCHESTRATOR)->whereNotNull('meta->cmd')
            ->latest('id')->first();
        if (!$last || $last->created_at->lt(now()->subHour())) {
            return $this->reply("Je n'ai rien à exporter : posez d'abord une question (par exemple « ventes du mois par vendeur »), puis dites « exporte en Excel » ou « exporte en PDF ».", error: true);
        }

        $doc = $this->parse($last->body);
        $title = $doc['title'] ?: 'Export';
        $bytes = match ($format) {
            'pdf'   => $this->pdf($doc, $admin),
            'csv'   => $this->csv($doc),
            default => $this->xlsx($doc),
        };
        $format = in_array($format, ['pdf', 'csv'], true) ? $format : 'xlsx';

        $this->cleanup($admin);
        $uuid = (string) Str::uuid();
        $name = Str::slug(mb_substr($title, 0, 60)) . '-' . now()->format('Y-m-d') . ".{$format}";
        Storage::disk('local')->put(self::DIR . "/{$admin->id}/{$uuid}__{$name}", $bytes);

        $label = ['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'][$format];

        return $this->reply(
            "Export prêt : « {$title} » en {$label} (" . count($doc['rows']) . ' ligne(s)' . (count($doc['notes']) > 0 ? ' et ' . count($doc['notes']) . ' note(s)' : '') . "). Le fichier reprend la réponse affichée, rien de plus ; il reste disponible 24 heures.",
            files: [['label' => "Télécharger {$name}", 'url' => "/agents/orchestrateur/exports/{$uuid}", 'name' => $name]],
        );
    }

    /**
     * Le fichier d'export d'un administrateur. @return array{path: string, name: string}|null
     */
    public function find(int $adminId, string $uuid): ?array
    {
        if (!preg_match('/^[0-9a-f\-]{36}$/', $uuid)) {
            return null;
        }
        foreach (Storage::disk('local')->files(self::DIR . "/{$adminId}") as $file) {
            if (str_starts_with(basename($file), "{$uuid}__")) {
                return ['path' => Storage::disk('local')->path($file), 'name' => substr(basename($file), 38)];
            }
        }

        return null;
    }

    /**
     * La réponse en titre, lignes et notes.
     *
     * @return array{title: string, rows: array<int, array<int, string>>, notes: array<int, string>}
     */
    public function parse(string $body): array
    {
        $lines = preg_split('/\R/', $body) ?: [];
        // Les mentions « Suite de votre question… » et « J'ai compris… » ne font pas partie des chiffres.
        while ($lines !== [] && preg_match('/^(Suite de votre question|Suite :|J\'ai compris)/u', trim($lines[0]))) {
            array_shift($lines);
            while ($lines !== [] && trim($lines[0]) === '') {
                array_shift($lines);
            }
        }

        $title = '';
        $rows = [];
        $notes = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '') {
                continue;
            }
            if ($title === '') {
                $title = rtrim($t, " :");
                continue;
            }
            if (preg_match('/^(?:•|\d+\.)\s+(.*)$/u', $t, $m)) {
                if (count($rows) < self::MAX_ROWS) {
                    $cells = [];
                    foreach (array_map('trim', explode(' — ', $m[1])) as $cell) {
                        // « 1 vente(s), 1 200,00 MAD » : le montant a sa propre colonne, pour qu'Excel le compte.
                        if (preg_match('/^(.+?),\s*([+-]?\d[\d \x{a0}\x{202f}]*,\d{2}\s*MAD)$/u', $cell, $mm)) {
                            $cells[] = $mm[1];
                            $cells[] = $mm[2];
                        } else {
                            $cells[] = $cell;
                        }
                    }
                    $rows[] = $cells;
                }
            } else {
                $notes[] = $t;
            }
        }

        return ['title' => $title, 'rows' => $rows, 'notes' => $notes];
    }

    /** @param array{title: string, rows: array<int, array<int, string>>, notes: array<int, string>} $doc */
    private function xlsx(array $doc): string
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Export');
        $sheet->setCellValue('A1', $doc['title']);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $r = 3;
        $maxCol = 1;
        foreach ($doc['rows'] as $cells) {
            foreach ($cells as $i => $cell) {
                $col = $i + 1;
                $maxCol = max($maxCol, $col);
                if (($n = self::amount($cell)) !== null) {
                    $sheet->setCellValue([$col, $r], $n);
                    $sheet->getStyle([$col, $r, $col, $r])->getNumberFormat()->setFormatCode('#,##0.00" MAD"');
                } else {
                    $sheet->setCellValueExplicit([$col, $r], $cell, DataType::TYPE_STRING);   // jamais interprété comme une formule
                }
            }
            $r++;
        }
        if ($doc['notes'] !== []) {
            $r++;
            foreach ($doc['notes'] as $note) {
                $sheet->setCellValueExplicit([1, $r++], $note, DataType::TYPE_STRING);
            }
        }
        for ($c = 1; $c <= $maxCol; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }

    /** CSV à point-virgule et BOM UTF-8 : Excel en français l'ouvre directement, accents compris. */
    private function csv(array $doc): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        $put = function (array $cells) use ($out) {
            // Une cellule qui commencerait par = + - @ serait lue comme une formule par un tableur : on la protège.
            fputcsv($out, array_map(fn ($c) => preg_match('/^[=+@]/', $c) || (preg_match('/^-/', $c) && self::amount($c) === null) ? "'" . $c : $c, $cells), ';', '"', '\\', "\r\n");
        };
        $put([$doc['title']]);
        $put([]);
        foreach ($doc['rows'] as $cells) {
            $put($cells);
        }
        if ($doc['notes'] !== []) {
            $put([]);
            foreach ($doc['notes'] as $note) {
                $put([$note]);
            }
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    private function pdf(array $doc, User $admin): string
    {
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $h = '<html><head><meta charset="utf-8"><style>body{font-family:"DejaVu Sans",sans-serif;font-size:9pt;color:#1b1b24} h1{font-size:15pt;color:#4a2fc4;margin:0 0 10px} table{width:100%;border-collapse:collapse;margin-top:8px} td{padding:4px 5px;border-bottom:0.6px solid #dcdce6;vertical-align:top} p.note{color:#444;margin:6px 0} .foot{margin-top:16px;color:#777;font-size:7.5pt}</style></head><body>';
        $h .= '<h1>' . $e($doc['title']) . '</h1>';
        if ($doc['rows'] !== []) {
            $h .= '<table>';
            foreach ($doc['rows'] as $cells) {
                $h .= '<tr>' . implode('', array_map(fn ($c) => '<td>' . $e($c) . '</td>', $cells)) . '</tr>';
            }
            $h .= '</table>';
        }
        foreach ($doc['notes'] as $note) {
            $h .= '<p class="note">' . $e($note) . '</p>';
        }
        $h .= '<div class="foot">Exporté le ' . now()->format('d/m/Y H:i') . ' par ' . $e($admin->name) . ' · O3, orchestrateur chef</div></body></html>';

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($h, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** « 1 200,00 MAD » devient 1200.0 ; tout le reste reste du texte. */
    private static function amount(string $cell): ?float
    {
        if (!preg_match('/^([+-]?\d[\d \x{a0}\x{202f}]*(?:,\d{1,2})?)\s*MAD$/u', trim($cell), $m)) {
            return null;
        }

        return (float) str_replace(',', '.', preg_replace('/[ \x{a0}\x{202f}]/u', '', $m[1]) ?? '');
    }

    /** Supprime les exports de cet administrateur vieux de plus de 24 heures. */
    private function cleanup(User $admin): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::DIR . "/{$admin->id}") as $file) {
            if ($disk->lastModified($file) < now()->subHours(self::TTL_HOURS)->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    /**
     * @param array<int, array{label: string, url: string, name: string}> $files
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, bool $error = false, array $files = []): array
    {
        return ['body' => $body, 'meta' => array_filter(['intent' => 'export', 'error' => $error ?: null, 'files' => $files ?: null], fn ($v) => $v !== null)];
    }
}
