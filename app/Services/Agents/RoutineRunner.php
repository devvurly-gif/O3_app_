<?php

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AgentAction;
use App\Models\AgentEvent;
use App\Models\AgentRoutine;
use App\Models\OrchestratorMessage;
use App\Models\User;
use App\Models\Setting;
use App\Notifications\RoutineReport;
use Illuminate\Support\Facades\Log;

/**
 * Exécute une routine : ses étapes connues, l'une après l'autre, puis dépose le compte rendu dans la
 * conversation de l'administrateur qui l'a créée.
 *
 * Aucune étape n'écrit sans validation : les étapes de RoutineSteps sont des lectures ou des brouillons, et une
 * étape « agent:N » est un agent de lecture. Une étape en échec n'arrête pas les suivantes ; le compte rendu
 * dit ce qui n'a pas pu se faire. Un administrateur supprimé ou inactif : la routine s'arrête et le dit.
 */
class RoutineRunner
{
    private const STEP_EXCERPT = 700;

    public function __construct(private CustomAgentRunner $agents)
    {
    }

    /**
     * Exécute les routines arrivées à échéance. L'échéance est réservée AVANT l'exécution (mise à jour
     * conditionnelle) : deux passages simultanés ne peuvent pas exécuter deux fois la même routine.
     *
     * @param callable(AgentRoutine, string): void|null $onRun appelé après chaque exécution (état)
     * @return int nombre de routines exécutées
     */
    public function runDue(?callable $onRun = null): int
    {
        $ran = 0;
        $due = AgentRoutine::where('is_active', true)->whereNotNull('next_run_at')->where('next_run_at', '<=', now())->orderBy('next_run_at')->get();

        foreach ($due as $routine) {
            $claimed = AgentRoutine::whereKey($routine->id)->where('is_active', true)->where('next_run_at', $routine->next_run_at)
                ->update(['next_run_at' => RoutineSchedule::next($routine->schedule)]);
            if ($claimed === 0) {
                continue;
            }

            $out = $this->run($routine->fresh(), 'planifiée');
            $ran++;
            $onRun && $onRun($routine, $out['status']);
        }

        return $ran + $this->runTriggered($onRun);
    }

    /**
     * Les routines déclenchées par un événement interne d'O3 : pour chacune, les événements arrivés depuis son
     * dernier passage et qui satisfont ses conditions. Chaque événement n'est traité qu'une fois (le curseur
     * `last_event_id` est avancé AVANT l'exécution, par une mise à jour conditionnelle) et, après une
     * exécution, la routine attend son délai (`cooldown_minutes`) avant de se relancer : un afflux
     * d'événements donne un seul compte rendu qui les regroupe.
     *
     * @param callable(AgentRoutine, string): void|null $onRun
     */
    private function runTriggered(?callable $onRun): int
    {
        $ran = 0;
        foreach (AgentRoutine::where('is_active', true)->whereNotNull('trigger')->orderBy('id')->get() as $routine) {
            $trigger = $routine->trigger;
            if ($routine->last_run_at && $routine->last_run_at->gt(now()->subMinutes((int) ($trigger['cooldown_minutes'] ?? 60)))) {
                continue;   // encore dans le délai : les événements s'accumulent pour le prochain passage
            }

            $since = (int) ($routine->last_event_id ?? 0);
            $events = AgentEvent::where('type', $trigger['event_type'])->where('id', '>', $since)->orderBy('id')->limit(100)->get();
            if ($events->isEmpty()) {
                continue;
            }

            $claimed = AgentRoutine::whereKey($routine->id)->where('is_active', true)
                ->where(fn ($q) => $since === 0 ? $q->whereNull('last_event_id')->orWhere('last_event_id', 0) : $q->where('last_event_id', $since))
                ->update(['last_event_id' => $events->max('id')]);
            $matching = $events->filter(fn (AgentEvent $e) => AgentTriggers::matches($trigger, $e));
            if ($claimed === 0 || $matching->isEmpty()) {
                continue;
            }

            $context = $matching->take(10)->map(fn (AgentEvent $e) => '• ' . AgentTriggers::contextLine($e))->implode("\n")
                . ($matching->count() > 10 ? "\n• … et " . ($matching->count() - 10) . ' autre(s).' : '');
            $out = $this->run($routine->fresh(), $matching->count() > 1 ? "déclenchée par {$matching->count()} événements" : 'déclenchée par un événement', $context);
            $ran++;
            $onRun && $onRun($routine, $out['status']);
        }

        return $ran;
    }

