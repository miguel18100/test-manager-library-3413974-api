<?php

namespace App\Providers;

use App\Adapters\Infrastructure\DbTransactionRunner;
use App\Adapters\Infrastructure\DiskCoverStorage;
use App\Adapters\Infrastructure\SystemClock;
use App\Adapters\Infrastructure\UuidGenerator;
use App\Adapters\Persistence\PostgresBookRepository;
use App\Adapters\Persistence\PostgresCategoryRepository;
use App\Adapters\Persistence\PostgresLoanReader;
use App\Adapters\Persistence\PostgresLoanRepository;
use App\Adapters\Persistence\PostgresMemberRepository;
use App\Adapters\Persistence\PostgresReportReader;
use App\Adapters\Persistence\PostgresUserRepository;
use App\Adapters\Security\JwtTokenIssuer;
use App\Adapters\Security\PasswordHasherAdapter;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanReader;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\ReportReader;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\TransactionRunner;
use App\Application\Ports\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Artículo III: Un único punto de composición donde los puertos se encuentran con los adaptadores
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdGenerator::class, UuidGenerator::class);
        $this->app->singleton(TransactionRunner::class, DbTransactionRunner::class);
        $this->app->singleton(PasswordHasher::class, PasswordHasherAdapter::class);
        $this->app->singleton(TokenIssuer::class, JwtTokenIssuer::class);
        $this->app->singleton(CoverStorage::class, DiskCoverStorage::class);

        $this->app->singleton(UserRepository::class, PostgresUserRepository::class);
        $this->app->singleton(CategoryRepository::class, PostgresCategoryRepository::class);
        $this->app->singleton(BookRepository::class, PostgresBookRepository::class);
        $this->app->singleton(MemberRepository::class, PostgresMemberRepository::class);
        $this->app->singleton(LoanRepository::class, PostgresLoanRepository::class);
        $this->app->singleton(LoanReader::class, PostgresLoanReader::class);
        $this->app->singleton(ReportReader::class, PostgresReportReader::class);
    }

    public function boot(): void
    {
        //
    }
}

