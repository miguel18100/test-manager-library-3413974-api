<?php

namespace App\Http\Middleware;

use App\Application\Ports\TokenIssuer;
use App\Domain\DomainError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateWithJwt
{
    public function __construct(private readonly TokenIssuer $tokens) {}

    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json([
                'code'    => DomainError::INVALID_CREDENTIALS,
                'message' => 'Token de autorización ausente o inválido.',
                'details' => [],
            ], 401);
        }

        $payload = $this->tokens->verify($token);

        if (! $payload) {
            return response()->json([
                'code'    => DomainError::INVALID_CREDENTIALS,
                'message' => 'El token ha expirado o no es válido.',
                'details' => [],
            ], 401);
        }

        if ($requiredRole && $payload['role'] !== $requiredRole) {
            return response()->json([
                'code'    => DomainError::FORBIDDEN_ROLE,
                'message' => 'No tiene permisos suficientes para ejecutar esta acción.',
                'details' => [],
            ], 403);
        }

        // Inyectar usuario autenticado
        $request->attributes->set('auth_user_id', $payload['sub']);
        $request->attributes->set('auth_role', $payload['role']);

        return $next($request);
    }
}

