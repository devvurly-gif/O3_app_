<?php

namespace Tests\Feature\Api;

use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Services\Agents\BankStatementParser;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Import d'un relevé bancaire : lu localement (Excel / CSV) ou par l'IA après confirmation (PDF) ; seuls les crédits dont
 * le client et le montant exact correspondent à une facture sont proposés ; rien n'est enregistré avant le clic ; aucun
 * message au client ; un relevé déjà importé ne compte pas deux fois.
 */
class OrchestratorBankStatementTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private ThirdPartner $atlas;
    private ThirdPartner $bati;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->atlas = ThirdPartner::factory()->create(['tp_title' => 'Quincaillerie Atlas', 'tp_Role' => 'customer']);
        $this->bati = ThirdPartner::factory()->create(['tp_title' => 'Bati Plus', 'tp_Role' => 'customer']);
    }

    private function invoice(string $ref, float $due, ThirdPartner $tp, string $type = 'InvoiceSale'): DocumentHeader
    {
        $d = DocumentHeader::factory()->create(['document_type' => $type, 'status' => 'confirmed', 'issued_at' => '2026-09-01', 'reference' => $ref, 'thirdPartner_id' => $tp->id]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round($due / 1.2, 2), 'total_ttc' => $due, 'amount_paid' => 0, 'amount_due' => $due]);

        return $d;
    }

    private function deposit(UploadedFile $file, string $message = ''): array
    {
        $r = $this->actingAs($this->admin, 'sanctum')->post('/api/agents/orchestrateur/fichiers', ['message' => $message, 'files' => [$file]], ['Accept' => 'application/json'])->assertCreated()->json('reply');

        return $r;
    }

    private function say(string $text): array
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    private function csv(string $content, string $name = 'releve.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private const STATEMENT = "Relevé de compte;;;;\nDate opération;Date valeur;Libellé;Débit;Crédit\n05/10/2026;05/10/2026;VIR RECU QUINCAILLERIE ATLAS FV-1;;1 500,00\n06/10/2026;06/10/2026;PRELEVEMENT LOYER;3 000,00;\n07/10/2026;07/10/2026;VIR RECU BATI REGLEMENT;;2.000,50\n08/10/2026;08/10/2026;VIR RECU DIVERS;;777,00\n09/10/2026;09/10/2026;Total des mouvements;3 000,00;3 500,50\n";

    // ── Lecture locale ───────────────────────────────────────────────

    public function test_the_parser_reads_headers_separators_numbers_and_dates_strictly(): void
    {
        $p = new BankStatementParser();
        $this->assertSame(1234.56, $p->number('1 234,56'));
        $this->assertSame(1234.56, $p->number('1.234,56'));
        $this->assertSame(1234.56, $p->number('1,234.56'));
        $this->assertSame(1234.5, $p->number('1234.5'));
        $this->assertSame(1234.0, $p->number('1.234'));
        $this->assertSame(-250.0, $p->number('-250'));
        $this->assertNull($p->number('abc'));

        $dir = sys_get_temp_dir() . '/stmt-' . uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/a.csv", mb_convert_encoding(self::STATEMENT, 'ISO-8859-1', 'UTF-8'));      // accents Latin-1
        $r = $p->parse("{$dir}/a.csv", 'csv');
        $this->assertCount(4, $r['lines']);                                                                  // le « total » sans date valide est écarté
        $this->assertSame(1, $r['ignored']);
        $this->assertSame(['date' => '2026-10-05', 'label' => 'VIR RECU QUINCAILLERIE ATLAS FV-1', 'credit' => 1500.0, 'debit' => 0.0], $r['lines'][0]);
        $this->assertSame(3000.0, $r['lines'][1]['debit']);
        $this->assertSame(2000.5, $r['lines'][2]['credit']);

        file_put_contents("{$dir}/b.csv", "date,libelle,montant\n2026-10-05,Virement A,1200.00\n2026-10-06,Frais,-35.00\n");
        $b = $p->parse("{$dir}/b.csv", 'csv');
        $this->assertSame([1200.0, 0.0], [$b['lines'][0]['credit'], $b['lines'][0]['debit']]);
        $this->assertSame(35.0, $b['lines'][1]['debit']);

        file_put_contents("{$dir}/c.csv", "nom;prix\nx;1\n");
        $this->assertNull($p->parse("{$dir}/c.csv", 'csv'));
        $this->assertStringContainsString("en-têtes", (string) $p->failure());
    }

    public function test_an_excel_statement_is_read_without_any_http_call(): void
    {
        $this->invoice('FV-1', 1500, $this->atlas);
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->fromArray([['Date', 'Libellé', 'Débit', 'Crédit'], ['05/10/2026', 'VIR ATLAS', null, 1500], ['06/10/2026', 'FRAIS', 12.5, null]], null, 'A1');
        $path = sys_get_temp_dir() . '/stmt-' . uniqid() . '.xlsx';
        (new Xlsx($sheet))->save($path);

        $r = $this->deposit(UploadedFile::fake()->createWithContent('releve.xlsx', (string) file_get_contents($path)));

        $this->assertStringContainsString("2 ligne(s) lue(s), 1 crédit(s), 1 débit(s) (dont 1 sans fournisseur ni facture d'achat reconnus, laissés de côté)", $r['body']);
        $this->assertStringContainsString('FV-1 (Quincaillerie Atlas)', $r['body']);
        Http::assertNothingSent();
    }

    // ── Rapprochement et enregistrement ──────────────────────────────

    public function test_only_certain_credits_become_proposals_and_nothing_is_recorded_before_the_click(): void
    {
        $this->invoice('FV-1', 1500, $this->atlas);
        $this->invoice('FV-2', 2000.5, $this->bati);
        $this->invoice('FV-3', 777, $this->atlas);                                       // le libellé « DIVERS » ne dit pas le client
        $this->invoice('FV-4', 777, $this->bati);                                        // … et deux factures ont ce montant : ambigu

        $r = $this->deposit($this->csv(self::STATEMENT));

        $this->assertStringContainsString("4 ligne(s) lue(s), 3 crédit(s), 1 débit(s) (dont 1 sans fournisseur ni facture d'achat reconnus, laissés de côté), 1 ligne(s) illisible(s) écartée(s)", $r['body']);
        $this->assertStringContainsString('2 règlement(s) reconnu(s) avec certitude', $r['body']);
        $this->assertStringContainsString('05/10/2026 — +1 500,00 MAD — FV-1 (Quincaillerie Atlas) — reconnu par le tiers et le montant', $r['body']);
        $this->assertStringContainsString('07/10/2026 — +2 000,50 MAD — FV-2 (Bati Plus)', $r['body']);
        $this->assertStringContainsString('À traiter à la main (1)', $r['body']);
        $this->assertStringContainsString('plusieurs factures de ce montant', $r['body']);
        $this->assertSame('Enregistrer ces règlements', $r['meta']['suggestions'][0]['label'] ?? $r['suggestions'][0]['label']);
        $this->assertSame(0, Payment::count());
        $this->assertFalse($r['ai']);                                                    // lu localement : l'étiquette « compris par IA » ne doit pas apparaître
        Http::assertNothingSent();
    }

    public function test_the_click_records_dated_payments_without_notifying_clients_and_a_second_import_skips_them(): void
    {
        $this->mock(\App\Services\PaymentNotificationService::class)->shouldNotReceive('send');
        $a = $this->invoice('FV-1', 1500, $this->atlas);
        $this->invoice('FV-2', 2000.5, $this->bati);

        $r = $this->deposit($this->csv(self::STATEMENT));
        $done = $this->say($r['suggestions'][0]['text']);

        $this->assertStringContainsString('2 règlement(s) enregistré(s)', $done['body']);
        $p = Payment::where('document_header_id', $a->id)->firstOrFail();
        $this->assertSame('2026-10-05', $p->paid_at->toDateString());                    // la date de l'opération bancaire
        $this->assertSame('bank_transfer', $p->method);
        $this->assertSame($this->admin->id, $p->user_id);
        $this->assertSame(0.0, (float) DocumentFooter::where('document_header_id', $a->id)->value('amount_due'));
        $this->assertSame('bank_statement_applied', AgentAction::first()->action);

        $again = $this->deposit($this->csv(self::STATEMENT));
        $this->assertStringContainsString('2 déjà importée(s)', $again['body']);
        $this->assertStringContainsString('Aucune ligne ne correspond avec certitude', $again['body']);
        $this->assertSame(2, Payment::count());
    }

    public function test_a_line_whose_invoice_changed_is_skipped_and_the_others_pass(): void
    {
        $a = $this->invoice('FV-1', 1500, $this->atlas);
        $this->invoice('FV-2', 2000.5, $this->bati);

        $r = $this->deposit($this->csv(self::STATEMENT));
        Payment::create(['document_header_id' => $a->id, 'amount' => 1500, 'method' => 'cash', 'paid_at' => now(), 'user_id' => $this->admin->id]);   // réglée entre-temps
        $done = $this->say($r['suggestions'][0]['text']);

        $this->assertStringContainsString('1 règlement(s) enregistré(s)', $done['body']);
        $this->assertStringContainsString('1 ligne(s) écartée(s)', $done['body']);
        $this->assertSame(2, Payment::count());
    }

    public function test_ignoring_changes_nothing(): void
    {
        $this->invoice('FV-1', 1500, $this->atlas);
        $r = $this->deposit($this->csv(self::STATEMENT));
        $this->say($r['suggestions'][1]['text']);

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, AgentAction::count());
    }

    // ── PDF : lecture par l'IA, confirmée à chaque fois ──────────────

    public function test_a_pdf_statement_is_not_sent_to_the_ai_until_the_admin_confirms(): void
    {
        Setting::set('messaging', 'anthropic_api_key', encrypt('sk-test-cle'));
        Setting::set('agents', 'orchestrator_ai_enabled', 'true');
        $this->invoice('FV-1', 1500, $this->atlas);
        Http::swap(new Factory());                                                        // Http::fake garde le premier stub : on repart de zéro
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'read_statement', 'input' => ['lines' => [
            ['date' => '2026-10-05', 'label' => 'VIR RECU QUINCAILLERIE ATLAS', 'credit' => 1500, 'debit' => null],
            ['date' => 'pas une date', 'label' => 'x', 'credit' => 10],
        ]]]]])]);

        $r = $this->deposit(UploadedFile::fake()->create('releve-octobre.pdf', 50, 'application/pdf'));

        $this->assertStringContainsString("les lignes de votre compte bancaire quitteraient O3", $r['body']);
        $this->assertStringContainsString('il faudra confirmer pour chaque relevé', $r['body']);
        Http::assertNothingSent();                                                        // rien n'est parti : ni lecture IA, ni lecture automatique de document
        $this->assertSame('releve_pdf', AgentEvent::first()->type);

        $read = $this->say($r['suggestions'][0]['text']);
        Http::assertSentCount(1);
        $this->assertStringContainsString('1 règlement(s) reconnu(s) avec certitude', $read['body']);
        $this->assertTrue($read['ai']);                                                  // ce relevé est passé par l'IA : l'étiquette le dit
        $this->assertFalse($r['ai']);                                                    // le dépôt seul n'envoyait rien
        $this->assertSame('bank_statement_sent_to_ai', AgentAction::first()->action);
        $this->assertSame(0, Payment::count());

        $this->say('lis le relevé #' . AgentEvent::where('type', 'releve_pdf')->value('id') . ' avec l\'IA');
        Http::assertSentCount(2);                                                         // chaque lecture est une confirmation de plus
    }

    public function test_pdf_reading_needs_the_ai_and_a_plain_pdf_still_follows_the_usual_document_flow(): void
    {
        $r = $this->deposit(UploadedFile::fake()->create('relevé.pdf', 50, 'application/pdf'));
        $read = $this->say($r['suggestions'][0]['text']);

        $this->assertStringContainsString("Je n'ai pas pu lire le relevé", $read['body']);
        $this->assertStringContainsString('Excel ou CSV de la banque se lit sans IA', $read['body']);
        Http::assertNothingSent();

        $this->assertStringContainsString('document #', $this->deposit(UploadedFile::fake()->create('facture.pdf', 50, 'application/pdf'))['body']);
    }
    // ── Débits : paiements faits aux fournisseurs ────────────────────

    private const DEBITS = "Date;Libellé;Débit;Crédit\n05/10/2026;VIR EMIS LEADER STAR FA-9;4 000,00;\n06/10/2026;LOYER OCTOBRE;1 500,00;\n07/10/2026;VIR EMIS INCONNU;4 000,00;\n08/10/2026;VIR RECU QUINCAILLERIE ATLAS;;4 000,00\n";

    public function test_a_debit_is_matched_to_a_purchase_invoice_only_with_the_supplier_and_the_exact_amount(): void
    {
        $leader = ThirdPartner::factory()->create(['tp_title' => 'Leader Star', 'tp_Role' => 'supplier']);
        $other = ThirdPartner::factory()->create(['tp_title' => 'Immobilière Sud', 'tp_Role' => 'supplier']);
        $this->invoice('FA-9', 4000, $leader, 'InvoicePurchase');
        $this->invoice('FA-LOYER', 1500, $other, 'InvoicePurchase');                    // même montant que « LOYER », mais le libellé ne nomme pas ce fournisseur
        $this->invoice('FV-4', 4000, $this->atlas);

        $r = $this->deposit($this->csv(self::DEBITS));

        $this->assertStringContainsString('3 débit(s)', $r['body']);
        $this->assertStringContainsString('1 crédit(s)', $r['body']);
        $this->assertStringContainsString('2 règlement(s) reconnu(s) avec certitude', $r['body']);
        $this->assertStringContainsString('1 encaissement(s) 4 000,00 MAD, 1 paiement(s) fournisseur 4 000,00 MAD', $r['body']);
        $this->assertStringContainsString('05/10/2026 — −4 000,00 MAD — FA-9 (Leader Star) — reconnu par le tiers et le montant', $r['body']);
        $this->assertStringContainsString('08/10/2026 — +4 000,00 MAD — FV-4 (Quincaillerie Atlas)', $r['body']);
        $this->assertStringNotContainsString('FA-LOYER', $r['body']);                  // le loyer n'est pas rapproché sur le montant seul
        $this->assertSame(0, Payment::count());
    }

    public function test_the_click_records_the_supplier_payment_on_the_purchase_invoice_without_any_message(): void
    {
        $this->mock(\App\Services\PaymentNotificationService::class)->shouldNotReceive('send');
        $leader = ThirdPartner::factory()->create(['tp_title' => 'Leader Star', 'tp_Role' => 'supplier']);
        $purchase = $this->invoice('FA-9', 4000, $leader, 'InvoicePurchase');
        $sale = $this->invoice('FV-4', 4000, $this->atlas);

        $r = $this->deposit($this->csv(self::DEBITS));
        $done = $this->say($r['suggestions'][0]['text']);

        $this->assertStringContainsString('2 règlement(s) enregistré(s)', $done['body']);
        $this->assertStringContainsString('ni aux fournisseurs', $done['body']);
        $p = Payment::where('document_header_id', $purchase->id)->firstOrFail();
        $this->assertSame('2026-10-05', $p->paid_at->toDateString());
        $this->assertSame(0.0, (float) DocumentFooter::where('document_header_id', $purchase->id)->value('amount_due'));
        $this->assertSame(0.0, (float) DocumentFooter::where('document_header_id', $sale->id)->value('amount_due'));

        $again = $this->deposit($this->csv(self::DEBITS));                               // le débit et le crédit de 4 000 ont des empreintes distinctes
        $this->assertStringContainsString('2 déjà importée(s)', $again['body']);
        $this->assertSame(2, Payment::count());
    }
    // ── Vrais fichiers : le type détecté d'après le contenu ne doit pas faire refuser un relevé ──

    private function realFile(string $name, string $content): UploadedFile
    {
        $path = sys_get_temp_dir() . '/real-' . uniqid() . '-' . $name;
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);                  // type DÉTECTÉ d'après le contenu, comme avec un vrai navigateur
    }

    public function test_real_csv_and_excel_files_are_accepted_whatever_mime_is_detected_and_other_files_are_not(): void
    {
        $this->invoice('FV-1', 1500, $this->atlas);
        $post = fn (UploadedFile $f) => $this->actingAs($this->admin, 'sanctum')->post('/api/agents/orchestrateur/fichiers', ['files' => [$f]], ['Accept' => 'application/json']);

        $csv = $this->realFile('releve.csv', "Date opération;Libellé;Débit;Crédit\n05/10/2026;VIR RECU QUINCAILLERIE ATLAS;;1 500,00\n");
        $this->assertNotSame('text/csv', $csv->getMimeType());                       // le piège : un simple texte « text/plain » (ou presque)
        $post($csv)->assertCreated();

        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray([['Date', 'Libellé', 'Débit', 'Crédit'], ['05/10/2026', 'VIR ATLAS', null, 1500]], null, 'A1');
        $xlsxPath = sys_get_temp_dir() . '/real-' . uniqid() . '.xlsx';
        (new Xlsx($sheet))->save($xlsxPath);
        $post(new UploadedFile($xlsxPath, 'releve.xlsx', null, null, true))->assertCreated();

        $post($this->realFile('notes.txt', "Date;Libellé\n"))->assertStatus(422);               // un .txt reste refusé
        $post($this->realFile('script.csv', "<?php echo 'x';"))->assertStatus(422);            // un faux .csv qui est du PHP aussi
        $post($this->realFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'))->assertStatus(422);
    }
}
