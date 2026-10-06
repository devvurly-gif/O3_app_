<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentCase;
use App\Models\AgentEvent;
use App\Models\OrchestratorMessage;
use App\Models\PaymentReminder;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Str;

/**
 * L'orchestrateur : le « chef » à qui un administrateur parle. Il comprend une
 * liste définie de demandes en français (règles, sans modèle de langage), les
 * confie aux agents, et répond avec ce qui s'est passé.
 *
 * Garde-fous :
 *  - il ne peut déclencher que des ordres qui existent (AgentOrderService) : des
 *    brouillons, jamais une écriture irréversible ;
 *  - une simple question ne déclenche rien : il faut un verbe d'action
 *    (« prépare », « lance », « contrôle »…) pour qu'un ordre parte ;
 *  - la validation humaine (relances, application des écarts d'inventaire) reste
 *    dans les écrans dédiés, vers lesquels il renvoie ;
 *  - ce qu'il ne comprend pas, il le dit et rappelle ce qu'il sait faire.
 */
class Orchestrator
{
    public function __construct(
        private AgentOrderService $orders,
        private OrchestratorInterpreter $interpreter,
        private DocumentIntake $intake,
        private CatalogAssistant $catalog,
        private AgentStudio $studio,
        private AgentInterview $interview,
        private BusinessAssistant $business,
        private InsightsAssistant $insights,
        private OperationsAssistant $operations,
        private AnalysisAssistant $analysis,
        private DeepDiveAssistant $deepDive,
        private ExplorerAssistant $explorer,
        private ExtrasAssistant $extras,
        private OversightAssistant $oversight,
        private MentionResolver $mentions,
    ) {
    }

    /**
     * Exécute une demande connue pour le compte d'un administrateur, sans écrire dans sa conversation : c'est ce
     * qu'utilisent les routines planifiées. Les mêmes règles, les mêmes garde-fous que le chat.
     *
     * @return array{body: string, meta: array<string, mixed>}
     */
    public function runCommand(User $admin, string $text): array
    {
        return $this->answer($admin, trim($text));
    }

    /** @return array{user: OrchestratorMessage, reply: OrchestratorMessage} */
    public function converse(User $admin, string $text): array
    {
        $text = trim($text);
        $user = OrchestratorMessage::create(['user_id' => $admin->id, 'role' => OrchestratorMessage::ROLE_ADMIN, 'body' => $text]);

        $answer = $this->interviewTurn($admin, $text) ?? $this->answer($admin, $text);

        $reply = OrchestratorMessage::create([
            'user_id' => $admin->id,
            'role'    => OrchestratorMessage::ROLE_ORCHESTRATOR,
            'body'    => $answer['body'],
            'meta'    => $answer['meta'],
        ]);

        return ['user' => $user, 'reply' => $reply];
    }

    /**
     * L'entretien de conception, s'il y en a un d'ouvert (la réponse de l'administrateur lui revient, sauf s'il
     * valide ou ignore une proposition) ou si la phrase en ouvre un. Seulement dans le chat : les routines
     * planifiées (runCommand) ne passent jamais par là.
     *
     * @return array{body: string, meta: array<string, mixed>}|null
     */
    private function interviewTurn(User $admin, string $text): ?array
    {
        $n = $this->normalize($text);
        if (preg_match('/\b(proposition|lot|routine|agent|document|demande|consigne)\s*#\s*\d+/', $n)) {
            return null;
        }
        if ($open = $this->interview->open($admin)) {
            return $this->interview->answer($admin, $open, $text, $n);
        }
        if (preg_match('/^(discutons|parlons|on discute|aide moi a (definir|concevoir|creer|etablir|organiser)|j.ai une idee|ouvre (l.atelier|un entretien)|concois|j.aimerais automatiser|j.ai besoin d.automatiser|entretien de conception)\b/', $n)) {
            return $this->interview->start($admin, $text);
        }

        return null;
    }

    /** @return array{body: string, meta: array<string, mixed>} */
    private function answer(User $admin, string $text): array
    {
        $n = $this->normalize($text);
        $action = $this->wantsAction($n);

        return match (true) {
            // Un document déposé (« prépare le brouillon d'achat du document #12 », « ignore le document #12 »…).
            (bool) preg_match('/\bdocuments?\s*#?\s*(\d+)/', $n, $doc)                            => $this->intake->act($admin, (int) $doc[1], $n),
            // Atelier des agents : recruter, planifier des routines, retenir des consignes, catalogue des tâches.
            ($studio = $this->studioIntent($n)) !== null                                          => $this->studioAnswer($admin, $studio, $text, $n),
            // Questions courantes formulées naturellement (« qui me doit de l'argent ? », « stock faible », « ça va ? ») : lectures.
            ($quick = $this->quickIntent($n)) !== null                                            => $this->quickAnswer($quick, $n),
            // Promotions d'un produit, règles et seuils des agents, notifications, entrepôts, listes de prix : lectures, aucun modèle de langage.
            ($ovs = $this->oversightIntent($n)) !== null                                          => $this->oversightAnswer($ovs, $n, $admin),
            // Messagerie, relances, droits, chèques, bannières, terminaux, variantes, prix par liste : lectures, aucun modèle de langage.
            ($ext = $this->extrasIntent($n)) !== null                                             => $this->extrasAnswer($ext, $n),
            // Recherche, documents d'un tiers, mouvements d'un produit, brouillons oubliés, comparaisons : lectures, aucun modèle de langage.
            ($exp = $this->explorerIntent($n)) !== null                                           => $this->explorerAnswer($exp, $n),
            // Fiches (produit, tiers, document), marge réalisée, tendances, prévisions : lectures, aucun modèle de langage.
            ($dd = $this->deepDiveIntent($n)) !== null                                           => $this->deepDiveAnswer($dd, $n),
            // Classements, qualité du catalogue, historique d'une fiche, actions des agents : lectures, aucun modèle de langage.
            ($ana = $this->analysisIntent($n)) !== null                                           => $this->analysisAnswer($ana, $n),
            // Lectures sur l'activité de l'entreprise (ventes, factures échues, trésorerie, caisse…) : aucun modèle de langage.
            ($biz = $this->businessIntent($n)) !== null                                           => $this->businessAnswer($biz, $n),
            ($ins = $this->insightsIntent($n)) !== null                                          => $this->insightsAnswer($ins, $n),
            ($ops = $this->operationsIntent($n)) !== null                                        => $this->operationsAnswer($ops, $n),
            ($men = $this->mentionAnswer($n)) !== null                                            => $men,
            (bool) preg_match('/inventaire|comptage/', $n)                                        => $this->inventory($admin, $n, $action),
            (bool) preg_match('/encaissement|impaye|recouvrement|relance|paiements? en retard/', $n) => $this->collections($admin, $n, $action),
            // Fiches produits : contrôle, propositions (IA, prix, photos) et leur validation (« lot #12 »).
            ($fiches = $this->catalogIntent($n)) !== null                                         => $this->catalogAnswer($admin, $fiches, $n),
            (bool) preg_match('/\ba trier\b|non classe|evenement|messages? recus?/', $n)          => $this->toSort(),
            (bool) preg_match('/que (peut|peu)[- ]on|fonctionnalites?|\bfonctions?\b|\bmodules?\b|\becrans?\b|\bmenu\b|dans o3|possibilites/', $n) => $this->capabilities(),
            AppCatalog::match($n) !== [] && $this->looksLikeNavigation($n)                       => $this->navigate($n, AppCatalog::match($n)),
            (bool) preg_match('/\b(etat|statut|situation|bilan|resume|point|agents?|orchestr|ou en)\b/', $n) => $this->status(),
            (bool) preg_match('/\b(aide|help|bonjour|salut|bonsoir|coucou|que peux|que sais|commandes?)\b/', $n) => $this->help(true),
            default                                                                               => $this->freeText($admin, $text),
        };
    }

    // ── Phrase libre (renfort par un modèle de langage, si activé) ───

    /**
     * Les règles n'ont rien compris. Si la compréhension avancée est activée, le modèle range la
     * phrase dans une demande connue ; sinon (ou en cas d'échec) l'aide habituelle s'affiche.
     * Une demande de lecture est traitée directement ; un ordre est seulement PROPOSÉ : il ne part
     * que sur le clic de l'administrateur, qui envoie la phrase canonique traitée par les règles.
     */
    private function freeText(User $admin, string $text): array
    {
        $titles = Warehouse::where('wh_status', true)->pluck('wh_title')->all();
        $r = $this->interpreter->interpret($text, $titles);

        if ($r === null) {
            $why = $this->interpreter->failure();

            // IA activée mais en échec : on le dit (la phrase n'est pas « incomprise », elle n'a pas pu être lue).
            if ($why) {
                return $this->help(false, "La compréhension avancée est indisponible : {$why}.");
            }

            return $this->understood($text);
        }

        $answer = match ($r['intent']) {
            'etat'            => $this->status(),
            'a_trier'         => $this->toSort(),
            'inventaire_etat' => $this->inventory($admin, '', false),
            'relances_etat'   => $this->collections($admin, '', false),
            'inventaire_ordre' => $this->propose(
                'préparer un inventaire' . ($r['warehouse'] ? " de l'entrepôt « {$r['warehouse']} »" : ' de tous les entrepôts') . ($r['scope'] === 'attention' ? ', limité aux articles à vérifier' : ''),
                'inventory',
                'prépare un inventaire' . ($r['warehouse'] ? " du {$r['warehouse']}" : '') . ($r['scope'] === 'attention' ? ' des articles à vérifier' : ''),
            ),
            'encaissements_ordre' => $this->propose('contrôler les encaissements et préparer les relances', 'collections', 'contrôle les encaissements'),
            'aide'            => $this->help(true),
            'fonctions'       => $this->capabilities(),
            'fiches_controle' => $this->catalog->audit($admin),
            'lecture'         => ($read = $r['phrase'] ? $this->readAnswer($this->normalize($r['phrase']), $admin) : null) !== null ? $read : $this->understood($text),
            'ecran'           => $r['screen'] ? $this->navigate($this->normalize($text), [$r['screen']]) : $this->understood($text),
            default           => $this->understood($text),
        };

        $answer['meta']['ai'] = true;

        return $answer;
    }