    /**
     * @param string $trigger « planifiée » ou « lancée à la demande »
     * @return array{status: string, body: string}
     */
    public function run(AgentRoutine $routine, string $trigger = 'planifiée', ?string $context = null): array
    {
        $creator = User::find($routine->created_by);
        if (!$creator || !$creator->is_active) {
            return $this->finish($routine, 'error', "Routine « {$routine->name} » arrêtée : son administrateur n'existe plus ou est inactif.", null, []);
        }

        // Résolu ici et non au constructeur : l'orchestrateur dépend lui-même de l'atelier des agents.
        $orchestrator = app(Orchestrator::class);

        $sections = [];
        $suggestions = [];
        $links = [];
        $failed = 0;

        foreach ($routine->steps as $step) {
            $label = RoutineSteps::label($step);
            try {
                if (isset(RoutineSteps::KNOWN[$step])) {
                    $answer = $orchestrator->runCommand($creator, RoutineSteps::KNOWN[$step]['phrase']);
                    $body = $answer['body'];
                    $error = (bool) ($answer['meta']['error'] ?? false);
                    $suggestions = array_merge($suggestions, $answer['meta']['suggestions'] ?? []);
                    $links = array_merge($links, $answer['meta']['links'] ?? []);
                } else {
                    [$body, $error, $more] = $this->runAgent($step, $context);
                    $suggestions = array_merge($suggestions, $more);
                }
            } catch (\Throwable $e) {
                Log::error("Routine #{$routine->id}, étape {$step} : {$e->getMessage()}");
                $body = "L'étape a échoué.";
                $error = true;
            }
            $error && $failed++;
            $sections[] = "— {$label}" . ($error ? ' (non réalisée)' : '') . " —\n" . mb_strimwidth($body, 0, self::STEP_EXCERPT, '…');
        }

        $status = $failed === 0 ? 'ok' : ($failed < count($routine->steps) ? 'partial' : 'error');
        $body = "Routine « {$routine->name} » ({$trigger}) :\n\n" . ($context !== null ? "Événements :\n{$context}\n\n" : '') . implode("\n\n", $sections)
            . "\n\nRien n'a été appliqué : les étapes préparent des brouillons ou lisent seulement, vous validez ce qui demande une décision.";

        return $this->finish($routine, $status, $body, $creator, [
            'suggestions' => array_slice($this->uniqueByText($suggestions), 0, 6),
            'links'       => array_values(collect($links)->unique('to')->all()),
            // Prévenu hors du chat seulement quand personne n'a lancé la routine lui-même.
            'notify'      => $trigger !== 'lancée à la demande',
        ]);
    }

    /** @return array{0: string, 1: bool, 2: array<int, array{label: string, text: string}>} */
    private function runAgent(string $step, ?string $context = null): array
    {
        $agent = preg_match('/^agent:(\d+)$/', $step, $m) ? Agent::where('kind', 'custom')->find((int) $m[1]) : null;
        if (!$agent || !$agent->is_active) {
            return ["L'agent est inactif ou n'existe plus : activez-le pour que la routine l'utilise.", true, []];
        }

        $task = ($agent->mission ?? $agent->name) . ($context !== null ? "\n\nÉvénements qui ont déclenché cette exécution :\n{$context}" : '');
        $result = $this->agents->run($agent, $task);
        if ($result === null) {
            return ["L'agent n'a pas pu travailler : " . ($this->agents->failure() ?? 'erreur') . '.', true, []];
        }

        $suggestions = array_map(fn (string $key) => ['label' => RoutineSteps::KNOWN[$key]['label'], 'text' => RoutineSteps::KNOWN[$key]['phrase']], $result['proposals']);

        return [($result['level'] === 'attention' ? "À surveiller.\n" : '') . $result['report'], false, $suggestions];
    }

    /** Enregistre le résultat, programme la prochaine exécution et dépose le message. @param array<string, mixed> $meta */
    private function finish(AgentRoutine $routine, string $status, string $body, ?User $creator, array $meta): array
    {
        $routine->update([
            'last_run_at'  => now(),
            'next_run_at'  => $routine->is_active ? $routine->nextScheduledRun() : null,
            'last_status'  => $status,
            'last_summary' => mb_substr($body, 0, 1500),
        ]);

        AgentEvent::create([
            'type'     => 'routine_executee',
            'source'   => 'scheduler',
            'status'   => $status === 'error' ? AgentEvent::STATUS_ERROR : AgentEvent::STATUS_DONE,
            'agent_id' => $routine->agent_id,
            'payload'  => ['text' => "Routine « {$routine->name} » : {$status}", 'routine_id' => $routine->id, 'result' => $status],
        ]);

        if ($creator) {
            OrchestratorMessage::create([
                'user_id' => $creator->id,
                'role'    => OrchestratorMessage::ROLE_ORCHESTRATOR,
                'body'    => $body,
                'meta'    => array_filter(['intent' => 'routine', 'suggestions' => $meta['suggestions'] ?? null ?: null, 'links' => $meta['links'] ?? null ?: null, 'error' => $status === 'error' ?: null], fn ($v) => $v !== null),
            ]);
        }

        // La cloche (et la notification push si elle est configurée) : seulement s'il y a quelque chose à décider ou si la routine a échoué.
        if ($creator && ($meta['notify'] ?? false) && ($status !== 'ok' || !empty($meta['suggestions']))) {
            try {
                // Au plus un e-mail par routine et par 6 heures, noté en base (un cache sans étiquettes échoue sous la séparation des tenants).
                $mailKey = "routine_mail_at_{$routine->id}";
                $mail = Setting::get('agents', 'routine_email', 'true') !== 'false' && (int) Setting::get('agents', $mailKey, '0') <= now()->subHours(6)->timestamp;
                $mail && Setting::set('agents', $mailKey, (string) now()->timestamp);
                $creator->notify(new RoutineReport($routine->id, $routine->name, $status, count($meta['suggestions'] ?? []), $mail));
            } catch (\Throwable $e) {
                Log::warning("Routine #{$routine->id} : notification non envoyée ({$e->getMessage()})");
            }
        }

        AgentAction::create([
            'agent_id' => $routine->agent_id ?? Agent::orderBy('id')->value('id') ?? 0,
            'event_id' => null,
            'action'   => 'routine_run',
            'level'    => 'auto',
            'input'    => ['routine_id' => $routine->id],
            'result'   => ['status' => $status],
        ]);

        return ['status' => $status, 'body' => $body];
    }

    /** @param array<int, array{label: string, text: string}> $items */
    private function uniqueByText(array $items): array
    {
        return array_values(collect($items)->unique('text')->all());
    }
}
