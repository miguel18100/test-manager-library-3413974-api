<?php

namespace App\Adapters\Security;

use App\Application\Ports\TokenIssuer;

final class JwtTokenIssuer implements TokenIssuer
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $resolved = $secret ?: config('library.jwt_secret');
        if (! is_string($resolved) || $resolved === '') {
            throw new \RuntimeException('JWT_SECRET no está configurado.');
        }
        $this->secret = $resolved;
    }

    public function issue(string $userId, string $role): array
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $expiresIn = 28800; // 8 horas
        $payload = [
            'sub'  => $userId,
            'role' => $role,
            'exp'  => time() + $expiresIn,
            'iat'  => time(),
        ];

        $b64Header = $this->base64UrlEncode(json_encode($header));
        $b64Payload = $this->base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$b64Header}.{$b64Payload}", $this->secret, true);
        $b64Signature = $this->base64UrlEncode($signature);

        return [
            'access_token' => "{$b64Header}.{$b64Payload}.{$b64Signature}",
            'expires_in'   => $expiresIn,
        ];
    }

    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$b64Header, $b64Payload, $b64Signature] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', "{$b64Header}.{$b64Payload}", $this->secret, true));

        if (! hash_equals($expectedSignature, $b64Signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($b64Payload), true);
        if (! is_array($payload) || ! isset($payload['sub'], $payload['role'], $payload['exp'])) {
            return null;
        }

        if (time() >= $payload['exp']) {
            return null;
        }

        return [
            'sub'  => $payload['sub'],
            'role' => $payload['role'],
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

