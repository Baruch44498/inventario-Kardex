<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarRol
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $usuario = $request->user();

        if (! $usuario) {
            abort(401);
        }

        if (! $usuario->tieneRol(...$roles)) {
            abort(403, 'Tu perfil no puede acceder a este módulo.');
        }

        return $next($request);
    }
}
