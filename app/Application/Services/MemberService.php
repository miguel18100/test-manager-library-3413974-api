<?php

namespace App\Application\Services;

use App\Application\Page;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\MemberRepository;
use App\Domain\DomainError;
use App\Domain\Member;

/**
 * HU-05: Gestión de socios.
 */
final class MemberService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly IdGenerator $ids,
    ) {}

    public function create(string $fullName, string $documentNumber): array
    {
        $id = $this->ids->next();
        $member = Member::create($id, $fullName, $documentNumber);
        $this->members->add($member);

        return [
            'id'              => $member->id,
            'full_name'       => $member->fullName,
            'document_number' => $member->documentNumber,
        ];
    }

    public function get(string $id): array
    {
        $member = $this->members->get($id);
        if ($member === null) {
            throw DomainError::notFound('El socio no existe.');
        }

        $activeLoans = $this->members->activeLoansCount($id);

        return [
            'id'              => $member->id,
            'full_name'       => $member->fullName,
            'document_number' => $member->documentNumber,
            'active_loans'    => $activeLoans,
        ];
    }

    public function search(?string $q, int $page, int $size): Page
    {
        $page = max(1, $page);
        $size = min(50, max(1, $size));

        return $this->members->search($q, $page, $size);
    }
}

