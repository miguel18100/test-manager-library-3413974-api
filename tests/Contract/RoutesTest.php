<?php

namespace Tests\Contract;

use App\Domain\DomainError;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** T-16 — las rutas expuestas son exactamente las de api-contract.md §3 (ni una más, ni una menos). */
final class RoutesTest extends TestCase
{
    private const CONTRACT = [
        'POST api/v1/auth/login',
        'GET api/v1/health',
        'POST api/v1/users',
        'GET api/v1/categories',
        'GET api/v1/books',
        'GET api/v1/books/{id}',
        'POST api/v1/books',
        'PUT api/v1/books/{id}',
        'DELETE api/v1/books/{id}',
        'PUT api/v1/books/{id}/cover',
        'DELETE api/v1/books/{id}/cover',
        'GET api/v1/books/{id}/cover',
        'GET api/v1/members',
        'GET api/v1/members/{id}',
        'POST api/v1/members',
        'POST api/v1/loans',
        'GET api/v1/loans',
        'GET api/v1/loans/{id}',
        'POST api/v1/loans/{loanId}/items/{itemId}/return',
        'GET api/v1/loans/overdue',
        'GET api/v1/reports/most-borrowed',
    ];

    public function test_exposed_routes_match_the_contract(): void
    {
        $exposed = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $exposed[] = $method.' '.$route->uri();
            }
        }
        sort($exposed);
        $expected = self::CONTRACT;
        sort($expected);

        $this->assertSame($expected, $exposed);
    }

    public function test_health_is_public_and_returns_ok(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_protected_routes_without_token_answer_401_with_the_single_error_body(): void
    {
        $this->getJson('/api/v1/books')
            ->assertStatus(401)
            ->assertJsonStructure(['code', 'message', 'details'])
            ->assertJson(['code' => DomainError::INVALID_CREDENTIALS]);
    }

    public function test_librarian_token_on_an_admin_route_answers_403_forbidden_role(): void
    {
        $token = $this->app->make(\App\Application\Ports\TokenIssuer::class)->issue('u1', 'librarian')['access_token'];

        $this->withToken($token)->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31')
            ->assertStatus(403)
            ->assertJson(['code' => DomainError::FORBIDDEN_ROLE]);
    }

    public function test_unknown_route_uses_the_single_error_body(): void
    {
        $this->getJson('/api/v1/no-existe')->assertStatus(404)->assertJson(['code' => 'NOT_FOUND', 'details' => []]);
    }

    public function test_every_contract_error_code_exists_in_the_domain(): void
    {
        $codes = ['INVALID_CREDENTIALS', 'FORBIDDEN_ROLE', 'NOT_FOUND', 'USERNAME_TAKEN', 'ISBN_TAKEN', 'DOCUMENT_TAKEN',
            'BOOK_HAS_OPEN_LOANS', 'BOOK_NOT_AVAILABLE', 'MEMBER_HAS_OVERDUE', 'ITEM_ALREADY_RETURNED',
            'MEMBER_LIMIT_EXCEEDED', 'DUPLICATE_BOOK_IN_LOAN', 'VALIDATION_ERROR', 'INVALID_DATE_RANGE'];

        foreach ($codes as $code) {
            $this->assertSame($code, constant(DomainError::class.'::'.$code));
        }
    }
}
