<?php

namespace Database\Seeders;

use App\Models\StructureIncrementor;
use App\Models\ThirdPartner;
use Illuminate\Database\Seeder;

class ThirdPartnerSeeder extends Seeder
{
    public function run(): void
    {
        $structure = StructureIncrementor::where('si_model', 'ThirdPartner')->first();

        $partners = [
            // Customers
            [
                'tp_title'          => 'Société Alpha SARL',
                'tp_Ice_Number'     => '001234567890123',
                'tp_Rc_Number'      => 'RC-12345',
                'tp_patente_Number' => 'PAT-12345',
                'tp_IdenFiscal'     => 'IF-12345',
                'tp_Role'           => 'customer',
                'tp_status'         => true,
                'tp_phone'          => '+212522123456',
                'tp_email'          => 'contact@alpha.ma',
                'tp_address'        => '10 Rue Mohammed V',
                'tp_city'           => 'Casablanca',
            ],
            [
                'tp_title'          => 'Beta Tech SA',
                'tp_Ice_Number'     => '002345678901234',
                'tp_Rc_Number'      => 'RC-23456',
                'tp_patente_Number' => 'PAT-23456',
                'tp_IdenFiscal'     => 'IF-23456',
                'tp_Role'           => 'customer',
                'tp_status'         => true,
                'tp_phone'          => '+212537234567',
                'tp_email'          => 'info@betatech.ma',
                'tp_address'        => '5 Avenue Hassan II',
                'tp_city'           => 'Rabat',
            ],
            [
                'tp_title'          => 'Gamma Distribution',
                'tp_Ice_Number'     => '003456789012345',
                'tp_Rc_Number'      => 'RC-34567',
                'tp_patente_Number' => 'PAT-34567',
                'tp_IdenFiscal'     => 'IF-34567',
                'tp_Role'           => 'customer',
                'tp_status'         => true,
                'tp_phone'          => '+212535345678',
                'tp_email'          => 'gamma@gamma.ma',
                'tp_address'        => '20 Boulevard Zerktouni',
                'tp_city'           => 'Fès',
            ],

            // Suppliers
            [
                'tp_title'          => 'Delta Import SARL',
                'tp_Ice_Number'     => '004567890123456',
                'tp_Rc_Number'      => 'RC-45678',
                'tp_patente_Number' => 'PAT-45678',
                'tp_IdenFiscal'     => 'IF-45678',
                'tp_Role'           => 'supplier',
                'tp_status'         => true,
                'tp_phone'          => '+212522456789',
                'tp_email'          => 'achats@delta.ma',
                'tp_address'        => '15 Rue Allal Ben Abdellah',
                'tp_city'           => 'Casablanca',
            ],
            [
                'tp_title'          => 'Epsilon Electronics',
                'tp_Ice_Number'     => '005678901234567',
                'tp_Rc_Number'      => 'RC-56789',
                'tp_patente_Number' => 'PAT-56789',
                'tp_IdenFiscal'     => 'IF-56789',
                'tp_Role'           => 'supplier',
                'tp_status'         => true,
                'tp_phone'          => '+212537567890',
                'tp_email'          => 'supply@epsilon.ma',
                'tp_address'        => '8 Rue Ibn Batouta',
                'tp_city'           => 'Tanger',
            ],

            // Both
            [
                'tp_title'          => 'Zeta Trading',
                'tp_Ice_Number'     => '006789012345678',
                'tp_Rc_Number'      => 'RC-67890',
                'tp_patente_Number' => 'PAT-67890',
                'tp_IdenFiscal'     => 'IF-67890',
                'tp_Role'           => 'both',
                'tp_status'         => true,
                'tp_phone'          => '+212528678901',
                'tp_email'          => 'trading@zeta.ma',
                'tp_address'        => '30 Rue Oued Ziz',
                'tp_city'           => 'Marrakech',
            ],
        ];

        foreach ($partners as $partner) {
            $exists = ThirdPartner::where('tp_Ice_Number', $partner['tp_Ice_Number'])->exists();

            if (! $exists) {
                ThirdPartner::create(array_merge($partner, [
                    'tp_code'      => $structure?->generateCode(),
                    'structure_id' => $structure?->id,
                ]));

                $structure?->refresh();
            }
        }

        // ── Client Comptoir (walk-in / POS default customer) ────────
        if (! ThirdPartner::where('tp_code', 'CLIENT-COMPTOIR')->exists()) {
            ThirdPartner::create([
                'tp_title'        => 'Client Comptoir',
                'tp_code'         => 'CLIENT-COMPTOIR',
                'tp_Role'         => 'customer',
                'tp_status'       => true,
                'type_compte'     => 'normal',
                'encours_actuel'  => 0,
                'seuil_credit'    => 0,
                'structure_id'    => $structure?->id,
            ]);
        }
    }
}