    /** Un ordre déduit d'une phrase libre n'est jamais lancé seul : on propose, l'administrateur confirme. */
    private function propose(string $what, string $intent, string $command): array
    {
        return $this->reply(
            "J'ai compris que vous voulez {$what}. Un ordre ne part que sur une demande explicite : le confirmez-vous ?",
            $intent . '_proposal',
            suggestions: [['label' => 'Oui, ' . $command, 'text' => $command]],
        );
    }

    // ── Demandes ─────────────────────────────────────────────────────

    private function help(bool $greeting, ?string $warning = null): array
    {
        $intro = $warning !== null
            ? "{$warning}\nJe ne peux donc traiter que ce que je comprends sans elle :"
            : ($greeting
                ? "Bonjour. Je suis l'orchestrateur : je reçois vos demandes et je les confie aux agents. Voici ce que je sais faire."
                : "Je n'ai pas compris cette demande. Voici ce que je sais faire.");

        return $this->reply(
            "{$intro}\n\n"
            . "• « état des agents » : la situation des agents, des événements et des validations en attente\n"
            . "• « événements à trier » : les messages que le routeur n'a su confier à personne\n"
            . "• « prépare un inventaire » : l'agent Stocks prépare la feuille à compter (ajoutez un nom d'entrepôt, ou « articles à vérifier » pour cibler)\n"
            . "• « contrôle les encaissements » : l'agent Recouvrement contrôle les paiements et prépare les relances\n"
            . "• « relances à valider » : ce qui attend votre validation\n"
            . "• « mettre à jour les fiches produits » : je contrôle les fiches (photos, descriptions, catégories, marques, prix, codes-barres) et je propose des corrections à valider ; « prépare les fiches pour l'utilisation » enchaîne toutes les étapes jusqu'à l'activation\n"
            . "• « que sait faire chaque agent » : le catalogue des tâches ; « recrute un agent qui… » ; « chaque lundi à 8 h, contrôle les encaissements » (routine) ; « retiens : … » (consigne) ; « crée les comptes des agents » ; « demande de développement : … » pour une tâche qui manque\n"
            . "• déposez une photo ou un PDF (trombone, ou glissez-le ici) : je lis le document, dis ce que c'est et propose la suite\n"
            . "• lectures sur l'activité : « résume la journée », « que dois-je valider ? », « chiffre d'affaires du mois », « factures échues », « devis sans suite depuis 10 jours », « bons de livraison non facturés », « encaissements du jour », « solde de chaque compte de trésorerie », « sessions de caisse », « valeur du stock », « produits dormants », « transferts en attente », « pertes du mois », « doublons de produits », « marge par catégorie », « produits jamais vendus », « clients inactifs depuis 60 jours », « clients qui dépassent leur seuil de crédit », « achats du mois par fournisseur », « factures fournisseurs à payer », « bons de commande en attente », « prix d'achat en hausse », « remises accordées ce mois », « dépenses du mois par catégorie », « dépenses sans justificatif », « activité récente », « promotions actives », « top 10 des produits vendus », « ventes du mois par vendeur », « meilleurs clients du trimestre », « qui a modifié la facture FV-001 », « codes-barres invalides », « actions des agents aujourd'hui », « fiche du produit PRC1 », « fiche du client Atlas », « montre la facture FV-001 », « marge réalisée du mois », « panier moyen », « évolution du chiffre d'affaires sur 6 mois », « produits bientôt en rupture », « prévision de trésorerie à 30 jours », « cherche perceuse », « factures du client Atlas », « mouvements du produit PRC1 », « brouillons anciens », « compare ce mois au mois dernier », « nouveaux clients du mois », « ventes par jour de la semaine », « permissions du rôle manager », « relances de paiement du mois », « commandes WhatsApp du jour », « chèques et effets reçus ce mois », « produits de la promotion rentrée », « règles de routage », « mes notifications non lues », « mes entrepôts », « listes de prix » ; ou posez simplement la question, par exemple « combien j'ai vendu hier ? » (chiffres lus directement dans la base, rien n'est modifié)
"
            . "• « que peut-on faire dans O3 » : tous les domaines de l'application ; ou nommez un écran (« les fiches produits », « créer une facture ») et je vous y envoie\n\n"
            . "Les agents préparent des brouillons. Rien n'est modifié ni envoyé sans votre validation, dans l'écran concerné.",
            'help',
            warning: $warning !== null,
        );
    }

    // ── Atelier des agents ───────────────────────────────────────────

    /** @return array{0: string, 1: ?int}|null l'intention de l'atelier et un éventuel numéro ; null si la phrase n'en relève pas */
    private function studioIntent(string $n): ?array
    {
        $id = fn (string $word) => preg_match('/\b' . $word . '\s*#\s*(\d+)/', $n, $m) ? (int) $m[1] : null;

        if (($e = $id('proposition')) !== null) {
            return ['act', $e];
        }
        if (($r = $id('routine')) !== null) {
            return match (true) {
                (bool) preg_match('/suspend|pause|arrete/', $n)    => ['routine_pause', $r],
                (bool) preg_match('/supprim|efface|retire/', $n)   => ['routine_delete', $r],
                (bool) preg_match('/reprend|reactiv|relance/', $n) => ['routine_resume', $r],
                (bool) preg_match('/lance|execut|demarre/', $n)    => ['routine_run', $r],
                default                                            => null,
            };
        }
        // « tous les jours à 8 h, lance l'agent #8 » est une routine à planifier, pas un lancement immédiat.
        if ($this->hasScheduleWords($n) && preg_match('/\bagent\s*#\s*\d+/', $n) && preg_match('/\b(lance|execut|control|prepar|surveill|verifi|fais|demande)\w*/', $n)) {
            return ['routine_new', null];
        }
        if (($a = $id('agent')) !== null) {
            return match (true) {
                (bool) preg_match('/desactiv|arrete|suspend|pause/', $n) => ['agent_off', $a],
                (bool) preg_match('/\bactiv/', $n)                       => ['agent_on', $a],
                (bool) preg_match('/lance|execut|travaille|demande/', $n) => ['agent_run', $a],
                default                                                  => null,
            };
        }
        if (($d = $id('consigne')) !== null && preg_match('/oubli|supprim|retir|efface/', $n)) {
            return ['directive_remove', $d];
        }
        if (($q = $id('demande')) !== null && preg_match('/faite|traitee|terminee|close/', $n)) {
            return ['dev_done', $q];
        }

        return match (true) {
            (bool) preg_match('/^(retiens|souviens|consigne|regle de la maison|desormais|note que|n oublie pas)\b/', $n) => ['directive_new', null],
            (bool) preg_match('/\bconsignes\b/', $n) && (bool) preg_match('/\b(mes|quelles|liste|les|affiche)\b|^consignes/', $n) => ['directive_list', null],
            (bool) preg_match('/demandes? de developpement/', $n) && (bool) preg_match('/\b(mes|quelles|liste|ouvertes|affiche)\b/', $n) => ['dev_list', null],
            (bool) preg_match('/demande de developpement|nouvelle (tache|fonction|capacite)|il faudrait que tu saches/', $n) => ['dev_new', null],
            (bool) preg_match('/catalogue des taches|taches connues|que (sait|savent|peut|peuvent)\b.*\bagents?|ce que (sait|savent).*agents?/', $n) => ['catalogue', null],
            // Comptes utilisateurs des agents (avant le recrutement : « crée les comptes des agents » n'est pas un recrutement).
            (bool) preg_match('/\bcomptes?\b/', $n) && (bool) preg_match('/\bagents?\b/', $n) && (bool) preg_match('/\b(cree|creer|ajoute|ajouter|etablis|etablir|prepare|preparer|donne|fournis|genere|generer)\b/', $n) => ['accounts_new', null],
            (bool) preg_match('/\bcomptes?\b/', $n) && (bool) preg_match('/\bagents?\b/', $n) => ['accounts_list', null],
            (bool) preg_match('/\bmes agents\b|\bagents? (recrutes|personnalises)\b|liste des agents/', $n) => ['agents', null],
            (bool) preg_match('/\b(recrut|embauch|engage)\w*|\bnouvel agent\b/', $n) || ((bool) preg_match('/\b(cree|creer|ajoute|ajouter)\b/', $n) && (bool) preg_match('/\bagent\b/', $n)) => ['recruit', null],
            (bool) preg_match('/\broutines?\b/', $n) && (bool) preg_match('/\b(mes|quelles|liste|les|affiche)\b/', $n) && !$this->hasScheduleWords($n) => ['routines', null],
            $this->hasScheduleWords($n) && (bool) preg_match('/\b(control|prepar|lance|verifi|fais|met|rappor|surveill|genere|inventaire|etat|resum|envoi|donn|montr|affich|bilan|point)\w*/', $n) => ['routine_new', null],
            default => null,
        };
    }

