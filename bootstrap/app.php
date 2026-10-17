<?php

use App\Domain\DomainError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);

        $exceptions->render(function (DomainError $e, Request $request) {
            $statusMap = [
                DomainError::NOT_FOUND             => 404,
                DomainError::INVALID_CREDENTIALS   => 401,
                DomainError::FORBIDDEN_ROLE        => 403,
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

        // Un identificador que no es UUID llega al motor como error 22P02: se responde como
        // «no existe» si va en la ruta, o como validación si va en el cuerpo (RNF-4).
        $exceptions->render(function (QueryException $e, Request $request) {
            if (($e->errorInfo[0] ?? '') !== '22P02') {
                return null;
            }
            if ($request->route()?->parameters()) {
                return response()->json(['code' => 'NOT_FOUND', 'message' => 'El recurso no existe.', 'details' => []], 404);
            }

            return response()->json([
                'code'    => 'VALIDATION_ERROR',
                'message' => 'Un identificador enviado no tiene el formato esperado.',
                'details' => [],
            ], 422);
        });

        // Cualquier otro fallo: mismo cuerpo único, sin trazas, SQL ni rutas del servidor (api-contract §1).
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return null;
            }

            return response()->json([
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Ocurrió un error inesperado. Intente de nuevo más tarde.',
                'details' => [],
            ], 500);
        });
    })->create();
