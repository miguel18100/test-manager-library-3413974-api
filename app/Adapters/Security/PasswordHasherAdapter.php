<?php

namespace App\Adapters\Security;

use App\Application\Ports\PasswordHasher;

final class PasswordHasherAdapter implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}

