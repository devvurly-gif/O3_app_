<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Journalise une exception et renvoie une réponse sûre.
     *
     * Le message d'une exception tierce — analyseur PDF, client SMTP, lecteur
     * Excel, socket d'imprimante — expose volontiers des chemins de fichiers,
     * des hôtes et des fragments de configuration. Il n'a rien à faire dans une
     * réponse HTTP.
     *
     * Il part donc au journal avec une référence, et l'appelant reçoit un
     * message métier accompagné de cette même référence : le support retrouve
     * la trace exacte sans que l'utilisateur ait eu à lire une pile d'appels.
     *
     * À n'utiliser que pour les exceptions venues de l'extérieur. Une
     * DomainException levée par l'application porte un message écrit pour
     * l'utilisateur : celui-là se renvoie tel quel.
     *
     * @param  array<string, mixed>  $context
     */
    protected function failed(
        Throwable $e,
        string $message,
        int $status = 422,
        array $context = [],
    ): JsonResponse {
        $ref = Str::lower(Str::random(8));

        Log::error($message, $context + [
            'ref'       => $ref,
            'exception' => $e,
        ]);

        return response()->json([
            'message' => $message,
            'ref'     => $ref,
        ], $status);
    }
}
