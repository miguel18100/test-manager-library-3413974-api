<?php

namespace App\Adapters\Persistence;

use App\Application\Ports\CategoryRepository;
use Illuminate\Support\Facades\DB;

final class PostgresCategoryRepository implements CategoryRepository
{
    public function find(string $id): ?array
    {
        $row = DB::table('library.category')->where('id', $id)->first();
        if (! $row) {
            return null;
        }

        return ['id' => $row->id, 'name' => $row->name];
    }

    public function all(): array
    {
        return DB::table('library.category')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
            ->all();
    }
}