    private function hasScheduleWords(string $n): bool
    {
        return (bool) preg_match('/\b(chaque|quotidien\w*|hebdomadaire\w*|mensuel\w*|planifi\w*|programm\w*|routine)\b|\btous les (jours|matins|lundis|mardis|mercredis|jeudis|vendredis|samedis|dimanches|mois)\b|\btoutes les semaines\b/', $n);
    }

    /** @param array{0: string, 1: ?int} $intent */
    private function studioAnswer(User $admin, array $intent, string $text, string $n): array
    {
        [$what, $id] = $intent;

        return match ($what) {
            'act'              => $this->studio->act($admin, (int) $id, $n),
            'routine_pause'    => $this->studio->routineAction($admin, (int) $id, 'pause'),
            'routine_delete'   => $this->studio->routineAction($admin, (int) $id, 'delete'),
            'routine_resume'   => $this->studio->routineAction($admin, (int) $id, 'resume'),
            'routine_run'      => $this->studio->routineAction($admin, (int) $id, 'run'),
            'agent_off'        => $this->studio->toggleAgent($admin, (int) $id, false),
            'agent_on'         => $this->studio->toggleAgent($admin, (int) $id, true),
            'agent_run'        => $this->studio->runAgent($admin, (int) $id, $text),
            'directive_remove' => $this->studio->directiveRemove($admin, (int) $id),
            'dev_done'         => $this->studio->devRequestDone((int) $id),
            'directive_new'    => $this->studio->directiveNew($admin, $text),
            'directive_list'   => $this->studio->directives(),
            'dev_list'         => $this->studio->devRequests(),
            'dev_new'          => $this->studio->devRequest($admin, $text),
            'catalogue'        => $this->studio->catalogue(),
            'agents'           => $this->studio->agents(),
            'accounts_new'     => $this->studio->accountsNew($admin),
            'accounts_list'    => $this->studio->accountsList(),
            'recruit'          => $this->studio->recruit($admin, $text),
            'routines'         => $this->studio->routines(),
            default            => $this->studio->routineNew($admin, $text),
        };
    }

    // ── Lectures sur l'activité (BusinessAssistant) ──────────────────

    /** Quelle lecture la phrase demande-t-elle ? null si aucune. @param string $n phrase normalisée */
    private function businessIntent(string $n): ?string
    {
        $period = (bool) preg_match('/\b(jour|journee|aujourd\w*|semaine|mois|hier|annee)\b|derniers? jours|\d+\s*jours/', $n);
        $order = (bool) preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|routine|agent|lance\w*)\b/', $n);

