<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Promotion;
use App\Models\Slide;
use App\Models\User;
use App\Services\PromotionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * « Prépare une promotion de 15 % sur la marque Jadever pour jeudi » (agent Marketing).
 *
 * La phrase donne une remise, une sélection (une marque ou une catégorie) et, au choix, un jour (« pour jeudi »),
 * une durée (« pendant 3 jours ») ou une fin (« jusqu'à dimanche »). L'agent choisit les produits de la sélection
 * qui sont visibles sur la boutique (actifs, publiés, avec un prix de vente) et PROPOSE la promotion : rien n'est
 * créé avant le clic de l'administrateur, qui crée alors une promotion en pourcentage avec ces produits.
 *
 * Une routine hebdomadaire (« chaque jeudi à 8 h, prépare une promotion… ») ne fait que préparer cette
 * proposition : la promotion ne part en ligne qu'au clic.
 */
class PromotionAssistant
{
    public const MAX_PRODUCTS = 100;
    private const MAX_PERCENT = 90;
    private const MAX_DAYS = 14;
    private const SHOWN = 8;

    private const WEEKDAYS = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7];

    /**
     * @param string $n phrase normalisée (sans accent, minuscules)
     * @return array{value: ?float, kind: ?string, name: ?string, weekday: ?int, until: ?int, days: int}
     */
    public function parse(string $n): array
    {
        $value = preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $n, $m) ? (float) str_replace(',', '.', $m[1]) : null;

        $kind = $name = null;
        if (preg_match('/\b(marque|categorie)s?\s+(?:de\s+|d\W)?(?:la\s+|le\s+|l\W)?([a-z0-9][a-z0-9&.\-]*(?:\s+[a-z0-9&.\-]+)*?)(?=\s+(?:pour|jusqu\w*|du|au|pendant|ce|cette|chaque|sur|avec|a)\b|\s*,|\s*$)/', $n, $m)) {
            $kind = $m[1];
            $name = trim($m[2]);
        }

        $weekdays = implode('|', array_keys(self::WEEKDAYS));
        $until = preg_match('/jusqu\W*(?:a|au)\s+(' . $weekdays . ')\b/', $n, $m) ? self::WEEKDAYS[$m[1]] : null;
        $weekday = preg_match('/\b(?:pour|ce|le|du)\s+(' . $weekdays . ')\b/', $n, $m) ? self::WEEKDAYS[$m[1]] : null;
        $days = preg_match('/pendant\s+(\d+)\s*jours?/', $n, $m) ? max(1, min(self::MAX_DAYS, (int) $m[1])) : 1;

        return ['value' => $value, 'kind' => $kind, 'name' => $name, 'weekday' => $weekday, 'until' => $until, 'days' => $days];
    }

    /** La marque ou la catégorie citée (comparaison sans accent ni casse). @return array{kind: string, id: int, title: string}|null */
    public function resolveTarget(string $kind, string $name): ?array
    {
        $enricher = app(CatalogEnricher::class);
        $key = $enricher->fold($name);
        if ($kind === 'marque') {
            $row = Brand::all(['id', 'br_title'])->first(fn (Brand $b) => $enricher->fold($b->br_title) === $key);

            return $row ? ['kind' => 'marque', 'id' => $row->id, 'title' => $row->br_title] : null;
        }
        $row = Category::all(['id', 'ctg_title'])->first(fn (Category $c) => $enricher->fold($c->ctg_title) === $key);

        return $row ? ['kind' => 'categorie', 'id' => $row->id, 'title' => $row->ctg_title] : null;
    }

    /** @return array{0: Carbon, 1: Carbon} début et fin, dans le fuseau de l'application (comme les dates de la base) */
    public function period(?int $weekday, ?int $until, int $days): array
    {
        $tz = RoutineSchedule::timezone();
        $now = Carbon::now($tz);

        $day = $now->copy()->startOfDay();
        if ($weekday !== null) {
            $day->addDays(($weekday - $day->isoWeekday() + 7) % 7);   // aujourd'hui compte
        }
        $start = $day->copy()->startOfDay();
        if ($start->lt($now)) {
            $start = $now->copy()->startOfMinute();   // le jour est déjà commencé : la promotion démarre au clic
        }
        $lastDay = $until !== null
            ? $day->copy()->addDays(($until - $day->isoWeekday() + 7) % 7)
            : $day->copy()->addDays($days - 1);

        $appTz = config('app.timezone');

        return [$start->setTimezone($appTz), $lastDay->endOfDay()->setTimezone($appTz)];
    }

    public function propose(User $admin, string $n): array
    {
        $p = $this->parse($n);
        if ($p['value'] === null || $p['value'] <= 0 || $p['value'] >= self::MAX_PERCENT) {
            return $this->reply('Quelle remise ? Dites-le en pourcentage (entre 1 et ' . (self::MAX_PERCENT - 1) . ' %), par exemple « prépare une promotion de 15 % sur la marque Jadever pour jeudi ».', error: true);
        }
        if ($p['kind'] === null || $p['name'] === null) {
            return $this->reply('Sur quelle sélection ? Citez une marque ou une catégorie, par exemple « prépare une promotion de ' . (int) $p['value'] . ' % sur la marque Jadever » ou « … sur la catégorie Outillage manuel ».', error: true);
        }
        $target = $this->resolveTarget($p['kind'], $p['name']);
        if ($target === null) {
            $known = ($p['kind'] === 'marque' ? Brand::orderBy('br_title')->limit(10)->pluck('br_title') : Category::orderBy('ctg_title')->limit(10)->pluck('ctg_title'))->implode(', ');

            return $this->reply('Je ne trouve pas la ' . $p['kind'] . ' « ' . Str::title($p['name']) . ' ».' . ($known !== '' ? " Connues : {$known}." : ''), error: true);
        }

        [$start, $end] = $this->period($p['weekday'], $p['until'], $p['days']);
        if ($end->isPast()) {
            return $this->reply("Cette période est déjà terminée ({$end->format('d/m H:i')}) : indiquez un jour à venir.", error: true);
        }

        $column = $target['kind'] === 'marque' ? 'brand_id' : 'category_id';
        $products = $this->visibleProducts($column, $target['id'])->take(self::MAX_PRODUCTS)->get();
        if ($products->isEmpty()) {
            return $this->reply("Aucun produit de la {$target['kind']} « {$target['title']} » n'est visible sur la boutique (actif, publié et avec un prix de vente) : une promotion n'y aurait aucun effet. Publiez d'abord les produits.", links: [['label' => 'Produits', 'to' => '/products']]);
        }

        $name = "Promo {$target['title']} du " . $start->format('d/m') . ($end->isSameDay($start) ? '' : ' au ' . $end->format('d/m'));
        $event = AgentEvent::create([
            'type' => 'promotion_proposition', 'source' => 'orchestrator', 'status' => AgentEvent::STATUS_ROUTED,
            'agent_id' => Agent::where('domain', 'marketing')->value('id'),
            'payload' => [
                'text' => "Proposition de promotion : {$name}", 'name' => $name, 'value' => $p['value'], 'target' => $target,
                'product_ids' => $products->pluck('id')->all(), 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(),
                'requested_by' => $admin->name,
            ],
        ]);

        $lines = $products->take(self::SHOWN)->map(fn (Product $x) => "• {$x->p_title} : " . $this->money((float) $x->p_salePrice) . ' → ' . $this->money(round((float) $x->p_salePrice * (1 - $p['value'] / 100), 2)))->implode("\n");

        return $this->reply(
            "Promotion proposée « {$name} » (lot #{$event->id}) : -" . $this->percent($p['value']) . " % sur la {$target['kind']} « {$target['title']} », "
            . 'du ' . $start->format('d/m H:i') . ' au ' . $end->format('d/m H:i') . ".\n{$products->count()} produit(s) visible(s) sur la boutique :\n{$lines}"
            . ($products->count() > self::SHOWN ? "\n… et " . ($products->count() - self::SHOWN) . ' autre(s).' : '')
            . "\n\nRien n'est créé avant votre clic. La remise s'applique sur le prix de vente ; pour l'arrêter plus tôt, désactivez la promotion dans « Promotions & bannières ».",
            links: [['label' => 'Promotions & bannières', 'to' => '/marketing/promotions']],
            suggestions: [
                ['label' => 'Créer la promotion', 'text' => "applique la promotion du lot #{$event->id}"],
                ['label' => 'Ignorer', 'text' => "ignore le lot #{$event->id}"],
            ],
            eventId: $event->id,
        );
    }

    public function apply(User $admin, AgentEvent $event): array
    {
        $payload = $event->payload ?? [];
        $end = Carbon::parse($payload['ends_at'] ?? 'now');
        if ($end->isPast()) {
            return $this->reply("Le lot #{$event->id} n'a pas été appliqué : la période est terminée. Redemandez la promotion.", error: true, eventId: $event->id);
        }

        $target = $payload['target'] ?? [];
        $column = ($target['kind'] ?? '') === 'marque' ? 'brand_id' : 'category_id';
        // Seulement ce qui est encore visible sur la boutique, parmi les produits proposés.
        $ids = $this->visibleProducts($column, (int) ($target['id'] ?? 0))->whereIn('id', $payload['product_ids'] ?? [])->pluck('id')->all();
        if ($ids === []) {
            return $this->reply("Le lot #{$event->id} n'a pas été appliqué : plus aucun des produits proposés n'est visible sur la boutique.", error: true, eventId: $event->id);
        }

        // Le visuel de la promotion : la photo principale d'un produit de la sélection (la boutique a besoin d'une image de bannière).
        $photo = ProductImage::whereIn('product_id', $ids)->orderByDesc('isPrimary')->orderBy('id')->value('url');
        $text = '-' . $this->percent((float) $payload['value']) . ' % sur ' . ($target['title'] ?? '');
        $starts = Carbon::parse($payload['starts_at']);
        $ends = Carbon::parse($payload['ends_at']);

        [$promotion, $slide] = DB::transaction(function () use ($payload, $ids, $target, $photo, $text, $starts, $ends) {
            $slug = Str::slug($payload['name']) ?: 'promo';
            if (Promotion::where('slug', $slug)->exists()) {
                $slug .= '-' . Str::lower(Str::random(4));
            }
            $promotion = Promotion::create([
                'name' => $payload['name'], 'slug' => $slug, 'type' => 'percentage', 'value' => $payload['value'],
                'description' => 'Remise de ' . $this->percent((float) $payload['value']) . ' % sur ' . count($ids) . ' produit(s) ' . ($target['title'] ?? '')
                    . ', du ' . $starts->format('d/m/Y') . ' au ' . $ends->format('d/m/Y') . '.',
                'banner_image' => $photo, 'banner_text' => $text,
                'starts_at' => $starts, 'ends_at' => $ends, 'is_active' => true, 'priority' => 0,
            ]);
            $promotion->products()->attach($ids);

            // Comme l'écran « Promotions & bannières » : une image de bannière donne un slide d'accueil lié à la promotion.
            $slide = $photo === null ? null : Slide::create([
                'title' => $promotion->name, 'subtitle' => $text, 'image' => $photo, 'button_text' => 'Voir la promo',
                'link_type' => 'promotion', 'link_id' => $promotion->id, 'position' => 'hero',
                'starts_at' => $starts, 'ends_at' => $ends, 'is_active' => true,
            ]);

            return [$promotion, $slide];
        });
        app(PromotionService::class)->clearCache();

        $result = ['promotion_id' => $promotion->id, 'products' => count($ids), 'skipped' => count($payload['product_ids'] ?? []) - count($ids), 'slide_id' => $slide?->id];
        $event->update(['status' => AgentEvent::STATUS_DONE, 'payload' => array_merge($payload, ['result' => $result])]);
        AgentAction::create([
            'agent_id' => $event->agent_id ?? Agent::where('domain', 'marketing')->value('id'), 'event_id' => $event->id,
            'action' => 'promotion_created', 'level' => 'approval', 'input' => ['requested_by' => $payload['requested_by'] ?? null], 'result' => $result,
        ]);

        return $this->reply(
            "Promotion « {$promotion->name} » créée : -" . $this->percent((float) $promotion->value) . ' % sur ' . count($ids) . ' produit(s), du '
            . $promotion->starts_at->format('d/m H:i') . ' au ' . $promotion->ends_at->format('d/m H:i') . '.'
            . ($slide ? "\nBannière d'accueil créée avec la photo d'un produit de la sélection." : "\nAucune bannière : aucun produit de la sélection n'a de photo. Ajoutez une image dans « Promotions & bannières »."),
            links: [['label' => 'Promotions & bannières', 'to' => '/marketing/promotions']],
            eventId: $event->id,
        );
    }

    /** Les produits que la boutique montre : publiés, actifs, avec un prix de vente. */
    private function visibleProducts(string $column, int $id)
    {
        return Product::where('is_ecom', true)->where('p_status', true)->where('p_salePrice', '>', 0)->where($column, $id)->orderBy('id');
    }

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' DH';
    }

    private function reply(string $body, array $links = [], bool $error = false, ?int $eventId = null, array $suggestions = []): array
    {
        return ['body' => $body, 'meta' => array_filter([
            'intent'      => 'promotion',
            'links'       => $links ?: null,
            'error'       => $error ?: null,
            'event_id'    => $eventId,
            'suggestions' => $suggestions ?: null,
        ], fn ($v) => $v !== null)];
    }
}
