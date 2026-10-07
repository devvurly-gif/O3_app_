<?php

namespace App\Services\Agents;

use App\Http\Requests\Api\PurchaseImportRequest;
use App\Http\Requests\Api\WhatsAppOrderImportRequest;
use App\Models\Agent;
use App\Models\AgentEvent;
use App\Models\DocumentHeader;
use App\Models\OrchestratorMessage;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImageService;
use App\Services\Purchases\PurchaseImportService;
use App\Services\Ventes\CustomerLookup;
use App\Services\Ventes\WhatsAppOrderImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Les fichiers (photos, PDF) déposés dans la conversation avec l'orchestrateur : il les garde, les
 * fait lire (DocumentReader), dit ce que c'est et PROPOSE la suite. Chaque fichier devient un
 * événement `document_depose` (source `orchestrator`) du journal des agents.
 *
 * Garde-fous :
 *  - rien n'est créé à la réception : l'orchestrateur propose, l'administrateur clique ;
 *  - une proposition de brouillon n'est faite que si le contrôle à blanc (dry run) du service
 *    d'import existant l'accepte : mêmes règles que l'agent de saisie et que la messagerie
 *    (SKU « sosie », fournisseur ressemblant, totaux, client unique, produits non ambigus…) ;
 *  - un achat ou une livraison ne sont jamais que des BROUILLONS (stock en attente) ;
 *  - un paiement n'est jamais enregistré : l'orchestrateur ne fait que retrouver la facture
 *    probable et renvoyer vers la trésorerie ;
 *  - ce que le modèle n'a pas pu lire est dit, jamais deviné.
 */
class DocumentIntake
{
    public const MAX_FILES = 3;

    public function __construct(
        private DocumentReader $reader,
        private PurchaseImportService $purchases,
        private WhatsAppOrderImportService $sales,
        private CustomerLookup $customers,
        private ProductImageService $images,
        private BankStatementImporter $statements,
    ) {
    }

    /**
     * @param array<int, UploadedFile> $files
     * @return array{user: OrchestratorMessage, reply: OrchestratorMessage}
     */
    public function receive(User $admin, array $files, string $note): array
    {
        $files = array_slice($files, 0, self::MAX_FILES);

        $user = OrchestratorMessage::create([
            'user_id' => $admin->id,
            'role'    => OrchestratorMessage::ROLE_ADMIN,
            'body'    => $note !== '' ? $note : (count($files) > 1 ? 'Documents déposés' : 'Document déposé'),
            'meta'    => ['attachments' => array_map(fn (UploadedFile $f) => [
                'name' => $f->getClientOriginalName(), 'mime' => $f->getMimeType(), 'size' => $f->getSize(),
            ], $files)],
        ]);

        $sections = [];
        $suggestions = [];
        $links = [];
        $events = [];
        foreach ($files as $file) {
            $section = $this->receiveOne($admin, $file, $note);
            $sections[] = $section['text'];
            $suggestions = array_merge($suggestions, $section['suggestions']);
            $links = array_merge($links, $section['links']);
            $events[] = $section['event_id'];
        }

        $reply = OrchestratorMessage::create([
            'user_id' => $admin->id,
            'role'    => OrchestratorMessage::ROLE_ORCHESTRATOR,
            'body'    => implode("\n\n", $sections),
            'meta'    => array_filter([
                'intent'      => 'documents',
                'links'       => $this->uniqueLinks($links) ?: null,
                'suggestions' => $suggestions ?: null,
                'event_id'    => count($events) === 1 ? $events[0] : null,
                'ai'          => true,
            ], fn ($v) => $v !== null),
        ]);

        return ['user' => $user, 'reply' => $reply];
    }

