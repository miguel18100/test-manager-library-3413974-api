<?php

namespace App\Adapters\Persistence;

use App\Application\Ports\UserRepository;
use App\Domain\DomainError;
use App\Domain\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PostgresUserRepository implements UserRepository
{
    public function findByUsername(string $normalizedUsername): ?User
    {
        $row = DB::table('library.user')
            ->where('username', $normalizedUsername)
            ->first();

        if (! $row) {
            return null;
        }

        return User::restore($row->id, $row->username, $row->password_hash, $row->role);
    }

    public function add(User $user): void
    {
        try {
            DB::table('library.user')->insert([
                'id'            => $user->id,
                'username'      => $user->username,
                'password_hash' => $user->passwordHash,
                'role'          => $user->role,
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ux_user_username') || str_contains($e->getMessage(), 'unique')) {
                throw new DomainError(
                    DomainError::USERNAME_TAKEN,
                    "El nombre de usuario '{$user->username}' ya está registrado.",
                    [['field' => 'username', 'issue' => 'ya existe']],
                );
            }
            throw $e;
        }
    }
}

