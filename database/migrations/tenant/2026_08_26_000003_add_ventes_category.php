<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le poste « Ventes », pendant en recette d'« Achat Marchandises ».
 *
 * La ventilation par poste range desormais les reglements de documents sous un
 * poste deduit de leur sens : les reglements fournisseurs en achat de
 * marchandise, les encaissements clients en vente. Ce dernier poste n'existait
 * pas — « Vente diverse » designe autre chose, les rentrees ponctuelles hors
 * facturation.
 */
return new class extends Migration {
    private const TITRE = 'Ventes';

    public function up(): void
    {
        if (DB::table('cash_categories')->where('cc_title', self::TITRE)->exists()) {
            return;
        }

        $now = now();

        DB::table('cash_categories')->insert([
            'cc_title'     => self::TITRE,
            'cc_code'      => 'TRZC-017',
            'cc_direction' => 'in',
            'cc_status'    => true,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    public function down(): void
    {
        $utilisee = DB::table('cash_transactions as ct')
            ->join('cash_categories as cc', 'cc.id', '=', 'ct.cash_category_id')
            ->where('cc.cc_title', self::TITRE)
            ->exists();

        if (!$utilisee) {
            DB::table('cash_categories')->where('cc_title', self::TITRE)->delete();
        }
    }
};
