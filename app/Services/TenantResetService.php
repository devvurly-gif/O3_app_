<?php

namespace App\Services;

use App\Models\DocumentIncrementor;
use App\Models\ThirdPartner;
use App\Models\WarehouseHasStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantResetService
{
    /**
     * Wipe all transactional data (sales, purchases, payments, stock
     * movements, POS sessions, import/messaging journals) for the CURRENT
     * tenant DB connection and reset stock levels / document numbering to
     * a clean slate.
     *
     * Catalog/master data (products, categories, warehouses, users,
     * third parties, settings) is intentionally left untouched.
     *
     * Deletes go through the query builder (not Eloquent) so this
     * doesn't fire per-row model events (payment notifications,
     * activity log writes) for what could be thousands of rows.
     * Foreign key checks are disabled for the duration since
     * document_headers.parent_id self-references the same table
     * being wiped in one statement.
     *
     * The journals that point at documents go too: purchase_imports and
     * whatsapp_order_imports are idempotence registers keyed by external_id,
     * so a surviving "created" row would answer "already_imported" for a
     * document that no longer exists; order_messages is the messaging thread
     * whose document_id links would dangle. customer_chat_codes/sessions are
     * kept: they are short-lived customer auth state, not linked to documents.
     */
    public function reset(?int $userId): array
    {
        $tables = [
            'purchase_imports',
            'whatsapp_order_imports',
            'order_messages',
            'payments',
            'document_lignes',
            'document_footers',
            'stock_mouvements',
            'document_headers',
            'pos_sessions',
        ];

        $summary = [];

        DB::transaction(function () use ($tables, &$summary) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                foreach ($tables as $table) {
                    $summary[$table] = DB::table($table)->count();
                    DB::table($table)->delete();
                }

                DB::table('activity_log')
                    ->whereIn('subject_type', [
                        \App\Models\DocumentHeader::class,
                        \App\Models\DocumentLigne::class,
                        \App\Models\DocumentFooter::class,
                        \App\Models\Payment::class,
                        \App\Models\StockMouvement::class,
                        \App\Models\PosSession::class,
                    ])
                    ->delete();

                $summary['warehouse_has_stock_reset'] = WarehouseHasStock::query()->count();
                WarehouseHasStock::query()->update([
                    'stockLevel'  => 0,
                    'wh_average'  => 0,
                    'stockAtTime' => now(),
                ]);

                DocumentIncrementor::query()->update(['nextTrick' => 1]);

                $summary['third_partners_encours_reset'] = ThirdPartner::query()->count();
                ThirdPartner::query()->update(['encours_actuel' => 0]);
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        Log::warning('Tenant data reset executed', [
            'tenant_id' => tenant('id'),
            'user_id'   => $userId,
            'summary'   => $summary,
        ]);

        return $summary;
    }
}