        return match (true) {
            (bool) preg_match('/\b(resume\w*|bilan|point|etat)\b.*\b(journee|du jour|aujourd\w*)|^(le )?point (du jour|de la journee)|ma journee/', $n) => 'day',
            (bool) preg_match('/que dois[- ]je valider|ce que je dois valider|en attente de validation|propositions? en attente|\ba valider\b/', $n) && !preg_match('/relance|document/', $n) => 'pending',
            !$order && (bool) preg_match('/chiffre d.affaires|\bca\b/', $n) || (!$order && $period && (bool) preg_match('/\bventes?\b|combien .*vendu/', $n)) => 'sales',
            !$order && (bool) preg_match('/factures? (clients? )?(echue|en retard|impayee)s?|echeances? depassee|impayes? depuis|factures? depassant/', $n) => 'overdue',
            (bool) preg_match('/\bdevis\b/', $n) && (bool) preg_match('/sans suite|sans reponse|en attente|non (transforme|converti)|ouverts?/', $n) => 'quotes',
            (bool) preg_match('/bons? de livraison|\bbl\b/', $n) && (bool) preg_match('/non factur|pas (encore )?factur|a facturer|sans facture/', $n) => 'deliveries',
            !$order && $period && (bool) preg_match('/encaissements?|paiements? recus?|combien .*encaisse/', $n) => 'income',
            (bool) preg_match('/\bsoldes?\b/', $n) && (bool) preg_match('/tresorerie|caisse|banque|comptes?/', $n) => 'balances',
            (bool) preg_match('/sessions? de caisse|caisses? ouvertes?|ecarts? de caisse/', $n) => 'sessions',
            default => null,
        };
    }

    private function businessAnswer(string $intent, string $n): array
    {
        return match ($intent) {
            'day'        => $this->business->daySummary(),
            'pending'    => $this->business->pendingValidations(),
            'sales'      => $this->business->sales($n),
            'overdue'    => $this->business->overdueInvoices($n),
            'quotes'     => $this->business->staleQuotes($n),
            'deliveries' => $this->business->unbilledDeliveries(),
            'income'     => $this->business->income($n),
            'balances'   => $this->business->balances(),
            default      => $this->business->cashSessions(),
        };
    }
    /** Quelle lecture sur le stock, le catalogue ou les tiers la phrase demande-t-elle ? null si aucune. @param string $n phrase normalisée */
    private function insightsIntent(string $n): ?string
    {
        if (preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|routine|agent|lance\w*|revis\w*|attribu\w*|active(?:r|z|ons)?)\b/', $n)) {
            return null;
        }
        $third = (bool) preg_match('/\b(clients?|fournisseurs?|tiers)\b/', $n);

        return match (true) {
            $third && (bool) preg_match('/inactifs?|dormants?|sans achat|(n.ont|ont) pas (achete|commande)|plus commande|pas commande/', $n) => 'inactive_customers',
            (bool) preg_match('/seuils? de credit|depass\w* (leur|le) (seuil|plafond)|plafond de credit/', $n) => 'credit',
            $third && (bool) preg_match('/doublons?/', $n) => 'dup_third',
            $third && (bool) preg_match('/sans (telephone|e-?mail|mail|ice)|(telephone|e-?mail|ice) manquants?|fiches? (incompletes?|a completer)/', $n) => 'incomplete_third',
            (bool) preg_match('/valeur du stock|stock valorise|valorisation du stock/', $n) => 'stock_value',
            (bool) preg_match('/stocks? negatifs?|quantites? negatives?|produits? (a|en) stock negatif/', $n) => 'negative',
            (bool) preg_match('/\bdormants?\b|sans mouvement|sans rotation/', $n) => 'dormant',
            (bool) preg_match('/transferts?/', $n) && (bool) preg_match('/attente|en cours|non (valide|recu|termine)/', $n) => 'transfers',
            (bool) preg_match('/mouvements? de stock|\bpertes?\b|\bcasse\b/', $n) => 'movements',
            (bool) preg_match('/doublons?/', $n) && (bool) preg_match('/produits?|articles?|fiches?|codes?|sku/', $n) => 'dup_products',
            (bool) preg_match('/\bmarges?\b/', $n) && (bool) preg_match('/\bpar (categorie|marque)|categories|marques/', $n) => 'margins',
            (bool) preg_match('/jamais vendus?|pas vendus?|ne se vendent pas|sans vente/', $n) => 'never_sold',
            default => null,
        };
    }

    private function insightsAnswer(string $intent, string $n): array
    {
        return match ($intent) {
            'inactive_customers' => $this->insights->inactiveCustomers($n),
            'credit'             => $this->insights->creditLimits(),
            'dup_third'          => $this->insights->duplicateThirdParties(),
            'incomplete_third'   => $this->insights->incompleteThirdParties($n),
            'stock_value'        => $this->insights->stockValue(),
            'negative'           => $this->insights->negativeStock(),
            'dormant'            => $this->insights->dormantStock($n),
            'transfers'          => $this->insights->pendingTransfers(),
            'movements'          => $this->insights->movements($n),
            'dup_products'       => $this->insights->duplicateProducts(),
            'margins'            => $this->insights->margins($n),
            default              => $this->insights->neverSold($n),
        };
    }
    /** Quelle lecture sur les achats, la trésorerie, l'activité, les utilisateurs, la boutique ou les promotions ? @param string $n phrase normalisée */
    private function operationsIntent(string $n): ?string
    {
        if (preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|routine|agent|lance\w*|revis\w*|attribu\w*|active(?:r|z|ons)?|publi\w*)\b/', $n)) {
            return null;
        }
        $supplier = (bool) preg_match('/fournisseurs?/', $n);

        return match (true) {
            $supplier && (bool) preg_match('/moins cher|meilleur prix|prix le plus bas|compar\w*/', $n) => 'cheapest',
            (bool) preg_match('/sans fournisseur/', $n) => 'no_supplier',
            (bool) preg_match('/prix d.achat/', $n) && (bool) preg_match('/hausse|augment|monte|grimpe/', $n) => 'price_up',
            (bool) preg_match('/bons? de commande/', $n) && (bool) preg_match('/attente|reception|non recus?|en cours|a recevoir/', $n) => 'purchase_orders',
            (bool) preg_match('/(factures?|echeances?)\s*(de )?(fournisseurs?|d.achat)|(a payer|echeances?).*fournisseurs?|fournisseurs?.*(a payer|echeances?)/', $n) => 'supplier_due',
            (bool) preg_match('/\bachats?\b/', $n) && (bool) preg_match('/\b(mois|semaine|jour|hier|annee|fournisseurs?)\b/', $n) => 'purchases',
            (bool) preg_match('/\bremises?\b/', $n) => 'discounts',
            (bool) preg_match('/prix de reference|sous (le|leur) prix (catalogue|de reference)/', $n) => 'below_ref',
            (bool) preg_match('/factures?.*annulee|annulations? de factures?/', $n) => 'cancelled',
            (bool) preg_match('/sans justificatifs?|justificatifs? manquants?/', $n) => 'no_receipt',
            (bool) preg_match('/recurren\w*|charges? fixes?/', $n) => 'recurrences',
            (bool) preg_match('/\bdepenses?\b/', $n) => 'expenses',
            (bool) preg_match('/activite (recente|de |des |d.)|dernieres? (modifications?|operations?|actions?)|qui a modifie/', $n) => 'activity',
            (bool) preg_match('/utilisateurs?/', $n) && (bool) preg_match('/inactifs?|par role|roles?|actifs|combien|liste/', $n) => 'users',
            (bool) preg_match('/(en ligne|boutique|website|site web)/', $n) && (bool) preg_match('/sans (stock|photo|image)|en rupture|incomplet/', $n) => 'online_gaps',
            (bool) preg_match('/promotions?/', $n) && (bool) preg_match('/actives?|en cours|termin|expir|finiss|quelles/', $n) => 'promotions',
            default => null,
        };
    }

    private function operationsAnswer(string $intent, string $n): array
    {
        return match ($intent) {
            'cheapest'        => $this->operations->cheapestSupplier($n),
            'no_supplier'     => $this->operations->productsWithoutSupplier(),
            'price_up'        => $this->operations->purchasePriceIncreases(),
            'purchase_orders' => $this->operations->pendingPurchaseOrders(),
            'supplier_due'    => $this->operations->supplierInvoicesDue($n),
            'purchases'       => $this->operations->purchasesBySupplier($n),
            'discounts'       => $this->operations->discounts($n),
            'below_ref'       => $this->operations->belowReferencePrice($n),
            'cancelled'       => $this->operations->cancelledInvoices($n),
            'no_receipt'      => $this->operations->expensesWithoutReceipt($n),
            'recurrences'     => $this->operations->upcomingRecurrences(),
            'expenses'        => $this->operations->expensesByCategory($n),
            'activity'        => $this->operations->recentActivity($n),
            'users'           => $this->operations->users(),
            'online_gaps'     => $this->operations->onlineGaps(),
            default           => $this->operations->promotions($n),
        };
    }
    /** Quel classement, quelle lecture de qualité ou d'historique la phrase demande-t-elle ? null si aucune. @param string $n phrase normalisée */
    private function analysisIntent(string $n): ?string
    {
        if (preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|lance\w*|revis\w*|attribu\w*|active(?:r|z|ons)?|publi\w*)\b/', $n)) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/qui a (modifie|change|supprime|cree|touche)|historique (de|du|des|d.)/', $n) => 'history',
            (bool) preg_match('/actions? des agents|journal des agents|ce que (les )?agents? (ont|a) fait/', $n) => 'agent_actions',
            (bool) preg_match('/fournisseurs?/', $n) && !preg_match('/clients?/', $n) && (bool) preg_match('/inactifs?|plus (achete|commande)|sans (achat|facture)|dormants?/', $n) => 'inactive_suppliers',
            (bool) preg_match('/clients? en compte|facturation (periodique|mensuelle)|a facturer ce mois/', $n) => 'account_customers',
            (bool) preg_match('/meilleurs? clients?|principaux clients|clients? .*plus (achete|rapporte)/', $n) => 'best_customers',
            (bool) preg_match('/\bventes?\b/', $n) && (bool) preg_match('/par (vendeur|utilisateur|caissier|commercial)/', $n) => 'by_seller',
            (bool) preg_match('/\bventes?\b|tickets?/', $n) && (bool) preg_match('/par (caisse|session|terminal)/', $n) => 'by_register',
            (bool) preg_match('/\btop\s*\d*\b|plus vendus?|meilleures? ventes?|meilleurs? produits?/', $n) && (bool) preg_match('/produits?|articles?|\btop\b|ventes?/', $n) => 'top_products',
            (bool) preg_match('/\btva\b/', $n) => 'vat',
            (bool) preg_match('/codes?[- ]?barres?|\bean\b/', $n) && (bool) preg_match('/invalides?|incorrects?|errones?|faux/', $n) => 'bad_ean',
            (bool) preg_match('/produits?/', $n) && (bool) preg_match('/par categorie/', $n) && !preg_match('/marge/', $n) => 'by_category',
            (bool) preg_match('/sans marque/', $n) => 'no_brand',
            (bool) preg_match('/liste de prix|liste tarifaire/', $n) && (bool) preg_match('/absents?|manquants?|sans|pas dans|oublies?/', $n) => 'pricelist_gap',
            (bool) preg_match('/mouvements?/', $n) && (bool) preg_match('/en attente|non appliques?|pending/', $n) => 'pending_moves',
            (bool) preg_match('/ajustements?/', $n) && (bool) preg_match('/inventaire|stock|recents?/', $n) => 'adjustments',
            default => null,
        };
    }

    private function analysisAnswer(string $intent, string $n): array
    {
        return match ($intent) {
            'history'            => $this->analysis->history($n),
            'agent_actions'      => $this->analysis->agentActions($n),
            'inactive_suppliers' => $this->analysis->inactiveSuppliers($n),
            'account_customers'  => $this->analysis->accountCustomersToInvoice(),
            'best_customers'     => $this->analysis->bestCustomers($n),
            'by_seller'          => $this->analysis->salesBySeller($n),
            'by_register'        => $this->analysis->salesByRegister($n),
            'top_products'       => $this->analysis->topProducts($n),
            'vat'                => $this->analysis->vatRates(),
            'bad_ean'            => $this->analysis->invalidBarcodes(),
            'by_category'        => $this->analysis->productsByCategory(),
            'no_brand'           => $this->analysis->productsWithoutBrand(),
            'pricelist_gap'      => $this->analysis->priceListGaps($n),
            'pending_moves'      => $this->analysis->pendingMovements(),
            default              => $this->analysis->inventoryAdjustments($n),
        };
    }
    /** Quelle fiche, quel indicateur ou quelle prévision la phrase demande-t-elle ? null si aucun. @param string $n phrase normalisée */
    private function deepDiveIntent(string $n): ?string
    {
        if (preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|lance\w*|revis\w*|attribu\w*|active(?:r|z|ons)?|publi\w*)\b/', $n)) {
            return null;
        }
        $sales = (bool) preg_match('/\bventes?\b|chiffre d.affaires|\bca\b/', $n);

        return match (true) {
            (bool) preg_match('/\b(montre|affiche|ouvre|detail|voir|donne)\w*\b.*\b(facture|devis|bon de \w+|commande|avoir|ticket|retour)\s+(?:n°\s*|no\s*|numero\s*)?[a-z0-9\-\/_.]*[0-9][a-z0-9\-\/_.]*\s*$/', $n) => 'doc_card',
            (bool) preg_match('/\b(fiche|solde|situation|infos?)\s+(du |de la |de l.|des )?(client|fournisseur)\s+\S/', $n) => 'third_card',
            (bool) preg_match('/\b(fiche|infos?|details?|stock|prix)\s+(du produit|de l.article|de la reference|du produit)\s+\S|^(quel est |donne[- ]moi |montre[- ]moi )?(le )?stock (de|du|d.)\s*(?!chaque|tous|toutes|l.entrepot|entrepot|depot|magasin|mon|ma|mes|la caisse)\S/', $n) => 'product_card',
            (bool) preg_match('/\bmarges?\b/', $n) && (bool) preg_match('/realisee?s?|reelles?|brutes?|globale|totale|du mois|de la semaine|de l.annee|du trimestre/', $n) && !preg_match('/par (categorie|marque)|\bprix\b/', $n) => 'margin',
            (bool) preg_match('/panier moyen|ticket moyen|montant moyen (d.une |des )?(vente|facture|ticket)s?/', $n) => 'basket',
            $sales && (bool) preg_match('/evolution|tendance|courbe|mois par mois|historique/', $n) => 'trend',
            $sales && (bool) preg_match('/par (categorie|marque)/', $n) => 'sales_group',
            (bool) preg_match('/devis/', $n) && (bool) preg_match('/transform|conversion|taux|convertis?|acceptes?|signes?/', $n) => 'quote_conversion',
            (bool) preg_match('/commandes? clients?/', $n) && (bool) preg_match('/attente|livrer|en cours|non livrees?/', $n) => 'customer_orders',
            (bool) preg_match('/\bretours?\b|\bavoirs?\b/', $n) && (bool) preg_match('/\b(mois|semaine|annee|trimestre|aujourd\w*|hier)\b|combien|liste/', $n) => 'returns',
            (bool) preg_match('/rupture/', $n) && (bool) preg_match('/bientot|prochains? jours|dans \d+ ?j|prevision|va manquer|vont manquer|menace|risque|couverture/', $n) => 'runout',
            (bool) preg_match('/tickets?/', $n) && (bool) preg_match('/annule/', $n) => 'cancelled_tickets',
            (bool) preg_match('/\bflux\b/', $n) && (bool) preg_match('/tresorerie|cash|argent/', $n) => 'cash_flow',
            (bool) preg_match('/prevision|projection|previsionnel|anticip/', $n) && (bool) preg_match('/tresorerie|cash|solde|argent/', $n) => 'cash_forecast',
            default => null,
        };
    }

    private function deepDiveAnswer(string $intent, string $n): array
    {
        return match ($intent) {
            'doc_card'         => $this->deepDive->documentCard($n),
            'third_card'       => $this->deepDive->thirdPartyCard($n),
            'product_card'     => $this->deepDive->productCard($n),
            'margin'           => $this->deepDive->realizedMargin($n),
            'basket'           => $this->deepDive->averageBasket($n),
            'trend'            => $this->deepDive->monthlyTrend($n),
            'sales_group'      => $this->deepDive->salesByGroup($n),
            'quote_conversion' => $this->deepDive->quoteConversion($n),
            'customer_orders'  => $this->deepDive->pendingCustomerOrders(),
            'returns'          => $this->deepDive->returns($n),
            'runout'           => $this->deepDive->runoutSoon($n),
            'cancelled_tickets' => $this->deepDive->cancelledTickets($n),
            'cash_flow'        => $this->deepDive->cashFlow($n),
            default            => $this->deepDive->cashForecast(),
        };
    }
    /** Quelle exploration (recherche, documents d'un tiers, comparaison…) la phrase demande-t-elle ? null si aucune. @param string $n phrase normalisée */
    private function explorerIntent(string $n): ?string
    {
        if (preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|lance\w*|revis\w*|attribu\w*|active(?:r|z|ons)?|publi\w*)\b/', $n)) {
            return null;
        }
        $party = (bool) preg_match('/\b(du|de la|de l.|des|d.|par le|par la|par)\s+(client|fournisseur|tiers)\s+\S/', $n);

        return match (true) {
            (bool) preg_match('/^(cherche|trouve|recherche)\b/', $n) && !preg_match('/photos?|images?|jadever|doublon|\bsans\b|jamais|fiches?|tous les|toutes les|inventaire|encaissements?|relances?/', $n) => 'search',
            $party && (bool) preg_match('/produits? achetes?|qu.a achete|ce que .* achete/', $n) => 'bought',
            $party && (bool) preg_match('/\b(factures?|devis|documents?|bons? de \w+|commandes?|achats?|avoirs?)\b/', $n) => 'third_docs',
            (bool) preg_match('/mouvements?\s+(du produit|de l.article|de la reference)/', $n) => 'moves',
            (bool) preg_match('/valeur du stock/', $n) && (bool) preg_match('/par categorie/', $n) => 'stock_cat',
            (bool) preg_match('/brouillons?/', $n) && (bool) preg_match('/anciens?|oublies?|de plus de|plus de \d+|en retard|vieux|stagn\w*|a confirmer|restent|trainent/', $n) => 'drafts',
            (bool) preg_match('/derniers? documents?|documents? recents?|derniere\w* creations?/', $n) => 'latest_docs',
            (bool) preg_match('/nouveaux? (clients?|fournisseurs?|produits?|articles?|tiers)|(clients?|produits?|articles?) (crees|ajoutes) (recemment|ce mois|cette semaine|aujourd)/', $n) => 'newcomers',
            (bool) preg_match('/compar\w*|par rapport (au|a la|a l)|versus|\bvs\b/', $n) && (bool) preg_match('/\b(mois|semaine|annee)\b|an dernier|l.an passe/', $n) && !preg_match('/fournisseur|\bprix\b/', $n) => 'compare',
            (bool) preg_match('/jours? de la semaine|par jour de (la )?semaine|quel jour.*(vend|plus)/', $n) => 'weekday',
            (bool) preg_match('/heures? (de pointe|d.affluence)|affluence|quelles? heures?/', $n) => 'peak',
            (bool) preg_match('/(clients?|fournisseurs?|tiers).*(par ville|villes)|villes? des (clients|tiers)/', $n) => 'by_city',
            (bool) preg_match('/dernieres? connexions?|qui s.est connecte|connexions? recentes?|derniere utilisation/', $n) => 'logins',
            default => null,
        };
    }

    private function explorerAnswer(string $intent, string $n): array
    {
        return match ($intent) {
            'search'      => $this->explorer->search($n),
            'bought'      => $this->explorer->productsBoughtBy($n),
            'third_docs'  => $this->explorer->thirdPartyDocuments($n),
            'moves'       => $this->explorer->productMovements($n),
            'stock_cat'   => $this->explorer->stockValueByCategory(),
            'drafts'      => $this->explorer->staleDrafts($n),
            'latest_docs' => $this->explorer->latestDocuments(),
            'newcomers'   => $this->explorer->newcomers($n),
            'compare'     => $this->explorer->comparePeriods($n),
            'weekday'     => $this->explorer->byWeekday(),
            'peak'        => $this->explorer->peakHours(),
            'by_city'     => $this->explorer->thirdPartiesByCity(),
            default       => $this->explorer->lastLogins(),
        };
    }
    /** Quelle lecture sur les canaux, les droits, les paiements, le site ou la caisse ? null si aucune. @param string $n phrase normalisée */
    private function extrasIntent(string $n): ?string
    {
        // « les relances envoyées ce mois » est une question ; « relance les clients » est un ordre.
        if (preg_match('/\brelances?\b.*(envoyees?|echec|du mois|de la semaine|historique)|combien de relances/', $n) && !preg_match('/\b(controle\w*|prepar\w*|lance\w*)\b/', $n)) {
            return 'reminders';
        }
        if (preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|lance\w*|revis\w*|attribu\w*|active(?:r|z|ons)?|publi\w*|import(?:e|er|ez))\b/', $n)) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/commandes?/', $n) && (bool) preg_match('/whatsapp|sms|messagerie|\bchat\b/', $n) => 'wa_orders',
            (bool) preg_match('/importations?|\bimports?\b|(factures?|documents?) deposes?/', $n) && (bool) preg_match('/achat|fournisseur|deposes?/', $n) => 'purchase_imports',
            (bool) preg_match('/dossiers?/', $n) && (bool) preg_match('/agents?|ouverts?/', $n) => 'cases',
            (bool) preg_match('/permissions?|droits?|autorisations?|\broles?\b/', $n) => 'permissions',
            (bool) preg_match('/cheques?|\beffets?\b/', $n) && (bool) preg_match('/recus?|encaisse|mois|semaine|liste|aujourd|hier/', $n) => 'cheques',
            (bool) preg_match('/bannieres?|slides?|carrousel/', $n) => 'banners',
            (bool) preg_match('/terminaux?/', $n) => 'terminals',
            (bool) preg_match('/variantes?/', $n) => 'variants',
            (bool) preg_match('/\bprix\b/', $n) && (bool) preg_match('/par liste|dans (les|chaque|toutes les) listes?|selon (les|la) listes?/', $n) => 'prices_by_list',
            default => null,
        };
    }

    private function extrasAnswer(string $intent, string $n): array
    {
        return match ($intent) {
            'reminders'        => $this->extras->reminders($n),
            'wa_orders'        => $this->extras->messagingOrders($n),
            'purchase_imports' => $this->extras->purchaseImports(),
            'cases'            => $this->extras->agentCases(),
            'permissions'      => $this->extras->permissions($n),
            'cheques'          => $this->extras->cheques($n),
            'banners'          => $this->extras->banners(),
            'terminals'        => $this->extras->terminals(),
            'variants'         => $this->extras->variants(),
            default            => $this->extras->pricesByList($n),
        };
    }

    /**
     * La réponse à une phrase qui est une LECTURE, ou null si les règles n'y voient pas une lecture. Sert au renfort par
     * IA : la question libre de l'administrateur y est reformulée en une phrase connue, jamais exécutée telle quelle.
     * Un ordre (« applique le lot 3 », « supprime… ») n'est reconnu par aucune de ces lectures : il ne passe donc pas.
     *
     * @param string $n phrase normalisée
     * @return array{body: string, meta: array<string, mixed>}|null
     */
    private function readAnswer(string $n, User $admin): ?array
    {
        return match (true) {
            ($i = $this->quickIntent($n)) !== null      => $this->quickAnswer($i, $n),
            ($i = $this->oversightIntent($n)) !== null  => $this->oversightAnswer($i, $n, $admin),
            ($i = $this->extrasIntent($n)) !== null    => $this->extrasAnswer($i, $n),
            ($i = $this->explorerIntent($n)) !== null  => $this->explorerAnswer($i, $n),
            ($i = $this->deepDiveIntent($n)) !== null  => $this->deepDiveAnswer($i, $n),
            ($i = $this->analysisIntent($n)) !== null  => $this->analysisAnswer($i, $n),
            ($i = $this->businessIntent($n)) !== null  => $this->businessAnswer($i, $n),
            ($i = $this->insightsIntent($n)) !== null  => $this->insightsAnswer($i, $n),
            ($i = $this->operationsIntent($n)) !== null => $this->operationsAnswer($i, $n),
            default                                    => $this->mentionAnswer($n),
        };
    }
    /** Quelle lecture sur les promotions d'un produit, les agents, les notifications ou les référentiels ? null si aucune. @param string $n phrase normalisée */
    private function oversightIntent(string $n): ?string
    {
        if (preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|lance\w*|revis\w*|attribu\w*|active(?:r|z|ons)?|publi\w*|supprim\w*|cree\w*)\b/', $n)) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/promotions?/', $n) && (bool) preg_match('/produits? (de|d.|dans|concernes? par) la promotion|produits? (de|d.) (cette )?promotion|promotion\s+\S+.*produits?/', $n) && !preg_match('/promotions? (du|de l.|d.)\s*(produit|article)/', $n) => 'promo_products',
            (bool) preg_match('/promotions?\s+(du|de l.|d.)\s*(produit|article)\s+\S/', $n) => 'product_promos',
            (bool) preg_match('/regles? de routage|routage|regles? du routeur/', $n) => 'routing',
            (bool) preg_match('/seuils?/', $n) && (bool) preg_match('/agents?|autonomie|validations?/', $n) && !preg_match('/credit|alerte stock/', $n) => 'thresholds',
            (bool) preg_match('/evenements?/', $n) && (bool) preg_match('/agents?/', $n) && (bool) preg_match('/mois|semaine|aujourd|par type|statuts?|combien/', $n) => 'agent_events',
            (bool) preg_match('/appareils?|abonn\w+/', $n) && (bool) preg_match('/notifications?|push/', $n) => 'push',
            (bool) preg_match('/notifications?/', $n) => 'notifications',
            (bool) preg_match('/factures?/', $n) && (bool) preg_match('/(non|pas|jamais) (encore )?envoyees?|a envoyer/', $n) => 'unsent',
            (bool) preg_match('/(mode|moyens?) de (paiement|reglement)/', $n) && (bool) preg_match('/repartition|par mode|factures?|ventes?/', $n) => 'pay_mix',
            (bool) preg_match('/livraisons?/', $n) && (bool) preg_match('/par ville|\bvilles?\b/', $n) && !preg_match('/commandes? clients?/', $n) => 'deliveries',
            (bool) preg_match('/\b(entrepots?|depots?|magasins?)\b/', $n) && (bool) preg_match('/\b(mes|liste|quels|combien|tous les)\b/', $n) && !preg_match('/stock|valeur|mouvement|transfert|inventaire|produit/', $n) => 'warehouses',
            (bool) preg_match('/categories?/', $n) && (bool) preg_match('/tresorerie|depenses?|caisse/', $n) && (bool) preg_match('/\b(mes|liste|quelles|combien|toutes)\b/', $n) => 'cash_categories',
            (bool) preg_match('/listes? (de prix|tarifaires?)/', $n) && (bool) preg_match('/\b(mes|liste|quelles|combien|clients?|resume)\b/', $n) && !preg_match('/absents?|manquants?|\bsans\b|pas dans|par liste/', $n) => 'price_lists',
            default => null,
        };
    }

    private function oversightAnswer(string $intent, string $n, User $admin): array
    {
        return match ($intent) {
            'promo_products' => $this->oversight->promotionProducts($n),
            'product_promos' => $this->oversight->productPromotions($n),
            'routing'        => $this->oversight->routingRules(),
            'thresholds'     => $this->oversight->thresholds(),
            'agent_events'   => $this->oversight->agentEvents($n),
            'push'           => $this->oversight->pushDevices(),
            'notifications'  => $this->oversight->notifications($admin->id),
            'unsent'         => $this->oversight->unsentInvoices(),
            'pay_mix'        => $this->oversight->paymentMix($n),
            'deliveries'     => $this->oversight->deliveriesByCity(),
            'warehouses'     => $this->oversight->warehouses(),
            'cash_categories' => $this->oversight->cashCategories(),
            default          => $this->oversight->priceLists(),
        };
    }
    /** Les formulations naturelles des questions les plus courantes. null si aucune. @param string $n phrase normalisée */
    private function quickIntent(string $n): ?string
    {
        if (preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|lance\w*|revis\w*|attribu\w*|active(?:r|z|ons)?|publi\w*|supprim\w*|cree\w*|applique\w*)\b/', $n)) {
            return null;
        }
        $bientot = (bool) preg_match('/bientot|prochains? jours|prevision|va manquer|vont manquer|couverture|valeur|dormant|negatif|mouvement/', $n);

        return match (true) {
            (bool) preg_match('/tout va bien|quoi de neuf|fais[- ]moi le point|^le point\b|^ca va\b|comment (ca )?va\b.*(aujourd|affaires|boutique|entreprise)|bilan de la journee/', $n) => 'day',
            (bool) preg_match('/\ba perte\b|sous (le |leur )?(prix d.achat|cout)|perdent de l.argent|prix trop bas|prix inferieurs? au cout/', $n) => 'below_cost',
            !$bientot && (bool) preg_match('/stocks? (bas|faible|critique|insuffisant)|rupture|epuise|manque\w* en stock|qu.est.ce qui manque|reapprovisionn\w*|a commander|quoi commander|dois commander|plus de stock|(a|=) ?(0|zero)\b/', $n) => 'low_stock',
            (bool) preg_match('/benefice|rentabilite|combien j.ai gagne|ce que je gagne|\bma marge\b|produits? (les )?(plus|moins) rentables?/', $n) && !preg_match('/par (categorie|marque)/', $n) => 'margin',
            (bool) preg_match('/\btva\b/', $n) && (bool) preg_match('/collectee|deductible|a payer|a declarer|du mois|du trimestre|de l.annee|declaration|montant|total/', $n) || (bool) preg_match('/total des taxes/', $n) => 'vat_summary',
            (bool) preg_match('/qui (me doit|ne m.a pas paye|n.a pas paye|ne paie)|me doit (de l.argent|combien)|clients? .*(ne paient|n.ont pas paye|en retard de paiement|impayes?)|\bimpayes\b|argent (que )?(les clients|on me doit)|creances?/', $n) && !preg_match('/fournisseurs?/', $n) && $this->mentions->thirdParty($n) === null => 'overdue',
            (bool) preg_match('/ce que je dois|je dois (payer|aux|a mes)|mes dettes|dois[- ]je payer|combien je dois/', $n) => 'supplier_due',
            (bool) preg_match('/ma tresorerie|ou en est (la|ma) tresorerie|argent (en caisse|disponible|dispo)|combien d.argent|mon solde|solde (global|total)|cash disponible/', $n) => 'balances',
            (bool) preg_match('/^chiffre (du jour|de la journee|d.hier)/', $n) => 'sales',
            (bool) preg_match('/combien (de |d.)\s*(clients?|fournisseurs?|produits?|articles?|factures?|devis|tickets?|commandes?|utilisateurs?|entrepots?|bons? de livraison)\b|stock total|total du stock/', $n) => 'count',
            (bool) preg_match('/dernieres? (factures?|ventes?|devis|commandes?|livraisons?)|derniers? (documents?|bons?)/', $n) => 'latest_docs',
            (bool) preg_match('/derniers? (paiements?|encaissements?|reglements?)/', $n) => 'latest_payments',
            (bool) preg_match('/derniers? (clients?|fournisseurs?)/', $n) => 'latest_customers',
            (bool) preg_match('/clients? (vip|fideles?|les plus (rentables?|importants?|gros))|gros clients|client le plus rentable|clients? qui achet\w* (le )?plus|meilleur client/', $n) => 'best_customers',
            (bool) preg_match('/livraisons? (en retard|a livrer|en cours|a faire)|commandes? (en cours|a livrer|non livrees?)/', $n) => 'orders',
            (bool) preg_match('/(vends?|vendu|ventes?).*(plus|moins) (que|qu.)\s*(le mois dernier|la semaine derniere|l.annee derniere|l.an dernier)|(plus|moins) (que|qu.)\s*(le mois dernier|la semaine derniere)/', $n) => 'compare',
            (bool) preg_match('/retours? (clients?|fournisseurs?)|produits? retournes?|\bretours\b/', $n) => 'returns',
            (bool) preg_match('/fournisseur principal|principaux fournisseurs|meilleur fournisseur|fournisseurs? le plus/', $n) => 'top_supplier',
            (bool) preg_match('/\bconnecte\b|connexions?/', $n) => 'logins',
            (bool) preg_match('/caisse du jour|cloture de caisse|fermeture de caisse|ouverture de caisse/', $n) => 'cash_day',
            (bool) preg_match('/ventes? (de|en) (la )?caisse/', $n) => 'register_sales',
            (bool) preg_match('/qui a (fait|cree|modifie|supprime)\s+(cette|ce|le|la)\s+(facture|produit|devis|client|document)\s*$/', $n) => 'ask_reference',
            default => null,
        };
    }

    private function quickAnswer(string $intent, string $n): array
    {
        return match ($intent) {
            'day'              => $this->business->daySummary(),
            'below_cost'       => $this->insights->belowCost(),
            'low_stock'        => $this->insights->lowStock(),
            'margin'           => $this->deepDive->realizedMargin($n),
            'vat_summary'      => $this->deepDive->vatSummary($n),
            'overdue'          => $this->business->overdueInvoices($n),
            'supplier_due'     => $this->operations->supplierInvoicesDue($n),
            'balances'         => $this->business->balances(),
            'sales'            => $this->business->sales($n),
            'count'            => $this->insights->count($n),
            'latest_docs'      => $this->explorer->latestDocuments(),
            'latest_payments'  => $this->business->income('encaissements du mois'),
            'latest_customers' => $this->explorer->newcomers('nouveaux ' . (str_contains($n, 'fournisseur') ? 'fournisseurs' : 'clients') . ' du mois'),
            'best_customers'   => $this->analysis->bestCustomers($n),
            'orders'           => $this->deepDive->pendingCustomerOrders(),
            'compare'          => $this->explorer->comparePeriods($n),
            'returns'          => $this->deepDive->returns($n),
            'top_supplier'     => $this->operations->purchasesBySupplier("achats de l'annee par fournisseur"),
            'logins'           => $this->explorer->lastLogins(),
            'cash_day'         => $this->business->cashSessions(),
            'register_sales'   => $this->analysis->salesByRegister($n),
            default            => $this->reply('Quelle fiche ? Donnez la référence, par exemple « qui a modifié la facture FV-0001 » ou « historique du produit PRC1 ».', 'help', error: true),
        };
    }

    /**
     * Une phrase qui nomme un client, un fournisseur ou un produit sans que les autres règles l'aient comprise :
     * « Atlas me doit combien ? », « dernière facture d'Atlas », « combien reste-t-il de perceuses ? ».
     *
     * @param string $n phrase normalisée
     * @return array{body: string, meta: array<string, mixed>}|null
     */
    private function mentionAnswer(string $n): ?array
    {
        if (preg_match('/\b(controle\w*|prepar\w*|relanc\w*|recrut\w*|lance\w*|revis\w*|attribu\w*|active(?:r|z|ons)?|publi\w*|supprim\w*|cree\w*|applique\w*)\b/', $n)) {
            return null;
        }

        if (preg_match('/doit|solde|impaye|reste|devoir|situation|factures?|achats?|historique|commandes?|devis|dernier|derniere|paiements?|livraisons?/', $n) && ($t = $this->mentions->thirdParty($n)) !== null) {
            $role = $t->role === 'supplier' ? 'fournisseur' : 'client';

            return preg_match('/doit|solde|impaye|reste|devoir|situation/', $n)
                ? $this->deepDive->thirdPartyCard("fiche du {$role} {$t->name}")
                : $this->explorer->thirdPartyDocuments("factures du {$role} {$t->name}" . (str_contains($n, 'impaye') ? ' impayees' : ''));
        }

        if (preg_match('/\bstock\b|\bprix\b|combien (il )?reste|combien en ai|ou (est|sont)|en stock|disponibles?|dispo/', $n) && ($word = $this->mentions->productWord($n)) !== null) {
            return $this->deepDive->productCard("fiche du produit {$word}");
        }

        return null;
    }
    // ── Fiches produits ──────────────────────────────────────────────

    /** Quelle demande sur les fiches produits la phrase exprime-t-elle ? null si aucune. */
    private function catalogIntent(string $n): ?string
    {
        if (preg_match('/\blot\s*#\s*\d+/', $n)) {
            return 'act';
        }
        $about = (bool) preg_match('/fiches?|produits?|prouits?|catalogue|articles?/', $n);

        return match (true) {
            // « cherche les photos Jadever » : le serveur va les chercher sur le site autorisé (aperçu, puis clic).
            (bool) preg_match('/\b(photos?|images?)\b/', $n) && (bool) preg_match('/\b(cherch|trouv|recuper|telecharg|import|rattach|rapport|ajout)\w*/', $n) && (bool) preg_match('/jadever|officiel|site|automatique|toi.meme|arriere/', $n) => 'photos_fetch',
            // « publier les produits dans le website » : l'agent Marketing propose la mise en boutique en ligne.
            (bool) preg_match('/\b(publi\w*|mise? en ligne|mett\w* en ligne)/', $n) && (bool) preg_match('/produits?|fiches?|articles?|catalogue/', $n) && (bool) preg_match('/website|web site|site web|\bsite\b|boutique|e-?commerce|en ligne|internet/', $n) => 'publication',
            (bool) preg_match('/\bprix\b|tarifs?/', $n) && (bool) preg_match('/revis|propos|marge|calcul/', $n) && ($about || str_contains($n, 'marge')) => 'pricing',
            (bool) preg_match('/sans photos?|photos? manquantes?|manque de photos?|pas de photos?|sans image/', $n) => 'photos',
            $about && (bool) preg_match('/\b(activ(?:e|er|ons|ation)|reactiv\w*|mett\w* en service)\b/', $n) => 'activation',
            (bool) preg_match('/codes?[- ]?barres?|\bean\b/', $n) && (bool) preg_match('/attribu|genere|propos|cree|ajout|complet|calcul|manquant/', $n) => 'barcodes',
            $about && (bool) preg_match('/utilisation|a l.emploi|\bpret|utilisables?/', $n) => 'prepare',
            $about && (bool) preg_match('/descriptions?|categor|marques?/', $n) && (bool) preg_match('/complet|enrichi|redige|genere|ajout|propos/', $n) => 'complete',
            $about && (bool) preg_match('/updat|m(?:et|ett)\w* a jour|mise a jour|incomplet|verifi|control|audit|qualite|manquant|\betat\b/', $n) => 'audit',
            default => null,
        };
    }

    private function catalogAnswer(User $admin, string $intent, string $n): array
    {
        return match ($intent) {
            'act'      => preg_match('/\blot\s*#\s*(\d+)/', $n, $m) ? $this->catalog->act($admin, (int) $m[1], $n) : $this->catalog->audit($admin),
            'pricing'  => $this->catalog->pricing($admin, $n),
            'photos'   => $this->catalog->photos(),
            'photos_fetch' => $this->catalog->fetchPhotos($admin),
            'publication' => $this->catalog->publication($admin),
            'activation' => $this->catalog->activation($admin),
            'barcodes' => $this->catalog->barcodes($admin),
            'prepare'  => $this->catalog->prepare($admin),
            'complete' => $this->catalog->complete($admin),
            default    => $this->catalog->audit($admin),
        };
    }

    // ── Orientation dans l'application (lecture seule) ───────────────

    /**
     * Les règles ne rendent la main à l'orientation que si la phrase ressemble à une recherche d'écran
     * (question « où / comment », verbe de navigation, demande de modification) ou si elle est courte,
     * donc probablement juste le nom de l'écran. Une longue phrase qui cite un mot du catalogue
     * (« recompter tout ce qu'il y a au dépôt ») reste pour la compréhension avancée.
     */
    private function looksLikeNavigation(string $n): bool
    {
        return str_word_count($n) <= 4
            || (bool) preg_match('/\bou (est|se|puis|peut|trouv\w*|fait|faire|creer|voir)\b|\b(comment|ouvre|ouvrir|aller|va|acceder|montre|affiche|afficher|voir|trouve|trouver|page)\b|updat|mets?\b|mett|modif|chang|corrig|ajout|creer|cree|supprim/', $n);
    }

    /** Ni règle ni IA n'ont rangé la phrase : si elle nomme un écran du catalogue on y renvoie, sinon l'aide. */
    private function understood(string $text): array
    {
        $n = $this->normalize($text);
        $screens = AppCatalog::match($n);

        return $screens !== [] ? $this->navigate($n, $screens) : $this->help(false);
    }

    /** Tout ce que l'on peut faire dans O3, par domaine. Rien n'est exécuté : on décrit et on oriente. */
    private function capabilities(): array
    {
        $lines = [];
        foreach (AppCatalog::grouped() as $group => $screens) {
            $lines[] = '• ' . $group . ' : ' . collect($screens)->pluck('title')->implode(', ');
        }

        return $this->reply(
            "Voici ce que l'on peut faire dans O3 App, par domaine :\n\n" . implode("\n", $lines)
            . "\n\nDites le nom de ce que vous cherchez (ex. « les fiches produits », « créer une facture », « la corbeille ») et je vous indique le bon écran."
            . "\n\nCe que je peux lancer moi-même, par les agents : préparer un inventaire, contrôler les encaissements et préparer les relances. Le reste se fait dans les écrans, par vous.",
            'capabilities',
            links: [['label' => 'Activité des agents', 'to' => '/settings/agents']],
        );
    }

    /**
     * L'utilisateur nomme un écran de l'application. On le décrit et on y renvoie. Si la phrase demande
     * de MODIFIER quelque chose, on dit franchement qu'aucun agent ne le fait encore : rien n'est exécuté.
     *
     * @param array<int, string> $keys écrans désignés, du mieux placé au moins bien placé
     */
    private function navigate(string $n, array $keys): array
    {
        $screens = AppCatalog::screens();
        $keys = array_slice($keys, 0, 3);
        // « Où créer une facture ? » est une question de localisation, pas une demande de le faire à sa place.
        $asksWhere = (bool) preg_match('/\bou (est|se|puis|peut|trouv\w*|fait|faire|creer|voir)\b|\bcomment\b/', $n);
        $edits = !$asksWhere && (bool) preg_match('/updat|mets?|mett|jour|modif|chang|corrig|complet|actualis|enrichi|ajout|cree|creer|supprim|augment|baiss/', $n);

        $body = $edits
            ? "Je ne sais pas encore le faire à votre place : aucun agent n'en est chargé pour le moment. Cela se fait dans "
            : 'Cela se passe dans ';
        $body .= count($keys) > 1 ? "ces écrans :\n" : "l'écran :\n";

        $links = [];
        foreach ($keys as $key) {
            $screen = $screens[$key];
            $body .= "\n• {$screen['title']} : {$screen['does']}";
            if (isset($screen['feature']) && tenant() && !tenant()->hasModule($screen['feature'])) {
                $body .= ' (non inclus dans votre formule actuelle)';
            }
            if (isset($screen['agent'])) {
                $body .= " — l'agent {$screen['agent']} en automatise une partie";
            }
            $links[] = ['label' => $screen['title'], 'to' => $screen['path']];
        }

        return $this->reply($body, 'navigate', links: $links);
    }

    private function status(): array
    {
        $agents = Agent::orderBy('id')->get();
        if ($agents->isEmpty()) {
            return $this->reply("Aucun agent n'est encore enregistré sur ce tenant : le socle n'a pas été initialisé (seeder des agents).", 'status', error: true);
        }

        $active = $agents->where('is_active', true)->pluck('name')->implode(', ') ?: 'aucun';
        $inactive = $agents->where('is_active', false)->pluck('name')->implode(', ') ?: 'aucun';
        $toSort = AgentEvent::where('status', AgentEvent::STATUS_TO_SORT)->count();
        $rejected = AgentEvent::where('status', AgentEvent::STATUS_REJECTED)->where('created_at', '>=', now()->subDay())->count();
        $reminders = PaymentReminder::whereIn('status', [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED]);
        $toValidate = (clone $reminders)->count();
        $amount = (float) (clone $reminders)->sum('amount_due');
        $sheets = $this->inventorySheets();

        $body = "Situation du " . now()->format('d/m à H:i') . " :\n\n"
            . "• Agents actifs : {$active}\n"
            . "• Agents inactifs : {$inactive}\n"
            . '• Routeur de messages : ' . (Setting::get('agents', 'router_enabled', 'false') === 'true' ? 'activé' : 'désactivé') . "\n"
            . '• Événements : ' . AgentEvent::count() . " au total, {$toSort} à trier, {$rejected} refusé(s) ces dernières 24 h\n"
            . "• Dossiers ouverts : " . AgentCase::where('status', 'open')->count() . "\n"
            . "• Relances à valider : {$toValidate}" . ($toValidate ? ' (' . $this->money($amount) . ')' : '') . "\n"
            . "• Feuilles d'inventaire à compter : {$sheets['to_count']}";

        return $this->reply($body, 'status', links: [
            ['label' => 'Activité des agents', 'to' => '/settings/agents'],
            ['label' => 'Relances de paiement', 'to' => '/ventes/relances'],
            ['label' => 'Inventaire', 'to' => '/stock/inventaire'],
        ]);
    }

    private function toSort(): array
    {
        $events = AgentEvent::where('status', AgentEvent::STATUS_TO_SORT)->latest('id')->limit(5)->get();
        if ($events->isEmpty()) {
            return $this->reply('Rien à trier : le routeur a confié tous les événements à un agent (ou les a refusés).', 'to_sort');
        }

        $lines = $events->map(function (AgentEvent $e) {
            $text = Str::limit((string) ($e->payload['text'] ?? '—'), 70);

            return "• #{$e->id} · {$e->source} · {$text}";
        })->implode("\n");
        $total = AgentEvent::where('status', AgentEvent::STATUS_TO_SORT)->count();

        return $this->reply(
            "{$total} événement(s) à trier. Les " . $events->count() . " plus récents :\n\n{$lines}",
            'to_sort',
            links: [['label' => 'Voir tous les événements', 'to' => '/settings/agents']],
        );
    }

    private function inventory(User $admin, string $n, bool $action): array
    {
        if (!$action) {
            $s = $this->inventorySheets();

            return $this->reply(
                "Feuilles d'inventaire : {$s['total']} préparée(s), {$s['to_count']} à compter, {$s['applied']} appliquée(s).\n\n"
                . "Pour en préparer une, dites par exemple « prépare un inventaire », « prépare un inventaire du Dépôt Principal » ou « prépare un inventaire des articles à vérifier ».",
                'inventory_status',
                links: [['label' => 'Ouvrir Inventaire', 'to' => '/stock/inventaire']],
            );
        }

        $warehouse = Warehouse::where('wh_status', true)->get()->first(fn (Warehouse $w) => str_contains($n, $this->normalize($w->wh_title)));
        $scope = preg_match('/a verifier|seulement|uniquement|anomalie|attention/', $n) ? 'attention' : 'all';

        $out = $this->orders->orderInventory([
            'warehouse_id' => $warehouse?->id,
            'scope'        => $scope,
            'note'         => 'Demandé à l\'orchestrateur',
        ], $admin->name);

        if (!$out['ok']) {
            return $this->reply($out['message'], 'inventory', error: true, eventId: $out['event']->id);
        }

        $r = $out['result'];
        $where = $warehouse ? "l'entrepôt « {$warehouse->wh_title} »" : 'tous les entrepôts';
        $body = "J'ai confié l'ordre à l'agent Stocks (événement #{$out['event']->id}) pour {$where}.\n\n"
            . "Feuille prête : {$r['rows']} article(s), dont {$r['flagged']} à vérifier.\n"
            . "Rien n'est modifié : vous comptez, puis vous appliquez vous-même les écarts dans l'écran Inventaire.";
        if ($r['rows'] === 0) {
            $body = "J'ai confié l'ordre à l'agent Stocks (événement #{$out['event']->id}) pour {$where}, mais aucun article ne correspond"
                . ($scope === 'attention' ? ' : aucun n\'est à vérifier.' : ' : aucun stock enregistré.');
        }

        return $this->reply($body, 'inventory', links: [['label' => 'Ouvrir Inventaire', 'to' => '/stock/inventaire']], eventId: $out['event']->id);
    }

    private function collections(User $admin, string $n, bool $action): array
    {
        if (!$action) {
            $drafts = PaymentReminder::with(['document:id,reference', 'thirdPartner:id,tp_title'])
                ->whereIn('status', [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED])->latest('id')->limit(3)->get();
            $count = PaymentReminder::whereIn('status', [PaymentReminder::STATUS_DRAFT, PaymentReminder::STATUS_FAILED])->count();

            if ($count === 0) {
                return $this->reply(
                    "Aucune relance n'attend votre validation.\n\nPour que l'agent Recouvrement contrôle les encaissements et prépare les relances, dites « contrôle les encaissements ».",
                    'reminders_status',
                    links: [['label' => 'Relances de paiement', 'to' => '/ventes/relances']],
                );
            }

            $lines = $drafts->map(fn (PaymentReminder $r) => "• {$r->thirdPartner?->tp_title} · {$r->document?->reference} · " . $this->money((float) $r->amount_due) . " · niveau {$r->level}")->implode("\n");

            return $this->reply(
                "{$count} relance(s) attendent votre validation. Les plus récentes :\n\n{$lines}\n\nValidez, modifiez ou rejetez-les dans l'écran Relances de paiement : seul votre geste envoie un message.",
                'reminders_status',
                links: [['label' => 'Relances de paiement', 'to' => '/ventes/relances']],
            );
        }

        $out = $this->orders->orderCollections($admin->name);
        if (!$out['ok']) {
            return $this->reply($out['message'], 'collections', error: true, eventId: $out['event']->id);
        }

        $r = $out['result'];
        $created = count($r['created']);
        $body = "J'ai confié l'ordre à l'agent Recouvrement (événement #{$out['event']->id}).\n\n"
            . "• {$r['overdue']} facture(s) en retard\n"
            . "• {$created} relance(s) préparée(s) en brouillon\n"
            . '• ' . count($r['anomalies']) . " anomalie(s) d'encaissement";
        foreach (array_slice($r['anomalies'], 0, 3) as $a) {
            $body .= "\n   – {$a['reference']} : {$a['message']}";
        }
        $body .= "\n\nAucun message n'est parti : validez les relances dans l'écran Relances de paiement.";

        return $this->reply($body, 'collections', links: [['label' => 'Relances de paiement', 'to' => '/ventes/relances']], eventId: $out['event']->id);
    }

    // ── Outils ───────────────────────────────────────────────────────

    /** @return array{total: int, to_count: int, applied: int} */
    private function inventorySheets(): array
    {
        $events = AgentEvent::where('type', 'inventaire_demande')->pluck('id');
        $prepared = \App\Models\AgentAction::whereIn('event_id', $events)->where('action', 'prepare_inventory_sheet')->pluck('event_id')->unique();
        $applied = \App\Models\AgentAction::whereIn('event_id', $events)->where('action', 'inventory_applied')->pluck('event_id')->unique();

        return ['total' => $prepared->count(), 'applied' => $applied->count(), 'to_count' => $prepared->diff($applied)->count()];
    }

    /** Un verbe d'action est nécessaire pour qu'un ordre parte ; « à vérifier » seul n'en est pas un. */
    private function wantsAction(string $n): bool
    {
        $n = str_replace('a verifier', '', $n);

        return (bool) preg_match('/\b(prepar|lanc|fais|faire|genere|demand|control|verifi|execut|realis|organis)\w*/', $n);
    }

    private function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' MAD';
    }

    /**
     * @param array<int, array{label: string, to: string}> $links
     * @param array<int, array{label: string, text: string}> $suggestions boutons proposés : le texte est envoyé tel quel en cas de clic
     * @return array{body: string, meta: array<string, mixed>}
     */
    private function reply(string $body, string $intent, array $links = [], bool $error = false, ?int $eventId = null, array $suggestions = [], bool $warning = false): array
    {
        return ['body' => $body, 'meta' => array_filter([
            'intent'   => $intent,
            'links'    => $links ?: null,
            'error'    => $error ?: null,
            'event_id'    => $eventId,
            'suggestions' => $suggestions ?: null,
            'warning'     => $warning ?: null,
        ], fn ($v) => $v !== null)];
    }
}
