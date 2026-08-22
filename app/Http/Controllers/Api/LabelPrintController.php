<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use App\Services\TsplLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Native label printing: renders the designer template to TSPL and gets it
 * onto a thermal printer without the browser's print pipeline in the way.
 *
 * Two transports, because where the printer sits decides what can reach it:
 *
 *  - `agent`  (default) — the browser fetches the payload from `payload()`
 *    and POSTs it to a tiny agent listening on the cashier's own machine,
 *    which forwards the bytes to the printer. This is the only thing that
 *    works for a VPS-hosted tenant: the shop's printer lives on a private
 *    LAN the server cannot see.
 *  - `server` — Laravel opens the socket itself, for installs running on
 *    the same network as the printer (on-prem, Laragon on the shop PC).
 */
class LabelPrintController extends Controller
{
    /** A runaway batch would tie up the printer for hours. */
    private const MAX_COPIES = 1000;

    /**
     * Ports the `server` transport may dial.
     *
     * SECURITY: the host/port pair comes from tenant settings, so an admin
     * could otherwise turn this endpoint into a blind SSRF probe against
     * anything the VPS can reach. Raw-printing ports only.
     */
    private const ALLOWED_PORTS = [515, 6101, 9100, 9101, 9102, 9103];

    public function __construct(private TsplLabelService $tspl)
    {
    }

    /**
     * Render the job and hand it back for the local agent to deliver.
     */
    public function payload(Request $request): JsonResponse
    {
        $data     = $this->validated($request);
        $printer  = $this->printerConfig();
        $template = $this->template($data);

        // Base64 et non texte brut : le TSPL est encodé en CP1252 pour que le
        // firmware sorte les accents, donc ce n'est pas de l'UTF-8 valide et
        // json_encode() le rejetterait.
        return response()->json([
            'payload_base64' => base64_encode($this->tspl->render($template, $this->items($data), $printer)),
            'transport'      => $printer['transport'],
            'agent_url'      => $printer['agent_url'],
            'printer_name'   => $printer['name'],
            'host'           => $printer['host'],
            'port'           => $printer['port'],
        ]);
    }

    /**
     * Render the job and push it straight to the printer from the server.
     */
    public function print(Request $request): JsonResponse
    {
        $data    = $this->validated($request);
        $printer = $this->printerConfig();

        if ($printer['host'] === '') {
            return response()->json([
                'message' => "Aucune adresse d'imprimante configurée. Renseignez-la dans les réglages d'étiquettes.",
            ], 422);
        }

        if (!in_array($printer['port'], self::ALLOWED_PORTS, true)) {
            return response()->json([
                'message' => 'Port d\'impression non autorisé : ' . $printer['port']
                    . '. Ports admis : ' . implode(', ', self::ALLOWED_PORTS) . '.',
            ], 422);
        }

        $items   = $this->items($data);
        $payload = $this->tspl->render($this->template($data), $items, $printer);

        try {
            $this->tspl->sendRaw($payload, $printer['host'], $printer['port']);
        } catch (Throwable $e) {
            // Une erreur de socket nomme l'hote et le port de l'imprimante.
            return $this->failed(
                $e,
                "L'imprimante n'a pas répondu. Vérifiez qu'elle est allumée et joignable sur le réseau.",
                422,
                ['printer' => $printer['name'] ?? null],
            );
        }

        return response()->json([
            'message' => 'Travail envoyé à l\'imprimante.',
            'labels'  => $items->sum(fn (array $i) => $i['qty']),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty'        => ['required', 'integer', 'min:1', 'max:500'],
            // Optional so a non-admin — who cannot persist the template — can
            // still print the layout they just tweaked on screen.
            'template'           => ['sometimes', 'array'],
        ]);

        $copies = array_sum(array_column($data['items'], 'qty'));
        if ($copies > self::MAX_COPIES) {
            abort(422, 'Trop d\'étiquettes en une fois (' . $copies . '). Maximum : ' . self::MAX_COPIES . '.');
        }

        return $data;
    }

    /**
     * Resolve the requested products, preserving the order and quantities the
     * client asked for.
     *
     * @param  array<string,mixed> $data
     * @return Collection<int,array{product:Product,qty:int}>
     */
    private function items(array $data): Collection
    {
        $ids = array_column($data['items'], 'product_id');

        $products = Product::with(['category', 'brand'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($data['items'] as $i) {
            $product = $products->get($i['product_id']);
            if ($product instanceof Product) {
                $items[] = ['product' => $product, 'qty' => (int) $i['qty']];
            }
        }

        return collect($items);
    }

    /**
     * The client's in-progress template wins; otherwise fall back to the one
     * the designer saved for the tenant.
     *
     * @param  array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function template(array $data): array
    {
        if (isset($data['template']) && $data['template'] !== []) {
            return $data['template'];
        }

        $stored = Setting::get('labels', 'template');
        $decoded = $stored ? json_decode($stored, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * `browser` keeps the old HTML/`window.print()` path (the client never
     * calls this controller then); `agent` and `server` are the two TSPL
     * deliveries described in the class docblock.
     */
    private function transport(): string
    {
        $value = (string) Setting::get('labels', 'printer_transport', 'browser');

        return in_array($value, ['browser', 'agent', 'server'], true) ? $value : 'browser';
    }

    /**
     * @return array{transport:string,name:string,host:string,port:int,agent_url:string,dpi:int,darkness:int,speed:float,gap:float,direction:int}
     */
    private function printerConfig(): array
    {
        return [
            'transport' => $this->transport(),
            // File d'impression Windows choisie dans la page ; l'agent s'en
            // sert plutôt que de sa propre cible configurée au lancement.
            'name'      => trim((string) Setting::get('labels', 'printer_name', '')),
            'host'      => trim((string) Setting::get('labels', 'printer_host', '')),
            'port'      => (int) (Setting::get('labels', 'printer_port', '9100') ?: 9100),
            'agent_url' => trim((string) Setting::get('labels', 'agent_url', 'http://127.0.0.1:9110/print')),
            'dpi'       => (int) (Setting::get('labels', 'printer_dpi', (string) TsplLabelService::DEFAULT_DPI) ?: TsplLabelService::DEFAULT_DPI),
            'darkness'  => (int) (Setting::get('labels', 'printer_darkness', '10') ?: 10),
            'speed'     => (float) (Setting::get('labels', 'printer_speed', '4') ?: 4),
            'gap'       => (float) (Setting::get('labels', 'printer_gap', '2') ?: 2),
            'direction' => (int) Setting::get('labels', 'printer_direction', '1'),
        ];
    }
}
