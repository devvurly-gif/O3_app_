<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportJadeverPowerSource extends Command
{
    protected $signature = 'import:jadever-power-source {tenant=jadema : Tenant ID to import into}';
    protected $description = 'Import Jadever "Source d\'energie" (power source) products from jadevermall.com into a single category';

    private array $products = [
        ['sku' => 'JDGNAA170Q', 'title' => 'Gasoline engine', 'description' => 'Engine type:4 stroke,OHV, Max.output:7.0HP, Camshaft diameter:19.05mm,Q type'],
        ['sku' => 'JDGNAA190Q', 'title' => 'Gasoline engine', 'description' => 'Engine type:4 stroke,OHV, Max.output:15.0HP, Camshaft diameter:25.4mm,Q type'],
        ['sku' => 'JDGEAA13', 'title' => 'Gasoline generator', 'description' => 'Rated voltage:220-240V, Rated frequency:50Hz, Max.output:11.0kW Rated output:10.0kW'],
        ['sku' => 'JDBY1A151', 'title' => 'Chargeur de batterie', 'description' => 'Input voltage: 220V Frequency: 50/60Hz, Charging voltage: 12/24V, Rated current:12V 15A / 24V 7.5A'],
        ['sku' => 'JDBY1A201', 'title' => 'Chargeur de batterie', 'description' => 'Input voltage: 220V Frequency: 50/60Hz, Charging voltage: 12/24V, Rated current: 20A/10A'],
        ['sku' => 'JDBD1510', 'title' => 'Battery load tester', 'description' => 'Current range:200-1000CCA, Load current:100A, Suit 6/12V lead-acid battery'],
        ['sku' => 'JDAAC501', 'title' => 'Compresseur D\'Air Auto', 'description' => 'Voltage:12V, Max Pressure:150PSI/10.34Bar, Max air flow:9L/min'],
        ['sku' => 'JDAAC511', 'title' => 'Auto air compressor', 'description' => 'Voltage:12V, Max pressure:160PSI/11Bar, Max air flow:35L/min'],
        ['sku' => 'JDHP1A12', 'title' => 'Nettoyeur haute pression', 'description' => 'Input power: 1200W, Max pressure: 90Bar (1305PSI), Aluminum wire induction motor'],
        ['sku' => 'JDAY1A10', 'title' => 'Pistolet pulvérisateur sans air', 'description' => 'Voltage:220-240V~50/60Hz, Motor rating power:1200W, Max (Fluid) delivery: 1600ml/min'],
        ['sku' => 'JDGNAA1706Q', 'title' => 'Gasoline engine', 'description' => 'Max.output:7.0HP, Camshaft diameter:19.05mm,Q type, Max.torque:13.5N·m'],
        ['sku' => 'JDGNAA1906Q', 'title' => 'Gasoline engine', 'description' => 'Max.output:15.0HP, Camshaft diameter:25.4mm,Q type, Max.torque:26N·m'],
        ['sku' => 'JDGEAA056', 'title' => 'Gasoline generator', 'description' => 'Rated voltage:220-240V, Rated frequency: 50Hz, Max.output: 2.8kW Rated output: 2.5kW'],
        ['sku' => 'JDGEAA056D', 'title' => 'Gasoline generator', 'description' => 'Rated voltage(V):220-240, Rated frequency(Hz):50, Max output(kW):2.8 Rated output(kW):2.5'],
        ['sku' => 'JDGEAA09', 'title' => 'Générateur d\'essence', 'description' => 'Rated voltage: 220-240V~50Hz, Max.output: 7.0kW, Rated output: 6.0kW'],
        ['sku' => 'JDGEAA106', 'title' => 'Gasoline generator', 'description' => 'Rated voltage:220-240V~50Hz, Max.output:8.5kW, Rated output:7.5kW'],
        ['sku' => 'JDGEAA116', 'title' => 'Gasoline generator', 'description' => 'Rated voltage:220-240V, Rated frequency:50Hz, Max.output:9.0kW Rated output:8.5kW'],
        ['sku' => 'JDGEAB06', 'title' => 'Inverter gasoline generator', 'description' => 'Rated voltage: 220-240V~50Hz, Max.output: 3.8kW Rated output: 3.5kW, Pure sine wave output(fluctuation±3%)'],
        ['sku' => 'JDGW1A21', 'title' => 'Pompe à eau à essence', 'description' => 'Diamètre du port d\'aspiration et de refoulement : 50 mm, 2 pouces, Débit max : 550 L/min, Max.head lift:30m'],
        ['sku' => 'JDGW1A31', 'title' => 'Pompe à eau à essence', 'description' => 'Diamètre du port d\'aspiration et de refoulement : 80mm,3", Débit max : 1000 L/min, Max.head lift:27m'],
        ['sku' => 'JDDG2A50', 'title' => 'Groupe électrogène diesel insonnorisé', 'description' => 'Tension nominale : 220-240V~50Hz, Max.output: 5.0kW, Puissance nominale: 4,5 kW'],
        ['sku' => 'JDDG2A50DT', 'title' => 'Silent diesel generator', 'description' => 'Rated voltage: 220-240/380-415V~50Hz, Phase:three, Max.output: 6.3kVA'],
        ['sku' => 'JDDG2A80DT', 'title' => 'Silent diesel generator', 'description' => 'Rated voltage: 220-240/380-415V~50Hz, Phase: three, Max.output: 8.0kVA'],
        ['sku' => 'JDDW1A30', 'title' => 'Pompe à eau diesel', 'description' => 'Diamètre du port d\'aspiration et de refoulement : 80mm,3", Débit max : 900 L/min, Max.head lift: 30m'],
        ['sku' => 'JDDW1A40', 'title' => 'Pompe à eau diesel', 'description' => 'Diamètre du port d\'aspiration et de refoulement : 100 mm, 4 pouces, Débit max : 1600 L/min, Max.head lift: 30m'],
        ['sku' => 'JDWD11601', 'title' => 'Poste À Souder Onduleur MMA', 'description' => 'Tension d\'entrée: 1~220-240V Fréquence: 50/60Hz, Courant de sortie : 10-160A, Cycle de service : 160A@30%'],
        ['sku' => 'JDWD32001', 'title' => 'Poste À Souder Onduleur MMA', 'description' => 'Tension d\'entrée: 1~220-240V Fréquence: 50/60Hz, Courant de sortie : 20-200A, Cycle de service : 200A@60%'],
        ['sku' => 'JDEH1A03', 'title' => 'Porte-électrode', 'description' => 'Rated current:300A, Length: 230mm, Suitable for MMA welding machine'],
        ['sku' => 'JDEH1A06', 'title' => 'Porte-électrode', 'description' => 'Rated current:600A, Length: 250mm, Suitable for MMA welding machine'],
        ['sku' => 'JDEH9A03', 'title' => 'Pince De Terre À Souder', 'description' => 'Rated current:300 A, Copper jaws, chromed steel handle'],
        ['sku' => 'JDEH9A05', 'title' => 'Pince De Terre À Souder', 'description' => 'Rated current:500 A, Copper jaws, Chromed steel handle'],
        ['sku' => 'JDWPVA01', 'title' => 'Pompe périphérique', 'description' => 'Voltage:220-240V~50Hz, Rated power:370W(0.5HP), Max.head:30m Max.flow:30L/min'],
        ['sku' => 'JDWPVA03', 'title' => 'Pompe périphérique', 'description' => 'Tension:220-240V~50Hz, Puissance nominale: 750W(1.0HP), Max.head:50m Max.flow:46L/min'],
        ['sku' => 'JDWPCA03', 'title' => 'Pompe périphérique', 'description' => 'Tension:220-240V~50Hz, Puissance nominale: 750W(1.0HP), Max.head:28m Max.flow:100L/min'],
        ['sku' => 'JDWPJA03', 'title' => 'Pompe périphérique', 'description' => 'Tension:220-240V~50Hz, Puissance nominale: 750W(1.0HP), Max.head:45m Max.flow:50L/min'],
        ['sku' => 'JDWPJA04', 'title' => 'Pompe périphérique', 'description' => 'Tension:220-240V~50Hz, Puissance nominale: 1100W (1.5HP), Max.head:55m Max.flow:63L/min'],
        ['sku' => 'JDWPJA05', 'title' => 'Pompe périphérique', 'description' => 'Tension:220-240V~50Hz, Puissance nominale: 1500W(2.0HP), Max.head:60m Max.flow:100L/min'],
        ['sku' => 'JDPC1A01', 'title' => 'Commande de pompes automatique', 'description' => 'Tension nominale: 220-240V, Fréquence:50/60Hz, Pression de démarrage:1.5bar Courant maximal:10A'],
        ['sku' => 'JDPC1A03', 'title' => 'Automatic pump control', 'description' => 'Rated voltage:220-240V, Frequency:50/60Hz, Staring pressure:1.5bar Max.current:10A'],
        ['sku' => 'JDWPWA03', 'title' => 'Pompe submersible pour eaux usées', 'description' => 'Tension:220-240V~50Hz, Puissance nominale: 750W(1.0HP), Max.head:13m Max. flow:300L/min'],
        ['sku' => 'JDWPWA05', 'title' => 'Pompe submersible pour eaux usées', 'description' => 'Voltage:220-240V~50Hz, Rated power:1500W(2.0HP), Max.head:14.5m Max. flow:350L/min'],
        ['sku' => 'JDWPQDC24', 'title' => 'Pompe Submersible Dc', 'description' => 'Voltage: 24V DC, Rated power: 240W(0.3HP), Max. head: 13m Max. flow: 67L/min'],
        ['sku' => 'JDHP3A12', 'title' => 'Nettoyeur haute pression', 'description' => 'Voltage: 220-240V~50/60Hz, Input power: 1200W, Max Pressure: 90Bar (1305PSI)'],
        ['sku' => 'JDHP3A18', 'title' => 'Nettoyeur haute pression', 'description' => 'Voltage: 220-240V~50/60Hz, Max pressure: 130Bar (1885PSI), Input power: 1800W'],
        ['sku' => 'JDHP3A22', 'title' => 'Nettoyeur haute pression', 'description' => 'Tension: 220-240V~50/60Hz, Puissance d\'entrée: 2200W, Pression maximale: 160Bar (2320PSI)'],
        ['sku' => 'JDGPS1A28', 'title' => 'Gasoline pressure washer', 'description' => 'Max Output: 7.0HP, Max. Pressure: 214Bar (3100psi), Flow Rate: 8.7L/min (2.3GPM)'],
        ['sku' => 'JDGPS2A32', 'title' => 'Gasoline pressure washer', 'description' => 'Pump Type: AxialPump, Max Output: 7.0HP, Max. Pressure: 242Bar (3500PSI)'],
        ['sku' => 'JDDPS1A36', 'title' => 'Nettoyeur haute pression diesel', 'description' => 'Max Output:9.0HP, Max. Pressure:250Bar (3600psi), Displacement :418cc'],
        ['sku' => 'JDAP1A06', 'title' => 'Compresseur d\'Air', 'description' => 'Voltage:220-240V~50Hz, Input power:1100W(1.5HP), Tank:6L(1.6Gal)'],
        ['sku' => 'JDAP1A24', 'title' => 'Compresseur d\'Air', 'description' => 'Voltage:220-240V~50Hz, Input power:1100W(1.5HP), Tank:24L(6.3Gal)'],
        ['sku' => 'JDAP1A25', 'title' => 'Compresseur d\'Air', 'description' => 'Tension:220-240V~50Hz, Puissance d\'entrée: 1200W （1.6HP）, Fil de cuivre en aluminium'],
        ['sku' => 'JDAP2A45', 'title' => 'Compresseur d\'Air', 'description' => 'Tension:220-240V~50Hz, Puissance d\'entrée: 2×1200W （3.2HP）, Réservoir: 50L(13.2Gal)'],
        ['sku' => 'JDAP2R41', 'title' => 'Compresseur d\'Air', 'description' => 'Tension:220-240V~50Hz, Puissance d\'entrée: 2×1200W （3.2HP）, Réservoir: 100L(26.4Gal)'],
        ['sku' => 'JDAP2R81', 'title' => 'Compresseur d\'Air', 'description' => 'Voltage:220-240V~50Hz, Input power:4×1200W(6.4HP）, Tank:150L(40Gal)'],
        ['sku' => 'JDAP3A50', 'title' => 'Compresseur d\'Air', 'description' => 'Voltage: 220-240V~50Hz, Input power: 1.8kW(2.5HP), Tank: 50L(13.2Gal)'],
        ['sku' => 'JDAP4A15', 'title' => 'Compresseur d\'Air', 'description' => 'Voltage:220-240V~50Hz, Input power: 1.5kW (2HP), Tank:50L(13.2Gal)'],
        ['sku' => 'JDAP4R11', 'title' => 'Compresseur d\'Air', 'description' => 'Voltage:220-240V~50Hz, Input power: 2.2kW(3HP), Tank:100L(26.4Gal)'],
        ['sku' => 'JDAP4R22', 'title' => 'Compresseur d\'Air', 'description' => 'Voltage:220-240V~50Hz, Phase:Single, Input power: 3.0kW (4HP)'],
        ['sku' => 'JDAP4R33', 'title' => 'Compresseur d\'Air', 'description' => 'Voltage:380V~50Hz, Phase:Three, Input power: 4.1kW (5.5HP)'],
        ['sku' => 'JDAP4R35', 'title' => 'Compresseur d\'Air', 'description' => 'Voltage:380V~50Hz, Phase:Three, Input power: 5.5kW (7.5HP)'],
        ['sku' => 'JDGA1510', 'title' => 'Pistolet à peinture pneumatique', 'description' => 'Buse standard : 1,5 mm, Largeur du motif : 180-250mm, Capacité de peinture : 1000cc'],
        ['sku' => 'JDGA1561', 'title' => 'Pistolet à peinture pneumatique', 'description' => 'Standard nozzle:1.5mm, Paint capacity:1000cc, Suitable for base coat'],
        ['sku' => 'JDGA1504', 'title' => 'Pistolet à peinture pneumatique', 'description' => 'Buse standard : 1,5 mm, Largeur du motif : 180-250mm, Capacité de peinture : 600cc'],
        ['sku' => 'JDAT1512', 'title' => 'Clé À Choc Pneumatique', 'description' => 'Square drive: 12.5mm(1/2"), No-load speed: 7500/min, Max.torque: 610Nm(450Ft-lb)'],
        ['sku' => 'JDMS2D02', 'title' => '10 Pcs 1/2" jeu de douilles a choc profonde', 'description' => 'Taille: 10.12.13.14.15.17.19.21.22.24mm, longueur:78mm, Matériau: Cr-V'],
        ['sku' => 'JDBN2540', 'title' => 'Brad cloueuse 2 en 1 combo', 'description' => 'Ga18, Opération pression:0.4-0.7Mpa(60-100Psi), Capacité de charge:100Pcs'],
        ['sku' => 'JDBN1550', 'title' => 'Cloueuse à air comprimé', 'description' => 'Ga18, Opération pression:0.4-0.7Mpa(60-100Psi), Capacité de charge:100pcs'],
        ['sku' => 'JDGC1552', 'title' => 'Scie à chaîne à essence', 'description' => 'Displacement:46cc, Rated power:1.8kW, Max. cutting diameter:445mm(18")'],
        ['sku' => 'JDGC2558', 'title' => 'Gasoline chain saw', 'description' => 'Displacement: 58cc, Rated power: 2.4kW, Engine idle speed: 3100±300rpm'],
        ['sku' => 'JDGM2543', 'title' => 'Coupe-herbe et débroussailleuse à essence', 'description' => 'Déplacement:43cc, Puissance nominale: 1,25 kW (2 ch), Vitesse maximale : 8000 tr/min'],
        ['sku' => 'JDGM3A18', 'title' => 'Gasoline lawn mower', 'description' => 'Displacement: 141cc, Cutting width: 460mm(18”), Rated power: 2.5kW(3.5HP)'],
        ['sku' => 'JDYQ1A25', 'title' => 'Gasoline blower', 'description' => 'Displacement: 25.4cc, Rated power: 0.75kW, Engine idle speed: 7500r/min'],
        ['sku' => 'JDYQ3A25', 'title' => 'Gasoline blower', 'description' => 'Displacement: 25.4cc, Rated power:0.75KW, Engine idle speed:7500r/min'],
        ['sku' => 'JDKN1A41', 'title' => 'Brumisateur', 'description' => 'Discharging Capacity: 41.5cc, Chemical tank:14L, Fuel tank:1.4L'],
        ['sku' => 'JDKN3A25', 'title' => 'Gasoline knapsack sprayer', 'description' => 'Displacement: 25.4cc, Rated power: 0.75kW, Chemical tank capacity: 25L'],
        ['sku' => 'JDTL2A75', 'title' => 'Gasoline tiller', 'description' => 'Petrol engine power: 170F(7.0HP), Tilling Scope: 540-880mm, Driving model: Belt'],
        ['sku' => 'JDTL2A1001', 'title' => 'Gasoline tiller', 'description' => 'Petrol engine power: 170F(7.0HP), Tilling Scope: 510-1000mm, Driving model: Gear'],
        ['sku' => 'JDTL2A100', 'title' => 'Gasoline tiller', 'description' => 'Petrol engine power: 170F(7.0HP), Tilling Scope: 510-1000mm, Driving model: Gear'],
        ['sku' => 'JDTL3A1001', 'title' => 'Diesel tiller', 'description' => 'Diesel engine power: 173F(5.2HP), Tilling Scope: 510-1000mm, Driving model:Gear'],
        ['sku' => 'JDTL3A100', 'title' => 'Diesel tiller', 'description' => 'Diesel engine power: 173F(5.2HP), Tilling Scope: 510-1000mm, Driving model:Gear'],
        ['sku' => 'JDTL3A1351', 'title' => 'Diesel tiller', 'description' => 'Diesel engine power: 186FA(9HP), Tilling Scope: 1060-1350mm, Driving model:Gear'],
        ['sku' => 'JDTL3A135', 'title' => 'Diesel tiller', 'description' => 'Diesel engine power: 186FA(9HP), Tilling Scope: 1060-1350mm, Driving model:Gear'],
        ['sku' => 'JDTL3A1351D', 'title' => 'Diesel tiller', 'description' => 'Diesel engine power: 186FA(9HP), Tilling Scope: 1060-1350mm, Driving model:Gear'],
        ['sku' => 'JDTL3A135D', 'title' => 'Diesel tiller', 'description' => 'Diesel engine power: 186FA(9HP), Tilling Scope: 1060-1350mm, Driving model:Gear'],
        ['sku' => 'JDTLPKA15', 'title' => 'Échantillon de fossé (Sample Ditcher)', 'description' => 'Trenching width: ≤ 300mm, Trenching depth: 100-200mm'],
        ['sku' => 'JDTDR7A30', 'title' => 'Bande-annonce (Remorque)', 'description' => 'Load: ≤300kg, Suspension type: leaf spring, Net weight: 95kg'],
        ['sku' => 'JDVR4A30', 'title' => 'Aspirateur', 'description' => 'Voltage: 220V-240V~50/60Hz, Input power: 1200W, Dust capacity: 30L'],
        ['sku' => 'JDVR5A60', 'title' => 'Aspirateur', 'description' => 'Voltage: 220V-240V~50/60Hz, Input power: 2X1200W, Dust capacity:60L'],
        ['sku' => 'JDVR5A90', 'title' => 'Aspirateur', 'description' => 'Voltage: 220V-240V~50/60Hz, Input power: 3X1200W, Dust capacity:90L'],
        ['sku' => 'JDTA1A80', 'title' => 'Essence Pilon (Dame sauteuse à essence)', 'description' => 'Puissance du moteur: 4,8 kW (6,5 ch), Poids:79kg, Force d\'impact: 10kN'],
        ['sku' => 'JDPM3A090', 'title' => 'Gasoline plate compactor', 'description' => 'Gasoline engine, Engine power:4.8kW/6.5HP, Weight:90KGS'],
        ['sku' => 'JDPM2A100', 'title' => 'Compacteur À Plaques À Essence', 'description' => 'Moteur à essence, Puissance du moteur: 4,8 kW (6,5 ch), Poids: 90kg'],
        ['sku' => 'JDPA2A160', 'title' => 'Compacteur À Plaque Réversible À Essence', 'description' => 'Moteur à essence, Puissance du moteur: 6.0kW (9.0HP), Poids : 160kg'],
        ['sku' => 'JDQF2A16', 'title' => 'Scie À Sol À Essence', 'description' => 'Moteur à essence, Puissance du moteur : 9,6 kW (13 ch), Poids:120kg'],
        ['sku' => 'JDQM1A36', 'title' => 'Essence Puissance Truelle (Truelle mécanique à essence)', 'description' => 'Puissance du moteur: 4,8 kW (6,5 ch), Poids:78kg, Diamètre du rotor: 91cm (36")'],
        ['sku' => 'JDQMD1A30', 'title' => 'Gasoline power trowel', 'description' => 'Engine:Honda GX390, Engine power:8.2kW/13HP, Weight: 250kg'],
        ['sku' => 'JDGB2A22', 'title' => 'Gasoline concrete vibrator (Hex type)', 'description' => 'Gasoline engine, Engine power:4.0kW(5.5HP), HEX type coupling'],
        ['sku' => 'JDGB2A12', 'title' => 'Gasoline concrete vibrator (Hex type)', 'description' => 'Moteur à essence, Puissance du moteur: 4.0kW (5.5HP), HEX type coupling'],
        ['sku' => 'JDECV2A15', 'title' => 'Moteur vibrateur électrique (type HEX)', 'description' => 'Voltage:220-240V~50Hz, Input power:1500W(2HP), HEX type coupling'],
    ];

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return 1;
        }

        $this->info('Importing ' . count($this->products) . " Jadever power-source products into tenant '{$tenant->name}'...");

        $tenant->run(function () {
            $brand = Brand::firstOrCreate(
                ['br_code' => 'JADEVER'],
                ['br_title' => 'JADEVER', 'br_status' => true]
            );
            $this->info("Brand: JADEVER (ID: {$brand->id})");

            $category = Category::firstOrCreate(
                ['ctg_code' => 'POWER_SOURCE'],
                ['ctg_title' => "Source d'énergie", 'ctg_status' => true]
            );
            $this->info("Category: Source d'énergie (ID: {$category->id})");

            $imported = 0;
            $skipped = 0;

            foreach ($this->products as $item) {
                if (Product::where('p_sku', $item['sku'])->exists()) {
                    $this->warn("  SKIP: {$item['sku']} already exists.");
                    $skipped++;
                    continue;
                }

                Product::create([
                    'p_title'         => $item['title'],
                    'p_code'          => $item['sku'],
                    'p_sku'           => $item['sku'],
                    'p_description'   => $item['description'],
                    'p_salePrice'     => 0,
                    'p_purchasePrice' => 0,
                    'p_cost'          => 0,
                    'p_taxRate'       => 20,
                    'p_unit'          => 'pièce',
                    'p_status'        => true,
                    'p_slug'          => Str::slug($item['title'] . '-' . $item['sku']),
                    'is_ecom'         => false,
                    'category_id'     => $category->id,
                    'brand_id'        => $brand->id,
                ]);

                $this->info("  OK: {$item['sku']} — {$item['title']}");
                $imported++;
            }

            $this->newLine();
            $this->info("Done! Imported: {$imported}, Skipped: {$skipped}");
        });

        return 0;
    }
}
