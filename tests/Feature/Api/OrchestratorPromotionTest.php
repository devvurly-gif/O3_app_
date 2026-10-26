<?php

namespace Tests\Feature\Api;

use App\Models\AgentEvent;
use App\Models\AgentRoutine;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Promotion;
use App\Models\Slide;
use App\Models\User;
use App\Services\Agents\RoutineRunner;
use App\Services\Agents\RoutineSchedule;
use App\Services\Agents\RoutineSteps;
use Carbon\Carbon;
use Database\Seeders\AgentFoundationSeeder;
use Tests\Concerns\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * « Prépare une promotion de 15 % sur la marque Jadever pour jeudi » : l'agent Marketing propose, un clic crée la
 * promotion ; une routine hebdomadaire prépare cette proposition chaque semaine sans jamais rien créer seule.
 */
class OrchestratorPromotionTest extends TestCase
{
    use RefreshTenantDatabase;

    private User $admin;
    private Brand $jadever;
    private Category $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AgentFoundationSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->jadever = Brand::create(['br_title' => 'Jadever', 'br_status' => true]);
        $this->tools = Category::factory()->create(['ctg_title' => 'Outillage manuel']);
        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00', RoutineSchedule::timezone()));   // un lundi
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function say(string $text): array
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/agents/orchestrateur', ['message' => $text])->assertCreated()->json('reply');
    }

    private function product(string $title, string $sku, array $over = []): Product
    {
        return Product::factory()->create(array_merge([
            'p_title' => $title, 'p_sku' => $sku, 'p_salePrice' => 200, 'p_purchasePrice' => 120, 'p_status' => true, 'is_ecom' => true,
            'brand_id' => $this->jadever->id, 'category_id' => $this->tools->id,
        ], $over));
    }

    public function test_it_proposes_a_promotion_for_a_brand_and_a_day_without_creating_anything(): void
    {
        $a = $this->product('Perceuse', 'JD1');
        $b = $this->product('Scie', 'JD2');
        $this->product('Hors boutique', 'JD3', ['is_ecom' => false]);
        $this->product('Inactif', 'JD4', ['p_status' => false]);
        $this->product('Sans prix', 'JD5', ['p_salePrice' => 0]);
        $other = Brand::create(['br_title' => 'Autre', 'br_status' => true]);
        $this->product('Autre marque', 'AU1', ['brand_id' => $other->id]);

        $reply = $this->say('prépare une promotion de 15 % sur la marque Jadever pour jeudi');

        $event = AgentEvent::where('type', 'promotion_proposition')->firstOrFail();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $event->payload['product_ids']);
        $this->assertEquals(15, $event->payload['value']);
        $this->assertStringContainsString('2 produit(s) visible(s)', $reply['body']);
        $this->assertStringContainsString('200,00 DH → 170,00 DH', $reply['body']);
        $this->assertSame("applique la promotion du lot #{$event->id}", $reply['suggestions'][0]['text']);
        $this->assertSame(0, Promotion::count());   // rien avant le clic
        $this->assertSame(\App\Models\Agent::where('domain', 'marketing')->value('id'), $event->agent_id);

        $tz = RoutineSchedule::timezone();
        $this->assertSame('2026-10-29 00:00', Carbon::parse($event->payload['starts_at'])->setTimezone($tz)->format('Y-m-d H:i'));   // jeudi
        $this->assertSame('2026-10-29 23:59', Carbon::parse($event->payload['ends_at'])->setTimezone($tz)->format('Y-m-d H:i'));
    }

    public function test_applying_creates_an_active_percentage_promotion_with_the_products(): void
    {
        $a = $this->product('Perceuse', 'JD1');
        $b = $this->product('Scie', 'JD2');
        ProductImage::create(['product_id' => $a->id, 'url' => '/storage/products/jd1.jpg', 'title' => 'jd1', 'isPrimary' => true]);
        $this->say('prépare une promotion de 15 % sur la marque Jadever pour jeudi');
        $event = AgentEvent::where('type', 'promotion_proposition')->firstOrFail();
        $b->update(['is_ecom' => false]);   // retiré de la boutique entre-temps

        $done = $this->say("applique la promotion du lot #{$event->id}");

        $this->assertStringContainsString('créée', $done['body']);
        $this->assertStringContainsString("Bannière d'accueil créée", $done['body']);
        $promotion = Promotion::firstOrFail();
        $this->assertSame('percentage', $promotion->type);
        $this->assertEquals(15, $promotion->value);
        $this->assertTrue($promotion->is_active);
        $this->assertSame([$a->id], $promotion->products()->pluck('products.id')->all());   // seulement ce qui est encore en ligne

        // Visuel : la photo d'un produit de la sélection, le texte de bannière et un slide d'accueil lié à la promotion.
        $this->assertSame('/storage/products/jd1.jpg', $promotion->banner_image);
        $this->assertSame('-15 % sur Jadever', $promotion->banner_text);
        $this->assertStringContainsString('Remise de 15 %', $promotion->description);
        $slide = Slide::firstOrFail();
        $this->assertSame(['hero', 'promotion', $promotion->id, '/storage/products/jd1.jpg'], [$slide->position, $slide->link_type, $slide->link_id, $slide->image]);
        $this->assertTrue($slide->is_active);
        $this->assertEquals($promotion->ends_at, $slide->ends_at);
        $this->assertSame('done', $event->fresh()->status);
        $this->assertSame(1, \App\Models\AgentAction::where('action', 'promotion_created')->count());

        // Un lot ne s'applique qu'une fois.
        $this->say("applique la promotion du lot #{$event->id}");
        $this->assertSame(1, Promotion::count());
    }

    public function test_without_any_product_photo_the_promotion_is_created_without_a_banner_and_says_so(): void
    {
        $this->product('Perceuse', 'JD1');
        $this->say('prépare une promotion de 15 % sur la marque Jadever');
        $event = AgentEvent::where('type', 'promotion_proposition')->firstOrFail();

        $done = $this->say("applique la promotion du lot #{$event->id}");

        $this->assertStringContainsString('Aucune bannière', $done['body']);
        $this->assertNull(Promotion::firstOrFail()->banner_image);
        $this->assertSame(0, Slide::count());
    }

    public function test_it_selects_by_category_and_lasts_several_days(): void
    {
        $this->product('Marteau', 'MA1');
        $this->say('crée une promotion de 10 % sur la catégorie Outillage manuel pendant 3 jours');

        $event = AgentEvent::where('type', 'promotion_proposition')->firstOrFail();
        $this->assertSame('categorie', $event->payload['target']['kind']);
        $tz = RoutineSchedule::timezone();
        $this->assertSame('2026-10-26', Carbon::parse($event->payload['starts_at'])->setTimezone($tz)->format('Y-m-d'));   // aujourd'hui
        $this->assertSame('2026-10-28 23:59', Carbon::parse($event->payload['ends_at'])->setTimezone($tz)->format('Y-m-d H:i'));
    }

    public function test_unknown_brand_missing_selection_and_empty_selection_create_nothing(): void
    {
        $this->product('Perceuse', 'JD1');

        $this->assertStringContainsString('Je ne trouve pas la marque', $this->say('prépare une promotion de 15 % sur la marque Inconnue')['body']);
        $this->assertStringContainsString('Sur quelle sélection', $this->say('prépare une promotion de 15 %')['body']);

        Product::query()->update(['is_ecom' => false]);
        $this->assertStringContainsString("visible sur la boutique", $this->say('prépare une promotion de 15 % sur la marque Jadever')['body']);

        $this->assertSame(0, AgentEvent::where('type', 'promotion_proposition')->count());
        $this->assertSame(0, Promotion::count());
    }

    public function test_a_weekly_routine_prepares_the_proposal_every_run_and_never_creates_the_promotion(): void
    {
        $this->product('Perceuse', 'JD1');

        $reply = $this->say('chaque jeudi à 8 h, prépare une promotion de 15 % sur la marque Jadever');

        $this->assertStringContainsString('Routine proposée', $reply['body']);
        $proposal = AgentEvent::where('type', 'routine_proposition')->firstOrFail();
        $this->say("applique la proposition #{$proposal->id}");

        $routine = AgentRoutine::firstOrFail();
        $this->assertSame(['promo:marque:Jadever:15'], $routine->steps);
        $this->assertEquals(['frequency' => 'weekly', 'time' => '08:00', 'weekday' => 4], $routine->schedule);

        $out = app(RoutineRunner::class)->run($routine, 'lancée à la demande');

        $this->assertStringContainsString('Promotion proposée', $out['body']);
        $this->assertSame(1, AgentEvent::where('type', 'promotion_proposition')->count());
        $this->assertSame(0, Promotion::count());   // la routine ne crée rien : il faut le clic
    }

    public function test_a_routine_needs_a_day_a_known_brand_and_a_discount(): void
    {
        $this->assertStringContainsString('Quel jour', $this->say('planifie une promotion de 15 % sur la marque Jadever à 8 h chaque semaine')['body']);
        $this->assertStringContainsString('Je ne trouve pas la marque', $this->say('chaque jeudi à 8 h, prépare une promotion de 15 % sur la marque Inconnue')['body']);
        $this->assertSame(0, AgentEvent::where('type', 'routine_proposition')->count());
    }

    public function test_promo_steps_are_validated_by_the_routine_whitelist(): void
    {
        $this->assertSame(
            ['promo:marque:Jadever:15', 'promo:categorie:Outillage manuel:10:3'],
            RoutineSteps::sanitize(['promo:marque:Jadever:15', 'promo:categorie:Outillage manuel:10:3', 'promo:marque:X:abc', 'promo:autre:Jadever:15', 'applique la promotion']),
        );
        $this->assertSame('prépare une promotion de 10 % sur la categorie Outillage manuel pendant 3 jours', RoutineSteps::promoPhrase('promo:categorie:Outillage manuel:10:3'));
    }
}
