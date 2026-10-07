<?php

namespace App\Http\Controllers\Api\Agents;

use App\Http\Controllers\Controller;
use App\Models\OrchestratorMessage;
use App\Models\Setting;
use App\Services\Agents\BankStatementImporter;
use App\Services\Agents\CatalogAssistant;
use App\Services\Agents\DocumentIntake;
use App\Services\Agents\ExportAssistant;
use App\Services\Agents\DocumentReader;
use App\Services\Agents\Orchestrator;
use App\Services\Agents\OrchestratorInterpreter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Session de discussion administrateur ↔ orchestrateur. Chaque administrateur ne
 * voit que sa propre conversation.
 */
class OrchestratorController extends Controller
{
    private const HISTORY = 60;

    public function __construct(private Orchestrator $orchestrator, private OrchestratorInterpreter $interpreter)
    {
    }

    /** GET /api/agents/orchestrateur/exports/{uuid} — un export (Excel, CSV, PDF) demandé par cet administrateur, et lui seul. */
    public function export(Request $request, ExportAssistant $exports, string $uuid): BinaryFileResponse
    {
        $this->ensureInteractiveUser($request);
        $file = $exports->find((int) $request->user()->id, $uuid);
        abort_if($file === null, 404);

        return response()->download($file['path'], $file['name']);
    }

    /** GET /api/agents/orchestrateur/photos/{event}/{product} — l'aperçu d'une photo proposée, en attente de validation. */
    public function photo(Request $request, CatalogAssistant $catalog, int $event, int $product): BinaryFileResponse
    {
        $this->ensureInteractiveUser($request);
        $path = $catalog->previewPath($event, $product);
        abort_if($path === null, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=300']);
    }

    /** GET /api/agents/orchestrateur — l'historique de la session de l'administrateur. */
    public function index(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $messages = OrchestratorMessage::where('user_id', $request->user()->id)
            ->latest('id')->limit(self::HISTORY)->get()->reverse()->values();

        return response()->json([
            'messages' => $messages->map(fn (OrchestratorMessage $m) => $this->present($m)),
            'ai'       => $this->aiState(),
        ]);
    }

    /** POST /api/agents/orchestrateur — l'administrateur écrit, l'orchestrateur répond. */
    public function send(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $data = $request->validate(['message' => ['required', 'string', 'min:1', 'max:1000']]);

        $exchange = $this->orchestrator->converse($request->user(), $data['message']);

        return response()->json([
            'user'  => $this->present($exchange['user']),
            'reply' => $this->present($exchange['reply']),
        ], 201);
    }

    /**
     * POST /api/agents/orchestrateur/fichiers — l'administrateur dépose des photos ou des PDF ; l'orchestrateur
     * les lit, dit ce que c'est et propose la suite. Rien n'est créé à la réception.
     */
    public function upload(Request $request, DocumentIntake $intake): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $data = $request->validate([
            'message'   => ['nullable', 'string', 'max:1000'],
            'files'     => ['required', 'array', 'min:1', 'max:' . DocumentIntake::MAX_FILES],
            'files.*'   => ['file', 'max:10240', function (string $attribute, mixed $value, \Closure $fail) {
                // Photos et PDF : sur le type détecté d'après le contenu ; relevés Excel / CSV : extension + type cohérent (voir isAcceptedStatementFile).
                if ($value instanceof \Illuminate\Http\UploadedFile && (in_array($value->getMimeType(), DocumentReader::MIMES, true) || BankStatementImporter::isAcceptedStatementFile($value))) {
                    return;
                }
                $fail('Seuls les photos (JPEG, PNG, WebP, GIF), les PDF et les relevés Excel ou CSV sont acceptés.');
            }],
        ], [
            'files.max'       => 'Trois fichiers au maximum à la fois.',
            'files.*.max'     => 'Un fichier dépasse 10 Mo.',
        ]);

        $exchange = $intake->receive($request->user(), $request->file('files'), trim((string) ($data['message'] ?? '')));

        return response()->json([
            'user'  => $this->present($exchange['user']),
            'reply' => $this->present($exchange['reply']),
        ], 201);
    }

    /** PUT /api/agents/orchestrateur/ia— active ou coupe la compréhension avancée (modèle de langage). */
    public function toggleAi(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        if ($data['enabled'] && !$this->interpreter->configured()) {
            return response()->json(['message' => "Aucune clé API Anthropic n'est enregistrée : saisissez-la dans Paramètres → Réglages → Messagerie."], 422);
        }

        Setting::set('agents', 'orchestrator_ai_enabled', $data['enabled'] ? 'true' : 'false');

        return response()->json($this->aiState());
    }

    /** DELETE /api/agents/orchestrateur — efface l'historique de la session (pas les ordres déjà donnés). */
    public function clear(Request $request): JsonResponse
    {
        $this->ensureInteractiveUser($request);

        OrchestratorMessage::where('user_id', $request->user()->id)->delete();

        return response()->json(null, 204);
    }

    /** @return array{configured: bool, enabled: bool, model: string} */
    private function aiState(): array
    {
        return [
            'configured' => $this->interpreter->configured(),
            'enabled'    => $this->interpreter->enabled(),
            'model'      => Setting::get('agents', 'orchestrator_ai_model') ?: OrchestratorInterpreter::DEFAULT_MODEL,
        ];
    }

    private function present(OrchestratorMessage $m): array
    {
        return [
            'id'          => $m->id,
            'role'        => $m->role,
            'body'        => $m->body,
            'links'       => $m->meta['links'] ?? [],
            'suggestions' => $m->meta['suggestions'] ?? [],
            'images'      => $m->meta['images'] ?? [],
            'files'       => $m->meta['files'] ?? [],
            'ai'          => (bool) ($m->meta['ai'] ?? false),
            'warning'     => (bool) ($m->meta['warning'] ?? false),
            'error'       => (bool) ($m->meta['error'] ?? false),
            'event_id'    => $m->meta['event_id'] ?? null,
            'attachments' => $m->meta['attachments'] ?? [],
            'created_at'  => $m->created_at,
        ];
    }

    private function ensureInteractiveUser(Request $request): void
    {
        // Jeton de connexion (abilities « * ») ou session : autorisé. Jeton à
        // abilities restreintes (agents IA) : refusé.
        $token = $request->user()?->currentAccessToken();
        abort_if($token && !$token->can('agents:orchestrator'), 403, 'Réservé aux utilisateurs connectés.');
    }
}
