<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentPdfService;
use App\Services\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Modeles de documents imprimes, regles par le tenant.
 *
 * Volontairement separe de SettingController : la config est un objet
 * structure (couleurs, enums, colonnes) qui merite ses propres regles
 * de validation, la ou l'endpoint generique se contente de "chaine ou
 * null". Cf. la note de securite de DocumentTemplateService.
 */
class DocumentTemplateController extends Controller
{
    public function __construct(
        private DocumentTemplateService $templates,
        private DocumentPdfService $pdf,
    ) {
    }

    /** GET /api/document-templates */
    public function index(): JsonResponse
    {
        return response()->json([
            'types'     => DocumentTemplateService::TYPE_LABELS,
            'defaults'  => DocumentTemplateService::DEFAULTS,
            'configs'   => $this->templates->all(),
            'resolved'  => collect(DocumentTemplateService::types())
                ->mapWithKeys(fn (string $type) => [$type => $this->templates->resolve($type)]),
            'options'   => [
                'fonts'          => DocumentTemplateService::FONTS,
                'papers'         => DocumentTemplateService::PAPERS,
                'orientations'   => DocumentTemplateService::ORIENTATIONS,
                'table_styles'   => DocumentTemplateService::TABLE_STYLES,
                'logo_positions' => DocumentTemplateService::LOGO_POSITIONS,
                'columns'        => DocumentTemplateService::COLUMNS,
            ],
        ]);
    }

    /** PUT /api/document-templates/{type} */
    public function update(Request $request, string $type): JsonResponse
    {
        $this->assertKnownType($type);

        $data = $request->validate(
            DocumentTemplateService::rules('config') + [
                'config' => ['required', 'array'],
            ],
        );

        $config = DocumentTemplateService::sanitize($data['config']);
        $this->templates->save($type, $config);

        return response()->json([
            'message'  => 'Modèle enregistré.',
            'config'   => $config,
            'resolved' => $type === DocumentTemplateService::DEFAULT_KEY
                ? null
                : $this->templates->resolve($type),
        ]);
    }

    /**
     * DELETE /api/document-templates/{type}
     * Le type repasse au reglage general. Sur `default`, on revient a la
     * mise en page livree avec l'application.
     */
    public function destroy(string $type): JsonResponse
    {
        $this->assertKnownType($type);

        $this->templates->reset($type);

        return response()->json(['message' => 'Modèle réinitialisé.']);
    }

    /**
     * POST /api/document-templates/{type}/preview
     * Rend un document fictif avec la config postee (donc non encore
     * enregistree) pour que le tenant juge sur piece avant de valider.
     */
    public function preview(Request $request, string $type): Response
    {
        $this->assertKnownType($type);

        $data = $request->validate(
            DocumentTemplateService::rules('config') + [
                'config' => ['required', 'array'],
            ],
        );

        $config = DocumentTemplateService::harden(
            DocumentTemplateService::sanitize($data['config'])
        );

        $sampleType = $type === DocumentTemplateService::DEFAULT_KEY ? 'InvoiceSale' : $type;

        $config['title'] = ($config['title_override'] ?? '') !== ''
            ? $config['title_override']
            : (DocumentTemplateService::TYPE_LABELS[$sampleType] ?? $sampleType);

        return $this->pdf->preview($sampleType, $config)->stream('apercu-modele.pdf');
    }

    /** 404 explicite plutot qu'une cle `type_<n'importe quoi>` en base. */
    private function assertKnownType(string $type): void
    {
        $allowed = array_merge([DocumentTemplateService::DEFAULT_KEY], DocumentTemplateService::types());

        validator(['type' => $type], ['type' => [Rule::in($allowed)]])->validate();
    }
}
