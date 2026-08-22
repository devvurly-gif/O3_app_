<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Exceptions que Laravel sait déjà traduire en réponse HTTP correcte, avec
     * un message destiné à l'utilisateur. Elles doivent suivre leur chemin
     * normal : une 404 doit rester une 404, une erreur de validation doit
     * garder le détail par champ.
     *
     * @var array<int, class-string<Throwable>>
     */
    private const HANDLED_BY_LARAVEL = [
        HttpExceptionInterface::class,
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        RecordsNotFoundException::class,
        TokenMismatchException::class,
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        /**
         * Enveloppe JSON stable pour les exceptions non gérées de l'API.
         *
         * Sans elle, une exception imprévue partait au client sous une forme
         * qui dépendait d'APP_DEBUG : en production un message vide, en
         * développement une pile d'appels complète. Aucune des deux n'est
         * exploitable, et la seconde est une fuite.
         *
         * L'appelant reçoit désormais toujours la même forme, avec une
         * référence courte qui figure aussi dans le journal — c'est ce qui
         * permet de relier un signalement utilisateur à une trace précise.
         */
        $this->renderable(function (Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            // En débogage, la page d'erreur de Laravel et sa pile d'appels sont
            // bien plus utiles : on ne s'interpose pas.
            if (config('app.debug')) {
                return null;
            }

            foreach (self::HANDLED_BY_LARAVEL as $handled) {
                if ($e instanceof $handled) {
                    return null;
                }
            }

            $ref = Str::lower(Str::random(8));

            Log::error('Exception non gérée', [
                'ref'       => $ref,
                'method'    => $request->method(),
                'url'       => $request->fullUrl(),
                'user_id'   => $request->user()?->id,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => "Une erreur inattendue s'est produite. "
                    . 'Communiquez la référence ci-dessous au support.',
                'ref'     => $ref,
            ], 500);
        });
    }
}
