<?php

namespace App\Domain;

/**
 * Agregado User: nombre normalizado (minúsculas y recortado, CA-02.2); rol en (admin, librarian).
 * El rol admin solo lo crea el arranque (spec §1); por API se rechaza (CA-02.3).
 */
final class User
{
    public const ADMIN = 'admin';
    public const LIBRARIAN = 'librarian';

    private function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly string $role,
    ) {}

    public static function normalizeUsername(string $raw): string
    {
        $u = mb_strtolower(trim($raw));
        if ($u === '' || mb_strlen($u) > 120) {
            throw DomainError::validation('El nombre de usuario es obligatorio (máximo 120 caracteres).', 'username', 'obligatorio, máximo 120');
        }

        return $u;
    }

    /** Alta por API: solo `librarian`. Pedir `admin` (o cualquier otro) => 422. */
    public static function createLibrarian(string $id, string $username, string $passwordHash, string $requestedRole): self
    {
        if ($requestedRole !== self::LIBRARIAN) {
            throw DomainError::validation('Por la API solo se pueden crear usuarios con rol librarian.', 'role', 'solo librarian');
        }

        return new self($id, self::normalizeUsername($username), $passwordHash, self::LIBRARIAN);
    }

    /** Solo lo usa el arranque con credenciales de entorno (D-10). */
    public static function createAdmin(string $id, string $username, string $passwordHash): self
    {
        return new self($id, self::normalizeUsername($username), $passwordHash, self::ADMIN);
    }

    public static function restore(string $id, string $username, string $passwordHash, string $role): self
    {
        if (! in_array($role, [self::ADMIN, self::LIBRARIAN], true)) {
            throw new \LogicException('Rol inválido en persistencia.');
        }

        return new self($id, $username, $passwordHash, $role);
    }
}

