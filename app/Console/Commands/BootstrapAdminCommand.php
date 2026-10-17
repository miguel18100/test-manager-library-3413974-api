<?php

namespace App\Console\Commands;

use App\Application\Ports\IdGenerator;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\User;
use Illuminate\Console\Command;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'library:bootstrap-admin';
    protected $description = 'Crea el usuario administrador inicial a partir de las variables de entorno (D-10, T-17)';

    public function handle(UserRepository $users, PasswordHasher $hasher, IdGenerator $ids): int
    {
        $rawUsername = config('library.admin_username');
        $rawPassword = config('library.admin_password');

        if (! $rawUsername || ! $rawPassword) {
            $this->warn('ADMIN_USERNAME o ADMIN_PASSWORD no están configurados en el entorno.');
            return self::FAILURE;
        }

        $normalized = User::normalizeUsername($rawUsername);
        $existing = $users->findByUsername($normalized);

        if ($existing) {
            $this->info("El usuario admin '{$normalized}' ya existe.");
            return self::SUCCESS;
        }

        $hash = $hasher->hash($rawPassword);
        $admin = User::createAdmin($ids->next(), $normalized, $hash);
        $users->add($admin);

        $this->info("Administrador inicial '{$normalized}' creado exitosamente.");
        return self::SUCCESS;
    }
}

