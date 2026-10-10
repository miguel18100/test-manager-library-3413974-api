<?php

namespace App\Domain;

/** Objeto de valor: cantidad entera >= 1 (CA-03.3). */
final class Quantity
{
    private function __construct(public readonly int $value) {}

    public static function of(int $value, string $field = 'total_copies'): self
    {
        if ($value < 1) {
            throw DomainError::validation('La cantidad debe ser mayor o igual a 1.', $field, 'debe ser >= 1');
        }

        return new self($value);
    }
}