    /** @return array{text: string, suggestions: array, links: array, event_id: int} */
    private function receiveOne(User $admin, UploadedFile $file, string $note): array
    {
        // Un relevé bancaire (Excel / CSV, ou PDF nommé « relevé ») ne passe jamais par la lecture automatique des documents.
        if ($this->statements->isStatement($file, $note)) {
            return $this->statements->receive($admin, $file);
        }

        $name = $file->getClientOriginalName();
        $mime = (string) $file->getMimeType();
        $stored = $file->store('orchestrator/' . now()->format('Y-m'), 'local');

        $event = AgentEvent::create([
            'type'    => 'document_depose',
            'source'  => 'orchestrator',
            'status'  => AgentEvent::STATUS_NEW,
            'payload' => [
                'text'        => "Document déposé : {$name}",
                'file'        => ['path' => $stored, 'name' => $name, 'mime' => $mime, 'size' => $file->getSize()],
                'note'        => $note !== '' ? $note : null,
                'uploaded_by' => $admin->name,
            ],
        ]);

        $doc = $stored ? $this->reader->read(Storage::disk('local')->path($stored), $mime) : null;
        if ($doc === null) {
            $this->update($event, AgentEvent::STATUS_TO_SORT, ['reading_failed' => $this->reader->failure()]);

            return [
                'text'        => "Fichier « {$name} » (document #{$event->id}) : je n'ai pas pu le lire — " . ($this->reader->failure() ?? 'lecture impossible')
                    . ".\nIl est conservé et classé à trier ; vous pouvez le traiter à la main.",
                'suggestions' => [],
                'links'       => [['label' => 'Import facture OCR', 'to' => '/achats/ocr-import']],
                'event_id'    => $event->id,
            ];
        }

        $typeLabel = DocumentReader::TYPES[$doc['type']];
        $certainty = $doc['confidence'] !== null ? ' (certitude ' . round($doc['confidence'] * 100) . ' %)' : '';
        $head = "Fichier « {$name} » (document #{$event->id}) : {$typeLabel}{$certainty}."
            . ($doc['summary'] !== '' ? "\n{$doc['summary']}" : '');

        $proposal = match ($doc['type']) {
            'facture_fournisseur' => $this->proposePurchase($event, $doc, $admin, $name),
            'bon_commande_client' => $this->proposeSale($event, $doc, $admin),
            'document_emis'       => $this->describeIssued($doc),
            'paiement'            => $this->proposePayment($event, $doc),
            'photo_produit'       => $this->proposePhoto($event, $doc, $mime),
            default               => ['text' => "Je ne sais pas quoi en faire automatiquement : il est classé à trier.", 'suggestions' => [], 'links' => [], 'action' => 'none'],
        };

        $keep = in_array($proposal['action'], ['purchase', 'sale', 'photo', 'payment'], true);
        $this->update(
            $event,
            // « known » : un document émis qui existe déjà dans O3, il n'y a rien à faire.
            $keep ? AgentEvent::STATUS_ROUTED : ($proposal['action'] === 'known' ? AgentEvent::STATUS_DONE : AgentEvent::STATUS_TO_SORT),
            ['reading' => $doc, 'proposal' => ['action' => $proposal['action']] + ($proposal['data'] ?? [])],
            $keep ? $this->agentFor($proposal['action']) : null,
        );

        $suggestions = $proposal['suggestions'];
        if ($keep) {
            $suggestions[] = ['label' => 'Ignorer ce document', 'text' => "ignore le document #{$event->id}"];
        }

        return ['text' => $head . "\n" . $proposal['text'], 'suggestions' => $suggestions, 'links' => $proposal['links'], 'event_id' => $event->id];
    }

    // ── Propositions ─────────────────────────────────────────────────

