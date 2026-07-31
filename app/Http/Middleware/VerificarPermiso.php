<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarPermiso
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$permisos
    ): Response {
        $usuario = $request->user();

        if (! $usuario) {
            abort(401);
        }

        if ($permisos === [] || ! $usuario->puedeAlguno(...$permisos)) {
            abort(
                403,
                'Tu perfil no tiene permiso para realizar esta acción.'
            );
        }

        return $next($request);
    }
}
