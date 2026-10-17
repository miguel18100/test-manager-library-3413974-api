<?php

namespace Tests\Unit;

use App\Domain\DomainError;
use App\Domain\Member;
use App\Domain\User;
use PHPUnit\Framework\TestCase;

/** T-06 — User y Member: normalización y rol válido (CA-02.2, CA-02.3, CA-05.1). */
final class UserAndMemberTest extends TestCase
{
    public function test_username_is_trimmed_and_lowercased(): void
    {
        $user = User::createLibrarian('u1', 'Ana ', 'hash', 'librarian');
        $this->assertSame('ana', $user->username);
        $this->assertSame(User::LIBRARIAN, $user->role);
    }

    public function test_admin_role_through_the_api_is_rejected(): void
    {
        try {
            User::createLibrarian('u1', 'ana', 'hash', 'admin');
            $this->fail('Debió lanzar VALIDATION_ERROR.');
        } catch (DomainError $e) {
            $this->assertSame(DomainError::VALIDATION_ERROR, $e->errorCode);
        }
    }

    public function test_member_requires_name_and_document(): void
    {
        $member = Member::create('m1', '  Luis Pérez ', ' 1098765 ');
        $this->assertSame('Luis Pérez', $member->fullName);
        $this->assertSame('1098765', $member->documentNumber);

        $this->expectException(DomainError::class);
        Member::create('m2', '', '123');
    }
}
