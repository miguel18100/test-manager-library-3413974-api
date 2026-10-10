<?php

namespace App\Http\Controllers;

use App\Application\Ports\CategoryRepository;
use App\Application\Services\BookService;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\LoanService;
use App\Application\Services\LoginService;
use App\Application\Services\MemberService;
use App\Application\Services\ReportService;
use App\Domain\DomainError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LibraryApiController extends Controller
{
    // GET /health
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    // POST /auth/login
    public function login(Request $request, LoginService $service): JsonResponse
    {
        $username = $request->input('username', '');
        $password = $request->input('password', '');

        $result = $service->execute($username, $password);

        return response()->json($result);
    }

    // POST /users
    public function createUser(Request $request, CreateLibrarianService $service): JsonResponse
    {
        $username = $request->input('username', '');
        $password = $request->input('password', '');
        $role = $request->input('role', '');

        $result = $service->execute($username, $password, $role);

        return response()->json($result, 201);
    }

    // GET /categories
    public function listCategories(CategoryRepository $categories): JsonResponse
    {
        return response()->json($categories->all());
    }

    // GET /books
    public function listBooks(Request $request, BookService $service): JsonResponse
    {
        $q = $request->query('q');
        $categoryId = $request->query('category_id');
        $page = (int) $request->query('page', 1);
        $size = (int) $request->query('size', 20);

        $result = $service->search($q, $categoryId, $page, $size);

        return response()->json($result->toArray());
    }

    // GET /books/{id}
    public function getBook(string $id, BookService $service): JsonResponse
    {
        return response()->json($service->getActive($id));
    }

    // POST /books
    public function createBook(Request $request, BookService $service): JsonResponse
    {
        $title = (string) $request->input('title', '');
        $author = (string) $request->input('author', '');
        $isbn = (string) $request->input('isbn', '');
        $categoryId = (string) $request->input('category_id', '');
        $totalCopies = (int) $request->input('total_copies', 0);

        $result = $service->create($title, $author, $isbn, $categoryId, $totalCopies);

        return response()->json($result, 201);
    }

    // PUT /books/{id}
    public function updateBook(string $id, Request $request, BookService $service): JsonResponse
    {
        $title = (string) $request->input('title', '');
        $author = (string) $request->input('author', '');
        $categoryId = (string) $request->input('category_id', '');
        $totalCopies = (int) $request->input('total_copies', 0);

        $result = $service->replace($id, $title, $author, $categoryId, $totalCopies);

        return response()->json($result);
    }

    // DELETE /books/{id}
    public function deleteBook(string $id, BookService $service): Response
    {
        $service->markDeleted($id);

        return response()->noContent();
    }

    // PUT /books/{id}/cover
    public function uploadCover(string $id, Request $request, BookService $service): Response
    {
        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            throw DomainError::validation('El archivo de portada es obligatorio.', 'file', 'archivo no proporcionado');
        }

        $bytes = file_get_contents($file->getRealPath());
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';

        $service->uploadCover($id, $bytes, $mimeType);

        return response()->noContent();
    }

    // DELETE /books/{id}/cover
    public function removeCover(string $id, BookService $service): Response
    {
        $service->removeCover($id);

        return response()->noContent();
    }

    // GET /books/{id}/cover
    public function getCover(string $id, BookService $service): Response
    {
        $cover = $service->getCoverBytes($id);

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($cover['bytes']) ?: 'image/jpeg';

        return response($cover['bytes'])->header('Content-Type', $mime);
    }

    // GET /members
    public function listMembers(Request $request, MemberService $service): JsonResponse
    {
        $q = $request->query('q');
        $page = (int) $request->query('page', 1);
        $size = (int) $request->query('size', 20);

        $result = $service->search($q, $page, $size);

        return response()->json($result->toArray());
    }

    // GET /members/{id}
    public function getMember(string $id, MemberService $service): JsonResponse
    {
        return response()->json($service->get($id));
    }

    // POST /members
    public function createMember(Request $request, MemberService $service): JsonResponse
    {
        $fullName = (string) $request->input('full_name', '');
        $doc = (string) $request->input('document_number', '');

        $result = $service->create($fullName, $doc);

        return response()->json($result, 201);
    }

    // POST /loans
    public function createLoan(Request $request, LoanService $service): JsonResponse
    {
        $memberId = (string) $request->input('member_id', '');
        $items = (array) $request->input('items', []);
        $userId = (string) $request->attributes->get('auth_user_id');

        $result = $service->createLoan($memberId, $items, $userId);

        return response()->json($result, 201);
    }

    // GET /loans
    public function listLoans(Request $request, LoanService $service): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $status = $request->query('status');
        $memberId = $request->query('member_id');
        $page = (int) $request->query('page', 1);
        $size = (int) $request->query('size', 20);

        $result = $service->listLoans($from, $to, $status, $memberId, $page, $size);

        return response()->json($result->toArray());
    }

    // GET /loans/{id}
    public function getLoan(string $id, LoanService $service): JsonResponse
    {
        return response()->json($service->getLoan($id));
    }

    // POST /loans/{loanId}/items/{itemId}/return
    public function returnLoanItem(string $loanId, string $itemId, LoanService $service): JsonResponse
    {
        $result = $service->returnItem($loanId, $itemId);

        return response()->json($result);
    }

    // GET /loans/overdue
    public function listOverdue(Request $request, LoanService $service): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $size = (int) $request->query('size', 50);

        $result = $service->overdue($page, $size);

        return response()->json($result->toArray());
    }

    // GET /reports/most-borrowed
    public function reportMostBorrowed(Request $request, ReportService $service): JsonResponse
    {
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        if (! $from || ! $to) {
            throw DomainError::validation('Los parámetros from y to son obligatorios.', 'from', 'obligatorio');
        }

        $result = $service->mostBorrowed($from, $to);

        return response()->json($result);
    }
}

