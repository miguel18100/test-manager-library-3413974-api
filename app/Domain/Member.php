<?php

namespace App\Domain;

/** Agregado Member: nombre y documento obligatorios; documento único (lo garantiza el motor). */
final class Member
{
    private function __construct(
        public readonly string $id,
        public readonly string $fullName,
        public readonly string $documentNumber,
    ) {}

    public static function create(string $id, string $fullName, string $documentNumber): self
    {
        $name = trim($fullName);
        $doc = trim($documentNumber);
        if ($name === '' || mb_strlen($name) > 200) {
            throw DomainError::validation('El nombre es obligatorio (máximo 200 caracteres).', 'full_name', 'obligatorio, máximo 200');
        }
        if ($doc === '' || mb_strlen($doc) > 30) {
            throw DomainError::validation('El documento es obligatorio (máximo 30 caracteres).', 'document_number', 'obligatorio, máximo 30');
        }

        return new self($id, $name, $doc);
    }

    public static function restore(string $id, string $fullName, string $documentNumber): self
    {
        return new self($id, $fullName, $documentNumber);
    }
}

