<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\DocumentIncrementor;
use App\Models\DocumentLigne;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentIncrementorService;
use App\Services\StockMouvementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import des bons de livraison consolidés du fournisseur LEADER STAR
 * (relevé "ETAT DES COMPTES" du 24/08/2026, client MR JAWAD LIGHT / F000053).
 *
 * Sens du flux : LEADER STAR livre le tenant → ce sont des ACHATS, donc des
 * Bons de Réception (ReceiptNotePurchase), pas des BL de vente.
 *
 * Les BL n'affichent qu'un TOTAL sans ligne de TVA : les montants sont donc
 * traités comme TTC et le HT est rétro-calculé (--tax, 20 % par défaut). Le
 * footer porte le TTC exact du BL pour rester aligné au centime près avec le
 * relevé fournisseur ; seul le HT/TVA subit l'arrondi ligne à ligne.
 *
 * Le numéro du fournisseur ne devient pas la référence du document (elle vient
 * de l'incrémenteur du tenant) mais il est écrit dans les notes — c'est aussi
 * la clé d'idempotence : relancer la commande ne recrée pas un BL déjà importé.
 */
class ImportJademaBls extends Command
{
    protected $signature = 'import:jadema-bls
        {tenant=jadema : ID du tenant destinataire}
        {--supplier=LEADER STAR : Raison sociale du fournisseur (tp_title)}
        {--warehouse= : Code dépôt (wh_code) ; défaut = premier dépôt}
        {--tax=20 : Taux de TVA inclus dans les prix du BL}
        {--no-stock : Ne pas générer les mouvements de stock}
        {--dry-run : Analyser sans rien écrire}';

    protected $description = 'Importe les BL consolidés LEADER STAR en Bons de Réception dans un tenant';

    /**
     * Source : data_bls.xlsx (feuille "bls") + dates de la feuille
     * "Rapprochement". 8 BL, 71 lignes, 103 455,00 MAD.
     */
    private array $bls = [
        [
            'no'    => '2026002115',
            'date'  => '2026-07-09',
            'lines' => [
                ['sku' => 'JDKN3A25', 'designation' => 'PULVÉRISATEUR À DOS À ESSENCECYLINDRÉE : 25,4 CM3 0,75 KW : 25 L', 'qty' => 2, 'pu' => 1000],
                ['sku' => 'JDGNAA1906Q', 'designation' => 'MOTEUR ESSENCE 15HP', 'qty' => 1, 'pu' => 1800],
                ['sku' => 'JDTR1201', 'designation' => 'COUPE CARREAUX MANUEL DE QUALITE INDUSTRIELLE 1200MM', 'qty' => 1, 'pu' => 1100],
                ['sku' => 'JDTR8510', 'designation' => 'COUPE CARREAUX MANUEL DE QUALITE INDUSTRIELLE 1000MM', 'qty' => 1, 'pu' => 800],
                ['sku' => 'JDDB1D80', 'designation' => 'DEGAGEUR 45J 1600 W -220V 14 KG', 'qty' => 2, 'pu' => 1050],
                ['sku' => 'JDDB2D95', 'designation' => 'DEGAGEUR 80J 1950 W -220V 18 KG', 'qty' => 2, 'pu' => 1800],
                ['sku' => 'JDED15501', 'designation' => 'PERCEUSE ÉLECTRIQUE 500 W-220V', 'qty' => 6, 'pu' => 230],
                ['sku' => 'JDMD151051', 'designation' => 'PERCEUSE À PERCUSSION TENSION 1050W : 220-240 V 0-3000 TR/MIN', 'qty' => 6, 'pu' => 280],
                ['sku' => 'JDGC2558', 'designation' => 'TRONÇONNEUSE À ESSENCE20 CYLINDRÉE : 58 CM³ -2,4 KW 3 100 ± 300 TR/MIN DIAMÈTRE DE COUPE MAX. : 470 MM (20)', 'qty' => 2, 'pu' => 1200],
                ['sku' => 'JDAP1A24', 'designation' => 'COMPRESSEUR SILENCIEUX 24L 220V 1 100 W (1,5 HP-6,3 GAL) 2 850 TR/MIN', 'qty' => 2, 'pu' => 1100],
                ['sku' => 'JDLM15225', 'designation' => 'MARTEAU PERFORATEUR 20 V', 'qty' => 4, 'pu' => 730],
                ['sku' => 'JDLM1B283', 'designation' => 'MARTEAU PERFORATEUR SANS FIL, MOTEUR SANS BALAIS TENSION : 20 V VITESSE À VIDE', 'qty' => 4, 'pu' => 920],
                ['sku' => 'JDMX151201', 'designation' => 'MELANGEURS 220-240 V~50/60 HZ PUISSANCE D\'ENTRÉE : 1200 W VITESSE À VIDE : 0-480 TR/MIN/', 'qty' => 2, 'pu' => 530],
                ['sku' => 'JDRH1D26-2', 'designation' => 'MARTEAU PERFORÉ 800W 220V 3 FORETS 2 BURNES', 'qty' => 4, 'pu' => 600],
                ['sku' => 'JDRH2D26', 'designation' => 'MARTEAU PERFORATEUR220-240V~1050W:1100R PM', 'qty' => 4, 'pu' => 600],
                ['sku' => 'JDSU30664', 'designation' => 'PISTOLET PULVÉRISATEUR SANS FIL TENSION : 20 V PRESSION DE PULVÉRISATION : 0,1-0,2 BAR DÉBIT MAX.: 650 ML/MIN', 'qty' => 4, 'pu' => 300],
                ['sku' => 'JDWPJA04', 'designation' => 'POMPE SURFACE 1,5 HP 220V 2TURBINE : 63 L/MIN ASPIRATION : 9 M', 'qty' => 2, 'pu' => 680],
                ['sku' => 'JDWPJA05', 'designation' => 'POMPE SURFACE 2HP 220 2 TURBINNE M DÉBIT : 100 L/MIN ASPIRATION: 9 M', 'qty' => 2, 'pu' => 830],
                ['sku' => 'JDWPWA03', 'designation' => 'POMPE VIDE CAVE 1HP 220 V HAUTEUR: 13 M DÉBIT: 300 L/MIN', 'qty' => 2, 'pu' => 1100],
                ['sku' => 'JDWPWA05', 'designation' => 'POMPE VIDE CAVE 2HP 220VHAUTEUR: 14,5 M DÉBIT: 350 L/MIN BROYEUR', 'qty' => 2, 'pu' => 1450],
                ['sku' => 'PUL-POMP22', 'designation' => 'POMPE 22 POUR PULVERSATEU', 'qty' => 1, 'pu' => 400],
                ['sku' => 'PUL-POMP30', 'designation' => 'POMPE 30POUR PULVERSATEUR', 'qty' => 1, 'pu' => 490],
                ['sku' => 'BAL100L', 'designation' => 'SURPRESSUR BALLON 100L', 'qty' => 1, 'pu' => 1200],
                ['sku' => 'BAL50L', 'designation' => 'SURPRESSEUR BALLON 50L', 'qty' => 1, 'pu' => 400],
                ['sku' => 'TARIERE-LC', 'designation' => 'TARIERE 52CC AVEC 2 PIECES DE TARIERE 200/300', 'qty' => 2, 'pu' => 1600],
                ['sku' => 'MEM5.5-3-NX', 'designation' => 'MOTEUR ELECTRIQUE 5.5HP 3000T 220V YL112M-2', 'qty' => 2, 'pu' => 1250],
                ['sku' => 'JDGW1A21', 'designation' => 'MOTEUR POMPE ESSENCE. 50 MM, 2 POUCES.', 'qty' => 2, 'pu' => 1100],
                ['sku' => 'JDGW1A31', 'designation' => 'MOTEUR POMPE ESSANCE 3 P', 'qty' => 2, 'pu' => 1150],
                ['sku' => 'MPD3F', 'designation' => 'MOTEUR POMPE DIESEL 186FA POMPE FONT', 'qty' => 1, 'pu' => 3300],
                ['sku' => 'PVC1.5HPWQD30-15-A', 'designation' => 'POMPE VIDE CAVE 1.5HP 220V 2\' H.MAX15M Q.MAX30M/H FIL D\'ALUMINIUM', 'qty' => 2, 'pu' => 1050],
                ['sku' => 'DEG65-DH3001', 'designation' => 'DEGAGEUR 65A 220V 2500W 1400R/MIN UPSPIRT JAUNE', 'qty' => 1, 'pu' => 850],
                ['sku' => 'DEG68J-DH9898S', 'designation' => 'DEGAGEUR 68J 3600W 1950R/MIN 220V HK-DH9898S', 'qty' => 1, 'pu' => 1350],
                ['sku' => 'DEG95A', 'designation' => 'DEGAGEUR 95A-3200W CUIVRE 220 AVEC BURAN PLAT ET POINT CAPLE EP 1.2MM', 'qty' => 1, 'pu' => 1000],
                ['sku' => 'DEG-DH100A', 'designation' => 'DEGAGEUR 100A 220V 4800W 1800R/MIN UPSPRIT', 'qty' => 1, 'pu' => 1150],
                ['sku' => 'JDLD6H39', 'designation' => 'ECHELLE COULISSANTE ALUMUINUIM 3*9', 'qty' => 2, 'pu' => 1700],
                ['sku' => 'POST-OK-ARC300', 'designation' => 'POSTE A SOUDER MMA 300A OKEM-ARC300 BAQUETTE 3.5', 'qty' => 1, 'pu' => 1000],
                ['sku' => 'POST-OK-ARC400', 'designation' => 'POSTE A SOUDER MMA 400 OKEM-ARTC400 BAQ 4', 'qty' => 1, 'pu' => 1100],
                ['sku' => 'PUL-WP196-22', 'designation' => 'PULVERISATEUR 7.5HP POMPE 22', 'qty' => 1, 'pu' => 1600],
                ['sku' => 'PUL-EY20', 'designation' => 'PULVERSATEUR ROBIN POMPE 22', 'qty' => 1, 'pu' => 1700],
                ['sku' => 'JDCD1B33', 'designation' => 'CLÉ À CHOCS SANS FIL,: 20 V', 'qty' => 2, 'pu' => 550],
                ['sku' => 'JDMD15851', 'designation' => 'PERCEUSE 220-240 V: 850 W-3 000 TR/MIN', 'qty' => 3, 'pu' => 230],
                ['sku' => 'JDJS15401', 'designation' => 'SCIE SAUTEUS TENSION : 220-240 V 400 W VITESSE À VIDE : 800-3 000 TR/MIN CAPACITÉ DE COUPE : BOIS : 55 MM ACIER : 3 MM', 'qty' => 3, 'pu' => 230],
                ['sku' => 'JDJS15401', 'designation' => 'SCIE SAUTEUS TENSION : 220-240 V 400 W VITESSE À VIDE : 800-3 000 TR/MIN CAPACITÉ DE COUPE : BOIS : 55 MM ACIER : 3 MM LIVRER EN ATTENTE ....', 'qty' => 1, 'pu' => 230],
            ],
        ],
        [
            'no'    => '202620331',
            'date'  => '2026-07-09',
            'lines' => [
                ['sku' => 'VBEY20-NX', 'designation' => 'VIBREUR ESSANCE EY20 NEXTGEN', 'qty' => 2, 'pu' => 1200],
                ['sku' => 'ME-EY-20NX', 'designation' => 'MOTEUR ESSANCE ROBIN EY20 NEXTGEN', 'qty' => 2, 'pu' => 1050],
            ],
        ],
        [
            'no'    => '202630212',
            'date'  => '2026-07-18',
            'lines' => [
                ['sku' => 'JDQF2A16', 'designation' => 'SCIE A SOL À ESSENCE 13HP POIDS 120 KG', 'qty' => 1, 'pu' => 7600],
            ],
        ],
        [
            'no'    => '202630236',
            'date'  => '2026-08-05',
            'lines' => [
                ['sku' => 'CESAILE-MM', 'designation' => 'CESAILE MOUSAOUI MOYAN MODEL FER 16 MAX', 'qty' => 1, 'pu' => 1050],
                ['sku' => 'CESAILE-GM', 'designation' => 'CESAILE MOUSAOUI GRAND MODEL FER 18 MM', 'qty' => 1, 'pu' => 1200],
                ['sku' => 'JDHJ1506', 'designation' => 'CRIC HYDRAULIQUE 6 TONNES', 'qty' => 1, 'pu' => 250],
                ['sku' => 'JDHJ1532', 'designation' => 'CRIC BOUTEILLE HYDRAULIQUE 32 T POIDS : 14,75 KG', 'qty' => 1, 'pu' => 800],
                ['sku' => 'JDBJ2308', 'designation' => 'CLOUEUR A CLOUS SANS FIL 20V 15-50MM', 'qty' => 1, 'pu' => 1000],
                ['sku' => 'JDAAC511', 'designation' => 'COMPRESSEUR D\'AIR AUTOMATIQUE 12 V', 'qty' => 1, 'pu' => 400],
                ['sku' => 'JDAAC501', 'designation' => 'COMPRESSEUR D\'AIR AUTOMATIQUE TENSION : 12 V PRESSION : 150 PSI/10,34 BAR DÉBIT D\'AIR: 9 L/MIN', 'qty' => 1, 'pu' => 250],
                ['sku' => 'JDHG2501', 'designation' => 'PISTOLET THERMIQUE 350W 230-400°C', 'qty' => 1, 'pu' => 110],
                ['sku' => 'JDLAP5421', 'designation' => 'MEULEUSE SANS FIL 20V 850W 20V BATTRY 4 AH 115MM', 'qty' => 1, 'pu' => 750],
                ['sku' => 'JDGA1504', 'designation' => 'PISTOLET À AIR SANS PRISE RAPIDE BUSE STANDARD : 1,5 MM 3-4BARCAPACITÉ DE PEINTURE : 600 CC', 'qty' => 1, 'pu' => 130],
                ['sku' => 'JDGA1561', 'designation' => 'PISTOLET À AIR SANS PRISE RAPIDE BUSE STANDARD 3-4BAR: 1,5 MM CAPACITÉ DE PEINTURE : 1000 CC CONSOMMATION D\'AIR : 42-71 L/MIN', 'qty' => 1, 'pu' => 145],
                ['sku' => 'JDLV20201', 'designation' => 'ASPIRATEUR SANS FIL TENSION : 20 V CAPACITÉ : 0,45 L', 'qty' => 1, 'pu' => 300],
                ['sku' => 'JDLWP5521', 'designation' => 'SCIE CIRCULAIRE SANS FIL, 20 V VITESSE À VIDE : 5 200 TR/MIN LAME : 165 MM', 'qty' => 1, 'pu' => 800],
                ['sku' => 'JDGA1510', 'designation' => 'PISTOLET À AIR COMPRIMÉ SANS RACCORD RAPIDE BUSE STANDARD : 1,5 MM', 'qty' => 1, 'pu' => 120],
            ],
        ],
        [
            'no'    => '202630246',
            'date'  => '2026-08-09',
            'lines' => [
                ['sku' => 'JDGA1504', 'designation' => 'PISTOLET À AIR SANS PRISE RAPIDE BUSE STANDARD : 1,5 MM 3-4BARCAPACITÉ DE PEINTURE : 600 CC', 'qty' => 1, 'pu' => 115],
                ['sku' => 'POL-EL', 'designation' => 'POLIE ELECTRIQUE', 'qty' => 1, 'pu' => 55],
            ],
        ],
        [
            'no'    => '2026002534',
            'date'  => '2026-08-12',
            'lines' => [
                ['sku' => 'JDAP1A06', 'designation' => 'COMPRESSEUR SILENCIEUX 6L 220V 1 100 W (1,5 HP-1,6 GAL): 2 850 TR/MIN', 'qty' => 2, 'pu' => 700],
                ['sku' => 'PS0.5HP-S-AC-220', 'designation' => 'POMPE SURFACE QB 0.5HP ALUMINUM TURBINE CUIVRE QB60 1P 220V-50HZ', 'qty' => 2, 'pu' => 220],
                ['sku' => 'PS1HP-S-AC-220', 'designation' => 'POMPE SURFACE QB 1HP ALUMINUM TURBINE CUIVRE QB80 1P 220V-50HZ', 'qty' => 2, 'pu' => 420],
                ['sku' => 'JDAP2A45', 'designation' => 'COMPRESSEUR SILENCIEUX 50L 220V 2 × 1200 W (3,2 HP-13,2 GAL) 2850TR/MIN', 'qty' => 1, 'pu' => 1800],
                ['sku' => 'PS0.75HP-S-AC-220', 'designation' => 'POMPE SURFACE QB 0.75HP ALUMINUM TURBINE CUIVRE QB70 1P 220V-50HZ', 'qty' => 2, 'pu' => 320],
                ['sku' => 'JDHJ2503', 'designation' => 'CRIC HYDRAULIQUE 3 TONNES TRANSPORT BOURKADI', 'qty' => 1, 'pu' => 1150],
            ],
        ],
        [
            'no'    => '202630245',
            'date'  => '2026-08-12',
            'lines' => [
                ['sku' => 'JDGM2543', 'designation' => 'DEBROUSSAILLEUSE À ESSENCE 1,25KW PUISSANCE 8 000 TR/MIN', 'qty' => 1, 'pu' => 1200],
                ['sku' => 'DEB-ESS-LC', 'designation' => 'DEBROUSSAILLEUSE ESSANCE', 'qty' => 1, 'pu' => 780],
            ],
        ],
        [
            'no'    => '2026002644',
            'date'  => '2026-08-24',
            'lines' => [
                ['sku' => 'PRISECONTROLE-A.M', 'designation' => 'PRISE CONTROLE 10BAR AVEC MANOMETRE', 'qty' => 6, 'pu' => 140],
            ],
        ],
    ];

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant   = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' introuvable.");
            return self::FAILURE;
        }

        $this->info("Tenant : {$tenant->name} ({$tenant->id})");

        $exit = self::SUCCESS;
        $tenant->run(function () use (&$exit) {
            $exit = $this->import();
        });

        return $exit;
    }

    private function import(): int
    {
        $taxRate = (float) $this->option('tax');
        $dryRun  = (bool) $this->option('dry-run');

        $supplier = ThirdPartner::where('tp_title', $this->option('supplier'))
            ->whereIn('tp_Role', ['supplier', 'both'])
            ->first();

        if (!$supplier) {
            $this->error("Fournisseur '{$this->option('supplier')}' introuvable dans ce tenant.");
            return self::FAILURE;
        }

        $warehouse = $this->option('warehouse')
            ? Warehouse::where('wh_code', $this->option('warehouse'))->first()
            : Warehouse::orderBy('id')->first();

        if (!$warehouse) {
            $this->error('Aucun dépôt trouvé.');
            return self::FAILURE;
        }

        $user = User::orderBy('id')->first();
        if (!$user) {
            $this->error('Aucun utilisateur trouvé pour porter les documents.');
            return self::FAILURE;
        }

        $incrementor = DocumentIncrementor::where('di_model', 'ReceiptNotePurchase')->first();
        if (!$incrementor) {
            $this->error("Aucun incrémenteur 'ReceiptNotePurchase' configuré.");
            return self::FAILURE;
        }

        $this->line("Fournisseur : {$supplier->tp_title} (#{$supplier->id})");
        $this->line("Dépôt       : {$warehouse->wh_title} ({$warehouse->wh_code})");
        $this->line("Utilisateur : {$user->name} (#{$user->id})");
        $this->line('TVA         : ' . $taxRate . ' % incluse dans les prix du BL');
        $this->line('Stock       : ' . ($this->option('no-stock') ? 'non mouvementé' : 'entrée appliquée'));
        $this->newLine();

        if ($dryRun) {
            return $this->analyse($taxRate);
        }

        $stockService = app(StockMouvementService::class);
        $incrService  = app(DocumentIncrementorService::class);

        $created = 0;
        $skipped = 0;
        $newProducts = 0;
        $grandTotal = 0.0;

        foreach ($this->bls as $bl) {
            $existing = DocumentHeader::where('document_type', 'ReceiptNotePurchase')
                ->where('notes', 'like', '%N° ' . $bl['no'] . '%')
                ->first();

            if ($existing) {
                $this->warn("  SKIP  BL {$bl['no']} — déjà importé ({$existing->reference}).");
                $skipped++;
                continue;
            }

            $result = DB::transaction(function () use ($bl, $supplier, $warehouse, $user, $incrementor, $incrService, $stockService, $taxRate) {
                $reference = $incrService->consumeNext($incrementor);

                $document = DocumentHeader::create([
                    'document_incrementor_id' => $incrementor->id,
                    'reference'               => $reference,
                    'document_type'           => 'ReceiptNotePurchase',
                    'document_title'          => 'Bon de Réception',
                    'thirdPartner_id'         => $supplier->id,
                    'user_id'                 => $user->id,
                    'warehouse_id'            => $warehouse->id,
                    'status'                  => 'confirmed',
                    'issued_at'               => $bl['date'],
                    'notes'                   => 'Import BL fournisseur ' . $supplier->tp_title
                                                 . ' — N° ' . $bl['no']
                                                 . ' du ' . date('d/m/Y', strtotime($bl['date'])),
                ]);

                $totalTtc  = 0.0;
                $totalHt   = 0.0;
                $newProds  = 0;

                foreach ($bl['lines'] as $i => $line) {
                    [$product, $isNew] = $this->resolveProduct($line, $taxRate);
                    $newProds += $isNew ? 1 : 0;

                    $unitPriceHt = round($line['pu'] / (1 + $taxRate / 100), 2);

                    $ligne = DocumentLigne::create([
                        'document_header_id' => $document->id,
                        'product_id'         => $product->id,
                        'sort_order'         => $i + 1,
                        'line_type'          => 'product',
                        'designation'        => Str::limit($line['designation'], 250, ''),
                        'reference'          => $line['sku'],
                        'quantity'           => $line['qty'],
                        'unit'               => $product->p_unit ?: 'pièce',
                        'unit_price'         => $unitPriceHt,
                        'discount_percent'   => 0,
                        'tax_percent'        => $taxRate,
                        'status'             => 'active',
                    ]);

                    $totalHt  += (float) $ligne->total_ligne_ht;
                    $totalTtc += $line['qty'] * $line['pu'];
                }

                // Le TTC reste celui du BL papier ; la TVA absorbe l'arrondi HT.
                $totalHt  = round($totalHt, 2);
                $totalTtc = round($totalTtc, 2);

                DocumentFooter::create([
                    'document_header_id' => $document->id,
                    'total_ht'           => $totalHt,
                    'total_discount'     => 0,
                    'total_tax'          => round($totalTtc - $totalHt, 2),
                    'total_ttc'          => $totalTtc,
                    'amount_paid'        => 0,
                    'amount_due'         => $totalTtc,
                ]);

                if (!$this->option('no-stock')) {
                    $document->load('lignes');
                    $stockService->processDocument($document);
                }

                return [$document, $totalTtc, $newProds];
            });

            [$document, $totalTtc, $newProds] = $result;

            $created++;
            $newProducts += $newProds;
            $grandTotal  += $totalTtc;

            $this->info(sprintf(
                '  OK    BL %s → %s | %s ligne(s) | %s MAD%s',
                $bl['no'],
                $document->reference,
                count($bl['lines']),
                number_format($totalTtc, 2, ',', ' '),
                $newProds ? " | {$newProds} produit(s) créé(s)" : ''
            ));
        }

        if ($created > 0) {
            $supplier->recalculateEncours();
        }

        $this->newLine();
        $this->info(sprintf(
            'Terminé — %d BR créé(s), %d ignoré(s), %d produit(s) créé(s), total %s MAD.',
            $created,
            $skipped,
            $newProducts,
            number_format($grandTotal, 2, ',', ' ')
        ));

        return self::SUCCESS;
    }

    /**
     * Passe d'analyse : ce que l'import ferait, sans rien écrire.
     */
    private function analyse(float $taxRate): int
    {
        $rows        = [];
        $missing     = [];
        $alreadyDone = 0;
        $grandTotal  = 0.0;

        foreach ($this->bls as $bl) {
            $total = 0.0;
            foreach ($bl['lines'] as $line) {
                $total += $line['qty'] * $line['pu'];
                if (!$this->findProduct($line['sku'])) {
                    $missing[$line['sku']] = $line['designation'] . ' — ' . $line['pu'] . ' MAD';
                }
            }

            $done = DocumentHeader::where('document_type', 'ReceiptNotePurchase')
                ->where('notes', 'like', '%N° ' . $bl['no'] . '%')
                ->exists();
            $alreadyDone += $done ? 1 : 0;
            $grandTotal  += $total;

            $rows[] = [
                $bl['no'],
                date('d/m/Y', strtotime($bl['date'])),
                count($bl['lines']),
                number_format($total, 2, ',', ' '),
                number_format(round($total / (1 + $taxRate / 100), 2), 2, ',', ' '),
                $done ? 'déjà importé' : 'à créer',
            ];
        }

        $this->table(['N° BL', 'Date', 'Lignes', 'TTC', 'HT (approx.)', 'Statut'], $rows);
        $this->info('Total : ' . number_format($grandTotal, 2, ',', ' ') . ' MAD sur '
            . count($this->bls) . ' BL — ' . $alreadyDone . ' déjà présent(s).');

        if ($missing) {
            $this->newLine();
            $this->warn(count($missing) . ' SKU absent(s) du catalogue (seraient créés) :');
            foreach ($missing as $sku => $label) {
                $this->line("  - {$sku} : {$label}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Les produits supprimés comptent : le soft-delete les cache de la
     * recherche mais l'index unique sur p_sku, lui, les voit toujours — les
     * ignorer ferait échouer la création sur un doublon (cas VBEY20-NX).
     */
    private function findProduct(string $sku): ?Product
    {
        return Product::withTrashed()->where('p_sku', $sku)->first()
            ?? Product::withTrashed()->where('p_code', $sku)->first();
    }

    /**
     * @return array{0: Product, 1: bool} Le produit et s'il vient d'être créé.
     */
    private function resolveProduct(array $line, float $taxRate): array
    {
        if ($product = $this->findProduct($line['sku'])) {
            // Un produit qu'on reçoit à nouveau n'est plus un produit supprimé.
            if ($product->trashed()) {
                $product->restore();
                $this->warn("  ↺ Produit {$line['sku']} restauré (il était supprimé).");
            }
            return [$product, false];
        }

        $category = Category::firstOrCreate(
            ['ctg_code' => 'CAT-001'],
            ['ctg_title' => 'Non catégorisé', 'ctg_status' => true]
        );

        $brand = Brand::firstOrCreate(
            ['br_code' => 'UNKNOWN'],
            ['br_title' => 'Marque inconnue', 'br_status' => true]
        );

        $title = Str::limit($line['designation'], 250, '');

        $product = Product::create([
            'p_title'         => $title,
            'p_code'          => $line['sku'],
            'p_sku'           => $line['sku'],
            'p_description'   => $line['designation'],
            'p_purchasePrice' => $line['pu'],
            'p_salePrice'     => 0,
            'p_cost'          => $line['pu'],
            'p_taxRate'       => $taxRate,
            'p_unit'          => 'pièce',
            'p_status'        => true,
            'p_slug'          => Str::slug(Str::limit($title, 80, '') . '-' . $line['sku']),
            'is_ecom'         => false,
            'category_id'     => $category->id,
            'brand_id'        => $brand->id,
        ]);

        return [$product, true];
    }
}