    private function proposePurchase(AgentEvent $event, array $d, User $admin, string $fileName): array
    {
        $missing = [];
        foreach ([['reference', 'le numéro de la facture'], ['date', 'la date']] as [$key, $label]) {
            if (empty($d[$key])) {
                $missing[] = $label;
            }
        }
        $supplier = array_filter(['ice' => $d['party']['ice'], 'name' => $d['party']['name'], 'phone' => $d['party']['phone']]);
        if (!$supplier || (empty($supplier['ice']) && empty($supplier['name']))) {
            $missing[] = 'le fournisseur (nom ou ICE)';
        }
        if ($d['totals']['ht'] === null && $d['totals']['ttc'] === null) {
            $missing[] = 'le total HT ou TTC';
        }
        if ($d['lines'] === []) {
            $missing[] = 'les lignes d\'articles';
        }
        $noSku = count(array_filter($d['lines'], fn ($l) => empty($l['sku'])));
        $noPrice = count(array_filter($d['lines'], fn ($l) => $l['unit_price'] === null));
        $noSku && $missing[] = "la référence de {$noSku} ligne(s)";
        $noPrice && $missing[] = "le prix de {$noPrice} ligne(s)";

        $d['lines'] = $this->withUniformVatRate($d);

        $links = [['label' => 'Import facture OCR', 'to' => '/achats/ocr-import'], ['label' => "Documents d'achat", 'to' => '/achats/documents']];
        if ($missing) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [],
                'text' => "Je ne peux pas préparer le brouillon d'achat seul : je n'ai pas pu lire " . implode(', ', $missing) . ".\nVous pouvez la saisir avec l'import de facture, ou me renvoyer une photo plus nette."];
        }

        $payload = [
            'external_id'        => 'ORC-' . $event->id,
            'type'               => 'facture_achat',
            'source_file'        => mb_substr($fileName, 0, 255),
            'supplier'           => $supplier,
            // Un fournisseur n'est créé que s'il est identifié par son ICE ; sinon il doit déjà exister.
            'allow_create'       => ['products' => true, 'supplier' => !empty($supplier['ice'])],
            'supplier_reference' => $d['reference'],
            'date'               => $d['date'],
            'due_date'           => $d['due_date'] && $d['due_date'] >= $d['date'] ? $d['due_date'] : null,
            'prices_include_vat' => $d['prices_include_vat'],
            'totals'             => array_filter(['ht' => $d['totals']['ht'], 'tva' => $d['totals']['tva'], 'ttc' => $d['totals']['ttc']], fn ($v) => $v !== null),
            'lines'              => array_map(fn ($l) => array_filter([
                'sku' => $l['sku'], 'ean13' => $l['ean13'], 'designation' => $l['designation'], 'qty' => $l['quantity'],
                'unit' => $l['unit'], $d['prices_include_vat'] ? 'unit_price' : 'unit_price_ht' => $l['unit_price'], 'vat_rate' => $l['vat_rate'],
            ], fn ($v) => $v !== null), $d['lines']),
            'notes'              => "Déposé dans l'orchestrateur par {$admin->name} (document #{$event->id}).",
        ];

        $check = Validator::make($payload, (new PurchaseImportRequest())->rules());
        if ($check->fails()) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => "Le contrôle de forme refuse cette facture : " . $check->errors()->first() . "\nSaisissez-la avec l'import de facture."];
        }

        $res = $this->purchases->handle($payload, true, $admin->id);
        if ($res['status'] !== 'valid') {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => "Le brouillon d'achat est bloqué par les contrôles :\n" . $this->issues($res['errors'])];
        }

        $new = $res['preview']['to_create'] ?? [];
        $text = 'Contrôle à blanc réussi : ' . count($d['lines']) . ' ligne(s), total TTC ' . $this->money($res['preview']['totals']['ttc'] ?? $d['totals']['ttc'])
            . '. Fournisseur ' . (($new['supplier'] ?? null) ? 'à créer (' . ($supplier['name'] ?? $supplier['ice']) . ')' : 'reconnu')
            . (($new['products'] ?? []) ? ', ' . count($new['products']) . ' produit(s) à créer' : '')
            . '. Le brouillon laisse le stock en attente : rien n\'entre en stock avant votre confirmation.'
            . ($res['warnings'] ? "\nÀ noter :\n" . $this->issues($res['warnings']) : '');

        return [
            'action' => 'purchase', 'text' => $text, 'links' => $links, 'data' => ['payload' => $payload],
            'suggestions' => [['label' => "Oui, préparer le brouillon d'achat", 'text' => "prépare le brouillon d'achat du document #{$event->id}"]],
        ];
    }

    /**
     * Quand le modèle n'a lu aucun taux de TVA sur les lignes mais que les totaux imprimés en donnent un
     * sans ambiguïté (TVA ÷ HT = un taux légal exact), tous les articles ont ce taux. Ce n'est pas une
     * supposition : c'est ce que dit le pied de facture. Un seul taux lu sur une ligne, ou un rapport qui
     * ne tombe pas sur un taux légal (facture à taux mixtes), et rien n'est complété — le contrôle
     * d'import, qui recalcule les totaux, tranche alors.
     *
     * @return array<int, array<string, mixed>>
     */
    private function withUniformVatRate(array $d): array
    {
        $lines = $d['lines'];
        $ht = $d['totals']['ht'];
        $tva = $d['totals']['tva'];
        if ($lines === [] || $ht === null || $ht <= 0 || $tva === null || count(array_filter($lines, fn ($l) => $l['vat_rate'] !== null)) > 0) {
            return $lines;
        }

        $rate = round($tva / $ht * 100, 1);
        if (!in_array($rate, [0.0, 7.0, 10.0, 14.0, 20.0], true)) {
            return $lines;
        }

        return array_map(fn ($l) => ['vat_rate' => (int) $rate] + $l, $lines);
    }

    private function proposeSale(AgentEvent $event, array $d, User $admin): array
    {
        $links = [['label' => 'Messagerie commandes', 'to' => '/ventes/messagerie'], ['label' => 'Clients', 'to' => '/customers']];
        $hint = $d['party']['phone'] ?: $d['party']['name'];
        if (!$hint) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => "Je n'ai pas pu lire pour quel client est cette commande."];
        }
        if ($d['lines'] === []) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => "Je n'ai pas pu lire les articles commandés."];
        }

        $found = $this->customers->byHint($hint);
        if ($found->count() === 0 && $d['party']['name'] && $d['party']['name'] !== $hint) {
            $found = $this->customers->byHint($d['party']['name']);
        }
        if ($found->count() !== 1) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [],
                'text' => $found->isEmpty() ? "Client « {$hint} » introuvable parmi vos clients : je ne crée pas de brouillon pour un client inconnu."
                    : "Plusieurs clients correspondent à « {$hint} » : " . $found->map(fn ($c) => "{$c->tp_code} ({$c->tp_title})")->take(4)->implode(', ') . '.'];
        }
        $customer = $found->first();

        $payload = [
            'external_id' => 'ORC-' . $event->id,
            'customer'    => array_filter(['phone' => $customer->tp_phone ?: null, 'code' => $customer->tp_code ?: null, 'name' => $customer->tp_title ?: null]),
            'lines'       => array_map(fn ($l) => array_filter(['query' => $l['sku'] ?: $l['designation'], 'quantity' => $l['quantity'], 'unit' => $l['unit']], fn ($v) => $v !== null), $d['lines']),
            'notes'       => "Déposé dans l'orchestrateur par {$admin->name} (document #{$event->id}).",
        ];
        $check = Validator::make($payload, WhatsAppOrderImportRequest::payloadRules());
        if ($check->fails()) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => 'Le contrôle de forme refuse cette commande : ' . $check->errors()->first()];
        }

        $res = $this->sales->handle($payload, true, $admin->id, $customer, 'Document déposé');
        if ($res['status'] !== 'valid') {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => "Le brouillon de livraison est bloqué par les contrôles :\n" . $this->issues($res['errors'])];
        }

        $text = "Client reconnu : {$customer->tp_title} ({$customer->tp_code}). " . count($d['lines']) . ' ligne(s), total ' . $this->money($res['preview']['totals']['ttc'] ?? null)
            . '. Ce sera un brouillon de bon de livraison, stock en attente'
            . (($res['preview']['target_document']['reference'] ?? null) ? ' ; il s\'ajoutera au brouillon du jour ' . $res['preview']['target_document']['reference'] : '') . '.'
            . ($res['warnings'] ? "\nÀ noter :\n" . $this->issues($res['warnings']) : '');

        return [
            'action' => 'sale', 'text' => $text, 'links' => $links, 'data' => ['payload' => $payload, 'customer_id' => $customer->id],
            'suggestions' => [['label' => 'Oui, préparer le brouillon de livraison', 'text' => "prépare le brouillon de livraison du document #{$event->id}"]],
        ];
    }

    /**
     * Un document établi par l'entreprise elle-même (bon de livraison, facture de vente, devis) n'est pas une
     * commande à traiter : on regarde s'il existe déjà dans O3 sous cette référence, et on ne le recrée jamais.
     */
    private function describeIssued(array $d): array
    {
        $links = [['label' => 'Documents de vente', 'to' => '/ventes/documents']];
        $ref = $d['reference'];
        $found = $ref ? DocumentHeader::where('reference', $ref)->first() : null;

        if ($found) {
            return ['action' => 'known', 'suggestions' => [], 'links' => [['label' => "Ouvrir {$found->reference}", 'to' => "/ventes/documents/{$found->id}"]],
                'text' => "C'est un document que vous avez émis, et il existe déjà dans O3 : {$found->reference} (statut {$found->status}). Il n'y a rien à faire."];
        }

        return ['action' => 'none', 'suggestions' => [], 'links' => $links,
            'text' => "C'est un document émis par votre entreprise" . ($ref ? " ({$ref})" : '') . ", pas une commande reçue : je ne le recrée pas à partir d'un PDF."
                . ($ref ? " Je ne le trouve pas dans O3 sous cette référence ; s'il manque, saisissez-le dans Documents de vente." : '')];
    }

    private function proposePayment(AgentEvent $event, array $d): array
    {
        $p = $d['payment'];
        $links = [['label' => 'Trésorerie', 'to' => '/treasury']];
        $what = collect([
            $p['method'] ? ['cheque' => 'chèque', 'virement' => 'virement', 'especes' => 'espèces', 'carte' => 'carte', 'autre' => 'paiement'][$p['method']] : 'paiement',
            $p['amount'] !== null ? $this->money($p['amount']) : null,
            $p['direction'] === 'recu' ? 'reçu' : ($p['direction'] === 'emis' ? 'émis' : null),
            $d['party']['name'] ? 'de/à ' . $d['party']['name'] : null,
            $p['date'] ? "du {$p['date']}" : null,
        ])->filter()->implode(' · ');

        $text = "J'ai lu : {$what}.";
        if ($p['amount'] === null) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => $text . "\nJe n'ai pas pu lire le montant : je ne peux pas retrouver la facture concernée."];
        }

        $types = $p['direction'] === 'emis' ? ['InvoicePurchase'] : ($p['direction'] === 'recu' ? ['InvoiceSale'] : ['InvoiceSale', 'InvoicePurchase']);
        $candidates = DocumentHeader::with(['thirdPartner:id,tp_title', 'footer'])
            ->whereIn('document_type', $types)
            ->where('status', '!=', 'draft')
            ->whereHas('footer', fn ($q) => $q->where('amount_due', '>', 0)->whereBetween('amount_due', [$p['amount'] - 0.01, $p['amount'] + 0.01]))
            ->limit(3)->get();

        $text .= $candidates->isEmpty()
            ? "\nAucune facture impayée ne correspond exactement à ce montant."
            : "\nFactures impayées de ce montant exact : " . $candidates->map(fn ($c) => "{$c->reference} ({$c->thirdPartner?->tp_title})")->implode(', ') . '.';
        $text .= "\nJe n'enregistre jamais un paiement moi-même : faites-le dans la trésorerie.";

        return ['action' => 'payment', 'text' => $text, 'links' => $links, 'suggestions' => []];
    }

    private function proposePhoto(AgentEvent $event, array $d, string $mime): array
    {
        $links = [['label' => 'Produits', 'to' => '/products']];
        if (!str_starts_with($mime, 'image/')) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => "Ce n'est pas une image : je ne peux pas la rattacher à un produit."];
        }
        $name = $d['product_hint']['name'];
        $sku = $d['product_hint']['sku'];
        if (!$name && !$sku) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => "Je ne reconnais pas de façon sûre de quel produit il s'agit : dites-moi la référence (ex. « rattache la photo du document #{$event->id} au produit #12 »)."];
        }

        $query = Product::query()->select(['id', 'p_title', 'p_sku']);
        if ($sku && (clone $query)->where('p_sku', $sku)->exists()) {
            $query->where('p_sku', $sku);
        } else {
            $tokens = collect(preg_split('/\s+/', mb_strtolower((string) ($name ?? $sku))))->filter(fn ($t) => mb_strlen($t) >= 3)->take(3);
            if ($tokens->isEmpty()) {
                return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => "Je ne peux pas rapprocher cette photo d'un produit."];
            }
            foreach ($tokens as $t) {
                $query->where('p_title', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $t) . '%');
            }
        }
        $candidates = $query->limit(3)->get();

        if ($candidates->isEmpty()) {
            return ['action' => 'none', 'links' => $links, 'suggestions' => [], 'text' => 'Je ne trouve aucun produit correspondant à « ' . ($name ?? $sku) . ' » dans le catalogue.'];
        }

        return [
            'action' => 'photo', 'links' => $links, 'data' => ['candidates' => $candidates->pluck('id')->all()],
            'text' => 'Produit(s) possible(s) : ' . $candidates->map(fn ($p) => "{$p->p_title} ({$p->p_sku}, #{$p->id})")->implode(' ; ') . '. Rien n\'est rattaché sans votre choix.',
            'suggestions' => $candidates->map(fn ($p) => ['label' => 'Rattacher à ' . mb_strimwidth($p->p_title, 0, 40, '…'), 'text' => "rattache la photo du document #{$event->id} au produit #{$p->id}"])->all(),
        ];
    }

    // ── Actions confirmées par l'administrateur ──────────────────────

    /**
     * « prépare le brouillon d'achat du document #12 », « ignore le document #12 », etc. La phrase est
     * celle d'un bouton proposé par l'orchestrateur ; l'action n'est faite que si elle correspond à la
     * proposition enregistrée pour ce document.
     *
     * @param string $n phrase normalisée (sans accent, en minuscules)
     * @return array{body: string, meta: array<string, mixed>}
     */
    public function act(User $admin, int $eventId, string $n): array
    {
        $event = AgentEvent::where('type', 'document_depose')->find($eventId);
        if (!$event) {
            return $this->reply("Je ne trouve pas le document #{$eventId}.", error: true);
        }

        $proposal = $event->payload['proposal'] ?? [];
        $name = $event->payload['file']['name'] ?? "#{$eventId}";
        $wantsIgnore = (bool) preg_match('/ignor|annul|abandon/', $n);
        $wantsAction = $wantsIgnore || (bool) preg_match('/prepar|rattach|lance|cree|confirm/', $n);

        if (!$wantsAction) {
            return $this->reply("Document #{$eventId} « {$name} » : " . ($event->payload['reading']['summary'] ?? 'non lu') . "\nÉtat : " . $this->statusLabel($event) . '.', eventId: $eventId);
        }
        if (in_array($event->status, [AgentEvent::STATUS_DONE, AgentEvent::STATUS_REJECTED], true)) {
            return $this->reply("Le document #{$eventId} a déjà été traité (" . $this->statusLabel($event) . ').', eventId: $eventId);
        }
        if ($wantsIgnore) {
            $this->update($event, AgentEvent::STATUS_REJECTED, ['dismissed_by' => $admin->name]);

            return $this->reply("C'est noté : le document #{$eventId} est ignoré. Le fichier reste conservé.", eventId: $eventId);
        }

        try {
            if (preg_match('/rattach|photo/', $n) && preg_match('/produit\s*#?\s*(\d+)/', $n, $m)) {
                return $this->attachPhoto($event, $proposal, (int) $m[1]);
            }
            if (preg_match('/achat/', $n)) {
                return $this->createPurchase($event, $proposal, $admin);
            }
            if (preg_match('/livraison|\bbl\b/', $n)) {
                return $this->createSale($event, $proposal, $admin);
            }
        } catch (\Throwable $e) {
            Log::error("Document #{$eventId} : action non exécutée : {$e->getMessage()}");

            return $this->reply("L'action n'a pas pu être exécutée sur le document #{$eventId} : aucune écriture n'a été validée.", error: true, eventId: $eventId);
        }

        return $this->reply("Je ne sais pas quelle action faire sur le document #{$eventId}. Utilisez les boutons proposés après la lecture.", eventId: $eventId);
    }

    private function createPurchase(AgentEvent $event, array $proposal, User $admin): array
    {
        if (($proposal['action'] ?? null) !== 'purchase') {
            return $this->reply("Aucun brouillon d'achat n'est proposé pour le document #{$event->id}.", error: true, eventId: $event->id);
        }

        $res = $this->purchases->handle($proposal['payload'], false, $admin->id);
        if (!in_array($res['status'], ['created', 'already_imported'], true)) {
            return $this->reply("Le brouillon d'achat n'a pas été créé :\n" . $this->issues($res['errors']), error: true, eventId: $event->id);
        }

        $doc = $res['document'] ?? [];
        $this->update($event, AgentEvent::STATUS_DONE, ['result' => ['document_id' => $doc['id'] ?? null, 'reference' => $doc['reference'] ?? null]]);

        return $this->reply(
            ($res['status'] === 'already_imported' ? 'Ce document avait déjà été importé' : "Brouillon d'achat créé") . ($doc ? " : {$doc['reference']}" : '')
            . ".\nIl est en brouillon et le stock est en attente : vérifiez-le puis confirmez-le dans Documents d'achat.",
            links: isset($doc['id']) ? [['label' => "Ouvrir le brouillon {$doc['reference']}", 'to' => "/achats/documents/{$doc['id']}"]] : [],
            eventId: $event->id,
        );
    }

    private function createSale(AgentEvent $event, array $proposal, User $admin): array
    {
        if (($proposal['action'] ?? null) !== 'sale') {
            return $this->reply("Aucun brouillon de livraison n'est proposé pour le document #{$event->id}.", error: true, eventId: $event->id);
        }

        $customer = $this->customers->byHint((string) ($proposal['payload']['customer']['code'] ?? ''))->first();
        $res = $this->sales->handle($proposal['payload'], false, $admin->id, $customer, 'Document déposé');
        if (!in_array($res['status'], ['created', 'already_imported'], true)) {
            return $this->reply("Le brouillon de livraison n'a pas été créé :\n" . $this->issues($res['errors']), error: true, eventId: $event->id);
        }

        $doc = $res['document'] ?? [];
        $this->update($event, AgentEvent::STATUS_DONE, ['result' => ['document_id' => $doc['id'] ?? null, 'reference' => $doc['reference'] ?? null]]);

        return $this->reply(
            ($res['status'] === 'already_imported' ? 'Cette commande avait déjà été importée' : (($doc['appended'] ?? false) ? 'Commande ajoutée au brouillon du jour' : 'Brouillon de bon de livraison créé')) . ($doc ? " : {$doc['reference']}" : '')
            . ".\nIl est en brouillon et le stock est en attente : vérifiez-le puis confirmez-le dans Documents de vente.",
            links: isset($doc['id']) ? [['label' => "Ouvrir le brouillon {$doc['reference']}", 'to' => "/ventes/documents/{$doc['id']}"]] : [],
            eventId: $event->id,
        );
    }

    private function attachPhoto(AgentEvent $event, array $proposal, int $productId): array
    {
        // Seuls les produits proposés à la lecture peuvent recevoir la photo.
        if (($proposal['action'] ?? null) !== 'photo' || !in_array($productId, $proposal['candidates'] ?? [], true)) {
            return $this->reply("Le produit #{$productId} ne fait pas partie des produits proposés pour ce document.", error: true, eventId: $event->id);
        }
        $product = Product::find($productId);
        $file = $event->payload['file'] ?? [];
        $path = Storage::disk('local')->path($file['path'] ?? '');
        if (!$product || !is_file($path)) {
            return $this->reply('Le produit ou le fichier est introuvable.', error: true, eventId: $event->id);
        }

        $this->images->upload($product, new UploadedFile($path, $file['name'], $file['mime'], null, true));
        $this->update($event, AgentEvent::STATUS_DONE, ['result' => ['product_id' => $product->id]]);

        return $this->reply("Photo rattachée à « {$product->p_title} ». Vous pouvez la retirer ou la définir comme principale dans la fiche produit.", links: [['label' => 'Produits', 'to' => '/products']], eventId: $event->id,
            suggestions: [['label' => 'Produits suivants sans photo', 'text' => 'quels produits sont sans photo']]);
    }

    // ── Outils ───────────────────────────────────────────────────────

    private function update(AgentEvent $event, string $status, array $payload = [], ?Agent $agent = null): void
    {
        $event->update(array_filter([
            'status'   => $status,
            'payload'  => array_merge($event->payload ?? [], $payload),
            'agent_id' => $agent?->id,
        ], fn ($v) => $v !== null));
    }

    private function agentFor(string $action): ?Agent
    {
        $domain = ['purchase' => 'achats', 'sale' => 'ventes'][$action] ?? null;

        return $domain ? Agent::where('domain', $domain)->first() : null;
    }

    private function statusLabel(AgentEvent $e): string
    {
        return [
            AgentEvent::STATUS_DONE     => 'traité',
            AgentEvent::STATUS_REJECTED => 'ignoré',
            AgentEvent::STATUS_TO_SORT  => 'à trier',
            AgentEvent::STATUS_ROUTED   => 'en attente de votre décision',
        ][$e->status] ?? $e->status;
    }

    /** @param array<int, array<string, mixed>> $issues */
    private function issues(array $issues): string
    {
        return collect($issues)->take(4)->map(fn ($i) => '– ' . ($i['message'] ?? json_encode($i)))->implode("\n");
    }

    private function money(?float $amount): string
    {
        return $amount === null ? '—' : number_format($amount, 2, ',', ' ') . ' MAD';
    }

    /** @param array<int, array{label: string, to: string}> $links */
    private function uniqueLinks(array $links): array
    {
        return array_values(collect($links)->unique('to')->all());
    }

    /**
     * @param array<int, array{label: string, text: string}> $suggestions
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, array $links = [], bool $error = false, ?int $eventId = null, array $suggestions = []): array
    {
        return ['body' => $body, 'meta' => array_filter([
            'intent'      => 'documents',
            'links'       => $links ?: null,
            'error'       => $error ?: null,
            'event_id'    => $eventId,
            'suggestions' => $suggestions ?: null,
        ], fn ($v) => $v !== null)];
    }
}
