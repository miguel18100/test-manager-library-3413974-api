<?php

namespace App\Adapters\Persistence;

use App\Application\Page;
use App\Application\Ports\BookRepository;
use App\Domain\Book;
use App\Domain\DomainError;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PostgresBookRepository implements BookRepository
{
    public function getActive(string $id): ?Book
    {
        $row = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->where('b.id', $id)
            ->whereNull('b.deleted_at')
            ->select('b.*', 'c.name as category_name')
            ->first();

        if (! $row) {
            return null;
        }

        return $this->mapRow($row);
    }

    public function getAny(string $id): ?Book
    {
        $row = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->where('b.id', $id)
            ->select('b.*', 'c.name as category_name')
            ->first();

        if (! $row) {
            return null;
        }

        return $this->mapRow($row);
    }

    public function getManyActive(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereIn('b.id', $ids)
            ->whereNull('b.deleted_at')
            ->select('b.*', 'c.name as category_name')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->id] = $this->mapRow($r);
        }

        return $map;
    }

    public function add(Book $book): void
    {
        try {
            DB::table('library.book')->insert([
                'id'               => $book->id,
                'title'            => $book->title(),
                'author'           => $book->author(),
                'isbn'             => $book->isbn->value,
                'category_id'      => $book->categoryId(),
                'total_copies'     => $book->totalCopies(),
                'available_copies' => $book->availableCopies(),
                'cover_key'        => $book->coverKey(),
                'deleted_at'       => null,
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ux_book_isbn_active')) {
                throw new DomainError(
                    DomainError::ISBN_TAKEN,
                    "El ISBN {$book->isbn->value} ya está registrado en otro libro activo.",
                    [['field' => 'isbn', 'issue' => 'ya existe']],
                );
            }
            throw $e;
        }
    }

    public function replace(Book $book): void
    {
        DB::table('library.book')
            ->where('id', $book->id)
            ->update([
                'title'            => $book->title(),
                'author'           => $book->author(),
                'category_id'      => $book->categoryId(),
                'total_copies'     => $book->totalCopies(),
                'available_copies' => $book->availableCopies(),
            ]);
    }

    public function markDeleted(Book $book): void
    {
        DB::table('library.book')
            ->where('id', $book->id)
            ->update([
                'deleted_at' => $book->deletedAt()?->format('Y-m-d H:i:sO'),
            ]);
    }

    public function setCover(string $bookId, ?string $coverKey): void
    {
        DB::table('library.book')
            ->where('id', $bookId)
            ->update(['cover_key' => $coverKey]);
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return DB::table('library.loan_item')
            ->where('book_id', $bookId)
            ->whereNull('returned_at')
            ->exists();
    }

    public function search(?string $q, ?string $categoryId, int $page, int $size): Page
    {
        $query = DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereNull('b.deleted_at');

        if ($categoryId) {
            $query->where('b.category_id', $categoryId);
        }

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('b.title', 'ilike', "%{$q}%")
                    ->orWhere('b.author', 'ilike', "%{$q}%")
                    ->orWhere('b.isbn', 'like', "%{$q}%");
            });
        }

        $total = $query->count();
        $offset = ($page - 1) * $size;

        $rows = $query->select('b.*', 'c.name as category_name')
            ->orderBy('b.title')
            ->offset($offset)
            ->limit($size)
            ->get();

        $items = $rows->map(fn ($r) => $this->mapRow($r))->all();

        return new Page($items, $page, $size, $total);
    }

    private function mapRow(object $r): Book
    {
        $deletedAt = $r->deleted_at ? new DateTimeImmutable($r->deleted_at) : null;

        return Book::restore(
            $r->id,
            $r->title,
            $r->author,
            $r->isbn,
            $r->category_id,
            $r->category_name,
            (int) $r->total_copies,
            (int) $r->available_copies,
            $r->cover_key,
            $deletedAt,
        );
    }
}

