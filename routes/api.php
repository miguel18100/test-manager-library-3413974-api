<?php

use App\Http\Controllers\LibraryApiController;
use App\Http\Middleware\AuthenticateWithJwt;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // 3.1 Salud y Autenticación (Públicos)
    Route::get('/health', [LibraryApiController::class, 'health']);
    Route::post('/auth/login', [LibraryApiController::class, 'login']);

    // Rutas protegidas solo para rol Admin
    Route::middleware([AuthenticateWithJwt::class.':admin'])->group(function () {
        Route::post('/users', [LibraryApiController::class, 'createUser']);

        Route::post('/books', [LibraryApiController::class, 'createBook']);
        Route::put('/books/{id}', [LibraryApiController::class, 'updateBook']);
        Route::delete('/books/{id}', [LibraryApiController::class, 'deleteBook']);
        Route::put('/books/{id}/cover', [LibraryApiController::class, 'uploadCover']);
        Route::delete('/books/{id}/cover', [LibraryApiController::class, 'removeCover']);

        Route::get('/reports/most-borrowed', [LibraryApiController::class, 'reportMostBorrowed']);
    });

    // Rutas protegidas para usuarios autenticados (admin y librarian)
    Route::middleware([AuthenticateWithJwt::class])->group(function () {
        Route::get('/categories', [LibraryApiController::class, 'listCategories']);

        Route::get('/books', [LibraryApiController::class, 'listBooks']);
        Route::get('/books/{id}', [LibraryApiController::class, 'getBook']);
        Route::get('/books/{id}/cover', [LibraryApiController::class, 'getCover']);

        Route::get('/members', [LibraryApiController::class, 'listMembers']);
        Route::get('/members/{id}', [LibraryApiController::class, 'getMember']);
        Route::post('/members', [LibraryApiController::class, 'createMember']);

        Route::post('/loans', [LibraryApiController::class, 'createLoan']);
        Route::get('/loans', [LibraryApiController::class, 'listLoans']);
        Route::get('/loans/overdue', [LibraryApiController::class, 'listOverdue']);
        Route::get('/loans/{id}', [LibraryApiController::class, 'getLoan']);
        Route::post('/loans/{loanId}/items/{itemId}/return', [LibraryApiController::class, 'returnLoanItem']);
    });
});

