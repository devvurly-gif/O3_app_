<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;

/**
 * Remplit l'Annexe 1 (périmètre et tarifs) du contrat de services avec la formule réellement souscrite et les prix EN VIGUEUR.
 *
 * Le contrat livré (`docs/legal/contrat-services-saas.docx`) garde ses cases à remplir. À chaque téléchargement ou envoi,
 * on en tire une copie dont l'annexe reprend `config('plans')` — donc ce que le super-administrateur a modifié dans
 * « Formules et prix » : modules cochés, volumétrie, frais de mise en service, abonnement mensuel, options. Il n'y a rien à
 * recopier à la main quand un prix change, et le contrat livré n'est jamais modifié.
 *
 * Ce qui n'a pas de source dans l'application (module additionnel, heure de support, téléphone) reste à remplir à la main :
 * on n'invente aucun chiffre. Sans l'extension zip ou si le gabarit n'a plus la forme attendue, on rend NULL et l'appelant
 * sert le document tel quel.
 */
class ContractAnnex
{
    public const TEMPLATE = 'docs/legal/contrat-services-saas.docx';

    /** Le contrat rempli pour ce client (octets du .docx), ou null s'il n'a pas pu l'être. */
    public function contractFor(Tenant $tenant): ?string
    {
        $plan = (array) config('plans.plans.' . $tenant->plan, []);
        $file = base_path(self::TEMPLATE);
        if ($plan === [] || !is_file($file) || !class_exists(\ZipArchive::class)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'o3c');
        if ($tmp === false || !copy($file, $tmp)) {
            return null;
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) {
                return null;
            }
            $xml = $zip->getFromName('word/document.xml');
            $filled = is_string($xml) ? $this->fill($xml, $plan, (array) config('plans.addons', [])) : null;
            if ($filled === null || !$zip->addFromString('word/document.xml', $filled)) {
                $zip->close();

                return null;
            }
            $zip->close();

            $bytes = file_get_contents($tmp);

            return $bytes === false ? null : $bytes;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Remplit le XML du document. Public pour être testé sans fichier.
     *
     * @param array<string, mixed> $plan
     * @param array<string, array<string, mixed>> $addons
     * @return string|null le XML rempli, ou null si l'Annexe 1 n'est pas reconnue
     */
    public function fill(string $xml, array $plan, array $addons): ?string
    {
        $start = strpos($xml, 'ANNEXE 1');
        $end = strpos($xml, 'ANNEXE 2', (int) $start);
        if ($start === false || $end === false) {
            return null;
        }
        $region = substr($xml, $start, $end - $start);
        $features = (array) ($plan['features'] ?? []);
        $limits = (array) ($plan['limits'] ?? []);

        // A. Modules : case cochée si la formule les inclut.
        foreach (['POS — Point de vente' => 'pos', 'e-Commerce / Boutique en ligne' => 'ecom', 'Paiement Bons de Livraison' => 'paiement_bl'] as $label => $feature) {
            $mark = in_array($feature, $features, true) ? '☑' : '☐';
            $region = $this->inRow($region, $label, fn (string $row) => str_replace(['<w:t>☐</w:t>', '<w:t>☑</w:t>'], "<w:t>{$mark}</w:t>", $row));
        }

        // B. Volumétrie incluse (null = illimité).
        $quota = fn (mixed $v, string $unit = '') => $v === null ? 'illimité' : $v . $unit;
        foreach (['Utilisateurs nommés' => $quota($limits['users'] ?? null), 'Terminaux POS' => $quota($limits['pos_terminals'] ?? null), 'Stockage fichiers' => $quota($limits['storage_gb'] ?? null, ' Go')] as $label => $value) {
            $region = $this->inParagraph($region, $label, fn (string $p) => $this->plain($p, $value, 22));
        }

        // C. Tarifs.
        $price = fn (int $cents) => number_format($cents / 100, 2, ',', ' ');
        $rows = [
            'Frais de mise en service' => (int) ($plan['setup_fee_cents'] ?? 0) > 0 ? $price((int) $plan['setup_fee_cents']) : 'Offerts',
            'Abonnement mensuel' => $price((int) ($plan['price_month_cents'] ?? 0)),
        ];
        if (in_array('pos', $features, true)) {
            $rows['Module POS'] = 'Inclus dans le pack';                                  // sinon : à chiffrer à la main, aucune option POS isolée n'existe au catalogue
        }
        foreach (['Utilisateur supplémentaire' => 'extra_user', 'Terminal POS supplémentaire' => 'extra_pos_terminal', 'Stockage supplémentaire' => 'extra_storage_5gb'] as $label => $addon) {
            if (isset($addons[$addon]['price_month_cents'])) {
                $rows[$label] = $price((int) $addons[$addon]['price_month_cents']);
            }
        }
        foreach ($rows as $label => $value) {
            $region = $this->inRow($region, $label, fn (string $row) => $this->plain($row, $value, 20));
        }
        $name = htmlspecialchars((string) ($plan['name'] ?? ''), ENT_XML1);
        $region = str_replace('Abonnement mensuel — pack souscrit', "Abonnement mensuel — pack {$name}", $region);

        return substr($xml, 0, $start) . $region . substr($xml, $end);
    }

    /** Applique $change à la première ligne de tableau dont le texte commence par $label. */
    private function inRow(string $region, string $label, callable $change): string
    {
        return (string) preg_replace_callback('#<w:tr>.*?</w:tr>#s', function (array $m) use ($label, $change) {
            return str_contains(html_entity_decode(strip_tags(str_replace('</w:t>', '|', $m[0]))), $label) ? $change($m[0]) : $m[0];
        }, $region, -1);
    }

    private function inParagraph(string $region, string $label, callable $change): string
    {
        return (string) preg_replace_callback('#<w:p>(?:(?!</w:p>).)*?</w:p>#s', function (array $m) use ($label, $change) {
            return str_contains(strip_tags($m[0]), $label) ? $change($m[0]) : $m[0];
        }, $region, -1);
    }

    /** Remplace le texte jaune à compléter (« […] », « [5] ») par la valeur, en texte courant. */
    private function plain(string $chunk, string $value, int $size): string
    {
        $run = '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:sz w:val="' . $size . '"/></w:rPr><w:t xml:space="preserve">' . htmlspecialchars($value, ENT_XML1) . '</w:t></w:r>';

        return (string) preg_replace('#<w:r><w:rPr><w:rFonts w:ascii="Consolas"(?:(?!</w:rPr>).)*</w:rPr><w:t>\[[^<]*\]</w:t></w:r>#s', $run, $chunk, 1);
    }
}
