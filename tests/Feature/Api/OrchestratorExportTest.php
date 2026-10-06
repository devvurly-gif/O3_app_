<?php

namespace Tests\Feature\Api;

use App\Models\DocumentFooter;
use App\Models\DocumentHeader;
use App\Models\OrchestratorMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Agents\ExportAssistant;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Exporte en Excel / PDF / CSV » : la dernière lecture affichée devient un fichier, téléchargeable par son seul
 * demandeur pendant 24 heures. Aucune donnée nouvelle n'est lue, rien n'est envoyé.
 */
class OrchestratorExportTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        Setting::set('locale', 'timezone', 'Etc/GMT-1');
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));
        Http::swap(new Factory());
        Http::fake();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Karim Admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reply(string $text, ?User $as = null): array
    {
        return $this->actingAs($as ?? $this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    private function sale(float $ttc): void
    {
        $d = DocumentHeader::factory()->create(['document_type' => 'InvoiceSale', 'status' => 'confirmed', 'issued_at' => '2026-10-05', 'user_id' => $this->admin->id]);
        DocumentFooter::factory()->create(['document_header_id' => $d->id, 'total_ht' => round($ttc / 1.2, 2), 'total_ttc' => $ttc]);
    }

    public function test_the_last_read_becomes_an_excel_file_with_real_numbers(): void
    {
        $this->sale(1200);
        $this->reply('ventes du mois par vendeur');

        $r = $this->reply('exporte en excel');

        $this->assertStringContainsString('Export prêt', $r['body']);
        $this->assertCount(1, $r['files']);
        $this->assertStringEndsWith('-2026-10-14.xlsx', $r['files'][0]['name']);

        $res = $this->actingAs($this->admin, 'sanctum')->get('/api' . $r['files'][0]['url'])->assertOk();
        $sheet = IOFactory::load($res->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertStringContainsString('Ventes du mois', (string) $sheet->getCell('A1')->getValue());
        $this->assertSame('Karim Admin', $sheet->getCell('A3')->getValue());
        $this->assertSame('1 vente(s)', $sheet->getCell('B3')->getValue());
        $this->assertEquals(1200.0, $sheet->getCell('C3')->getValue());                    // un vrai nombre, pas du texte
        $this->assertStringContainsString('MAD', $sheet->getStyle('C3')->getNumberFormat()->getFormatCode());
    }

    public function test_csv_and_pdf_exports(): void
    {
        $this->sale(1200);
        $this->reply('ventes du mois par vendeur');

        $csv = $this->reply('télécharge en csv');
        $csvRes = $this->actingAs($this->admin, 'sanctum')->get('/api' . $csv['files'][0]['url'])->assertOk();
        $content = file_get_contents($csvRes->baseResponse->getFile()->getPathname());
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);                        // BOM : Excel lit les accents
        $this->assertStringContainsString('"Karim Admin";"1 vente(s)";"1 200,00 MAD"', $content);

        $pdf = $this->reply('mets ça en pdf');
        $pdfRes = $this->actingAs($this->admin, 'sanctum')->get('/api' . $pdf['files'][0]['url'])->assertOk();
        $this->assertStringStartsWith('%PDF', file_get_contents($pdfRes->baseResponse->getFile()->getPathname()));
        $this->assertStringEndsWith('.pdf', $pdf['files'][0]['name']);
    }

    public function test_nothing_to_export_and_only_the_requester_can_download(): void
    {
        $r = $this->reply('exporte en pdf');
        $this->assertStringContainsString("Je n'ai rien à exporter", $r['body']);
        $this->assertTrue($r['error']);

        $this->sale(500);
        $this->reply('ventes du mois par vendeur');
        $file = $this->reply('exporte en excel')['files'][0];

        $other = User::factory()->admin()->create();
        $this->actingAs($other, 'sanctum')->get('/api' . $file['url'])->assertNotFound();      // le fichier d'un autre administrateur
        $this->actingAs($this->admin, 'sanctum')->get('/api/agents/orchestrateur/exports/00000000-0000-0000-0000-000000000000')->assertNotFound();
        $this->actingAs($this->admin, 'sanctum')->get('/api' . $file['url'])->assertOk();
    }

    public function test_it_does_not_hijack_other_phrases_and_follow_ups_survive_an_export(): void
    {
        $this->sale(500);
        $this->assertStringContainsString('Ventes du mois', $this->reply('ventes du mois')['body']);
        $this->reply('exporte en excel');

        // Une suite fonctionne encore après l'export (la mémoire garde la dernière lecture, pas le dernier message).
        $this->assertStringContainsString('Suite de votre question', $this->reply('et hier ?')['body']);

        // Un mot de format sans demande d'export n'en est pas une.
        $this->assertSame([], $this->reply('ventes du mois')['files']);
        $this->assertSame([], $this->reply('où est le pdf de la facture')['files']);
    }

    public function test_old_exports_are_removed_and_formulas_are_neutralized(): void
    {
        OrchestratorMessage::create(['user_id' => $this->admin->id, 'role' => 'orchestrator', 'body' => "Liste d'essai :\n\n• =CMD|' /C calc'!A0 — +1 — normal\n• -3 — texte", 'meta' => ['cmd' => 'liste d essai']]);

        $first = $this->reply('exporte en csv')['files'][0];
        $csv = file_get_contents($this->actingAs($this->admin, 'sanctum')->get('/api' . $first['url'])->assertOk()->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString("'=CMD", $csv);       // pas lue comme une formule par le tableur
        $this->assertStringContainsString("'+1", $csv);
        $this->assertStringContainsString("'-3", $csv);

        // Au-delà de 24 heures, le fichier disparaît au prochain export.
        $path = (new ExportAssistant())->find($this->admin->id, substr($first['url'], strrpos($first['url'], '/') + 1))['path'];
        touch($path, time() - 25 * 3600);
        $this->reply('exporte en pdf');
        $this->assertNull((new ExportAssistant())->find($this->admin->id, substr($first['url'], strrpos($first['url'], '/') + 1)));
    }
}
