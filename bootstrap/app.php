<?php

use App\Domain\DomainError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DomainError $e, Request $request) {
            $statusMap = [
                DomainError::NOT_FOUND             => 404,
                DomainError::INVALID_CREDENTIALS   => 401,
                DomainError::USERNAME_TAKEN        => 409,
                DomainError::ISBN_TAKEN            => 409,
                DomainError::DOCUMENT_TAKEN        => 409,
                DomainError::BOOK_HAS_OPEN_LOANS   => 409,
                DomainError::BOOK_NOT_AVAILABLE    => 409,
                DomainError::MEMBER_HAS_OVERDUE    => 409,
                DomainError::ITEM_ALREADY_RETURNED => 409,
                DomainError::MEMBER_LIMIT_EXCEEDED => 422,
                DomainError::DUPLICATE_BOOK_IN_LOAN=> 422,
                DomainError::INVALID_DATE_RANGE    => 422,
                DomainError::VALIDATION_ERROR      => 422,
            ];

            $status = $statusMap[$e->errorCode] ?? 400;

            return response()->json([
                'code'    => $e->errorCode,
                'message' => $e->getMessage(),
                'details' => $e->details,
            ], $status);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            $details = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $details[] = ['field' => $field, 'issue' => $msg];
                }
            }

            return response()->json([
                'code'    => 'VALIDATION_ERROR',
                'message' => 'Error de validación en los datos enviados.',
                'details' => $details,
            ], 422);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'code'    => 'NOT_FOUND',
                'message' => 'El recurso no existe.',
                'details' => [],
            ], 404);
        });
    })->create();
