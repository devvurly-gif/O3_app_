<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Trésorerie — dépenses & recettes.
 *
 * Le journal de trésorerie a deux sources : les écritures saisies à la main
 * (`cash_transactions`) et les paiements déjà enregistrés sur les documents
 * (`payments`). Les paiements ne sont PAS recopiés ici — les dupliquer ferait
 * diverger les deux tables dès la première suppression. Ils sont rattachés à un
 * compte au moment de la lecture, via `cash_accounts.ca_payment_method`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('ca_title');
            $table->string('ca_code')->nullable()->unique();
            $table->enum('ca_type', ['cash', 'bank', 'cheque', 'other'])->default('cash');
            // Rattache les paiements documents portant ce moyen de règlement à
            // ce compte. Un seul compte par méthode, d'où l'index unique.
            $table->string('ca_payment_method')->nullable()->unique();
            $table->decimal('ca_initial_balance', 15, 2)->default(0);
            $table->boolean('ca_status')->default(true);
            $table->text('ca_notes')->nullable();
            $table->foreignId('structure_id')->nullable()->constrained('structure_incrementors')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cash_categories', function (Blueprint $table) {
            $table->id();
            $table->string('cc_title');
            $table->string('cc_code')->nullable()->unique();
            // 'both' = catégorie utilisable en dépense comme en recette.
            $table->enum('cc_direction', ['in', 'out', 'both'])->default('out');
            $table->string('cc_color', 20)->nullable();
            $table->boolean('cc_status')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cash_recurrences', function (Blueprint $table) {
            $table->id();
            $table->string('cr_label');
            $table->enum('cr_direction', ['in', 'out']);
            $table->decimal('cr_amount', 15, 2);
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->cascadeOnDelete();
            $table->foreignId('cash_category_id')->nullable()->constrained('cash_categories')->nullOnDelete();
            $table->foreignId('thirdPartner_id')->nullable()->constrained('third_partners')->nullOnDelete();
            $table->string('cr_method')->nullable();
            $table->enum('cr_frequency', ['weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            // Jour d'ancrage : jour du mois (1-31) ou jour de la semaine (1-7)
            // selon la fréquence. Un 31 sur un mois court retombe au dernier jour.
            $table->unsignedTinyInteger('cr_anchor_day')->default(1);
            $table->date('cr_start_date');
            $table->date('cr_end_date')->nullable();
            $table->date('cr_next_run_at');
            $table->boolean('cr_status')->default(true);
            $table->text('cr_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('ct_code')->nullable()->unique();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->foreignId('cash_category_id')->nullable()->constrained('cash_categories')->nullOnDelete();
            $table->foreignId('cash_recurrence_id')->nullable()->constrained('cash_recurrences')->nullOnDelete();
            $table->enum('ct_direction', ['in', 'out']);
            $table->decimal('ct_amount', 15, 2);
            $table->date('ct_date');
            $table->string('ct_label');
            $table->string('ct_method')->nullable();
            $table->string('ct_reference')->nullable();
            $table->foreignId('thirdPartner_id')->nullable()->constrained('third_partners')->nullOnDelete();
            // Rattachement facultatif à un document, à titre de simple renvoi :
            // une écriture manuelle ne solde jamais une facture (c'est le rôle
            // de `payments`), elle ne fait que pointer vers elle.
            $table->foreignId('document_header_id')->nullable()->constrained('document_headers')->nullOnDelete();
            // Les deux moitiés d'un virement entre comptes partagent cet uuid.
            $table->uuid('ct_transfer_group')->nullable()->index();
            $table->string('ct_attachment_path')->nullable();
            $table->string('ct_attachment_name')->nullable();
            $table->text('ct_notes')->nullable();
            $table->enum('ct_status', ['active', 'cancelled'])->default('active');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['ct_date', 'ct_direction']);
            $table->index(['cash_account_id', 'ct_date']);
        });

        $this->seedDefaults();
    }

    /**
     * Un module de trésorerie vide n'est pas utilisable : sans compte, aucune
     * écriture n'est saisissable. On amorce donc les comptes correspondant aux
     * moyens de paiement de l'app, des catégories courantes, et le compteur de
     * références — y compris sur les tenants déjà en production.
     */
    private function seedDefaults(): void
    {
        $now = now();

        DB::table('cash_accounts')->insert([
            ['ca_title' => 'Caisse espèces', 'ca_code' => 'TRZ-CAISSE', 'ca_type' => 'cash',   'ca_payment_method' => 'cash',          'ca_status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['ca_title' => 'Banque',         'ca_code' => 'TRZ-BANQUE', 'ca_type' => 'bank',   'ca_payment_method' => 'bank_transfer', 'ca_status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['ca_title' => 'Chèques',        'ca_code' => 'TRZ-CHEQUE', 'ca_type' => 'cheque', 'ca_payment_method' => 'cheque',        'ca_status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['ca_title' => 'Effets',         'ca_code' => 'TRZ-EFFET',  'ca_type' => 'other',  'ca_payment_method' => 'effet',         'ca_status' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $categories = [
            ['Loyer',              'out'], ['Salaires',          'out'],
            ['Carburant',          'out'], ['Transport',         'out'],
            ['Électricité & eau',  'out'], ['Téléphone & internet', 'out'],
            ['Fournitures',        'out'], ['Entretien & réparations', 'out'],
            ['Taxes & impôts',     'out'], ['Frais bancaires',   'out'],
            ['Autres dépenses',    'out'],
            ['Apport',             'in'],  ['Vente diverse',     'in'],
            ['Remboursement',      'in'],  ['Autres recettes',   'in'],
        ];

        $rows = [];
        foreach ($categories as $i => [$title, $direction]) {
            $rows[] = [
                'cc_title'     => $title,
                'cc_code'      => sprintf('TRZC-%03d', $i + 1),
                'cc_direction' => $direction,
                'cc_status'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        DB::table('cash_categories')->insert($rows);

        if (!DB::table('structure_incrementors')->where('si_model', 'CashTransaction')->exists()) {
            DB::table('structure_incrementors')->insert([
                'si_title'     => 'Structure Trésorerie',
                'si_model'     => 'CashTransaction',
                'si_template'  => 'TRZ-{00000}',
                'si_nextTrick' => 1,
                'si_status'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_recurrences');
        Schema::dropIfExists('cash_categories');
        Schema::dropIfExists('cash_accounts');

        DB::table('structure_incrementors')->where('si_model', 'CashTransaction')->delete();
    }
};
