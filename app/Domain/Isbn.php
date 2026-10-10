<?php

namespace App\Domain;

/** Objeto de valor: ISBN de exactamente 13 dígitos (CA-03.2). */
final class Isbn
{
    private function __construct(public readonly string $value) {}

    public static function of(string $raw): self
    {
        $value = trim($raw);
        if (! preg_match('/^\d{13}$/', $value)) {
            throw DomainError::validation('El ISBN debe tener exactamente 13 dígitos.', 'isbn', 'debe tener 13 dígitos');
        }

        return new self($value);
    }
}

