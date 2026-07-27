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

        if (!$usuario) {
            abort(401);
        }

        $codigoRol = $usuario->role?->codigo;

        if (!$codigoRol || !in_array($codigoRol, $roles, true)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }

        return $next($request);
    }
}
