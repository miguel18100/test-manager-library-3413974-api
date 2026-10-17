<?php

namespace App\Domain;

/**
 * Error de negocio con código estable (api-contract.md §2).
 * El dominio y la aplicación solo lanzan esto; el adaptador HTTP lo traduce a estado + cuerpo único.
 */
final class DomainError extends \RuntimeException
{
    public const NOT_FOUND = 'NOT_FOUND';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    public const USERNAME_TAKEN = 'USERNAME_TAKEN';
    public const ISBN_TAKEN = 'ISBN_TAKEN';
    public const DOCUMENT_TAKEN = 'DOCUMENT_TAKEN';
    public const BOOK_HAS_OPEN_LOANS = 'BOOK_HAS_OPEN_LOANS';
    public const BOOK_NOT_AVAILABLE = 'BOOK_NOT_AVAILABLE';
    public const MEMBER_HAS_OVERDUE = 'MEMBER_HAS_OVERDUE';
    public const ITEM_ALREADY_RETURNED = 'ITEM_ALREADY_RETURNED';
    public const MEMBER_LIMIT_EXCEEDED = 'MEMBER_LIMIT_EXCEEDED';
    public const DUPLICATE_BOOK_IN_LOAN = 'DUPLICATE_BOOK_IN_LOAN';
    public const INVALID_DATE_RANGE = 'INVALID_DATE_RANGE';
    public const FORBIDDEN_ROLE = 'FORBIDDEN_ROLE';

    /** @param list<array{field:string, issue:string}> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $message = 'El recurso no existe.'): self
    {
        return new self(self::NOT_FOUND, $message);
    }

    public static function validation(string $message, string $field = '', string $issue = ''): self
    {
        $details = $field !== '' ? [['field' => $field, 'issue' => $issue !== '' ? $issue : $message]] : [];

        return new self(self::VALIDATION_ERROR, $message, $details);
    }
}

