<?php

namespace App\Adapters\Infrastructure;

use App\Application\Ports\TransactionRunner;
use Illuminate\Support\Facades\DB;

final class DbTransactionRunner implements TransactionRunner
{
    public function run(callable $work): mixed
    {
        return DB::transaction($work);
    }
}

