<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le poste « Achat Marchandises » aux catégories de trésorerie.
 *
 * Les postes amorcés à la création du module couvraient les frais de structure
 * — loyer, salaires, carburant — mais pas la dépense principale d'un négoce :
 * la marchandise elle-même. Un règlement fournisseur saisi à la main n'avait
 * donc aucun poste où atterrir.
 *
 * Le poste est ajouté aux tenants existants comme aux futurs : une migration
 * s'exécute sur les deux, là où modifier la liste d'amorçage d'origine
 * n'aurait servi que les nouveaux.
 */
return new class extends Migration {
    private const TITRE = 'Achat Marchandises';

    public function up(): void
    {
        if (DB::table('cash_categories')->where('cc_title', self::TITRE)->exists()) {
            return;
        }

        $now = now();

        DB::table('cash_categories')->insert([
            'cc_title'     => self::TITRE,
            'cc_code'      => 'TRZC-016',
            'cc_direction' => 'out',
            'cc_status'    => true,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    public function down(): void
    {
        // Une catégorie déjà utilisée par des écritures ne se retire pas :
        // ses lignes perdraient leur poste sans que personne ne le voie.
        $utilisee = DB::table('cash_transactions as ct')
            ->join('cash_categories as cc', 'cc.id', '=', 'ct.cash_category_id')
            ->where('cc.cc_title', self::TITRE)
            ->exists();

        if (!$utilisee) {
            DB::table('cash_categories')->where('cc_title', self::TITRE)->delete();
        }
    }
};
