<?php

namespace App\Adapters\Persistence;

use App\Application\Page;
use App\Application\Ports\MemberRepository;
use App\Domain\DomainError;
use App\Domain\Member;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PostgresMemberRepository implements MemberRepository
{
    public function get(string $id): ?Member
    {
        $row = DB::table('library.member')->where('id', $id)->first();
        if (! $row) {
            return null;
        }

        return Member::restore($row->id, $row->full_name, $row->document_number);
    }

    public function getForUpdate(string $id): ?Member
    {
        $row = DB::table('library.member')->where('id', $id)->lockForUpdate()->first();
        if (! $row) {
            return null;
        }

        return Member::restore($row->id, $row->full_name, $row->document_number);
    }

    public function add(Member $member): void
    {
        try {
            DB::table('library.member')->insert([
                'id'              => $member->id,
                'full_name'       => $member->fullName,
                'document_number' => $member->documentNumber,
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ux_member_document_number') || str_contains($e->getMessage(), 'unique')) {
                throw new DomainError(
                    DomainError::DOCUMENT_TAKEN,
                    "El número de documento '{$member->documentNumber}' ya está registrado.",
                    [['field' => 'document_number', 'issue' => 'ya existe']],
                );
            }
            throw $e;
        }
    }

    public function search(?string $q, int $page, int $size): Page
    {
        $query = DB::table('library.member');

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('full_name', 'ilike', "%{$q}%")
                    ->orWhere('document_number', 'like', "%{$q}%");
            });
        }

        $total = $query->count();
        $offset = ($page - 1) * $size;

        $rows = $query->orderBy('full_name')
            ->offset($offset)
            ->limit($size)
            ->get();

        $items = $rows->map(fn ($r) => [
            'id'              => $r->id,
            'full_name'       => $r->full_name,
            'document_number' => $r->document_number,
        ])->all();

        return new Page($items, $page, $size, $total);
    }

    public function activeLoansCount(string $memberId): int
    {
        return DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('i.returned_at')
            ->count();
    }
}

