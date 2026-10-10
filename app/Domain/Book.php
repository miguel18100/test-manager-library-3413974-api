<?php

namespace App\Domain;

use DateTimeImmutable;

/**
 * Agregado Book (architecture.md §3).
 * Invariantes: 0 <= disponibles <= total (RN-1); total >= 1; título y autor no vacíos.
 */
final class Book
{
    private function __construct(
        public readonly string $id,
        private string $title,
        private string $author,
        public readonly Isbn $isbn,
        private string $categoryId,
        private string $categoryName,
        private int $totalCopies,
        private int $availableCopies,
        private ?string $coverKey,
        private ?DateTimeImmutable $deletedAt,
    ) {}

    /** CA-03.1: los disponibles arrancan iguales al total. */
    public static function create(
        string $id, string $title, string $author, Isbn $isbn,
        string $categoryId, string $categoryName, Quantity $total,
    ): self {
        return new self(
            $id, self::text($title, 250, 'title', 'título'), self::text($author, 200, 'author', 'autor'),
            $isbn, $categoryId, $categoryName, $total->value, $total->value, null, null,
        );
    }

    /** Reconstrucción desde persistencia (sin revalidar reglas de entrada). */
    public static function restore(
        string $id, string $title, string $author, string $isbn, string $categoryId, string $categoryName,
        int $total, int $available, ?string $coverKey, ?DateTimeImmutable $deletedAt,
    ): self {
        return new self($id, $title, $author, Isbn::of($isbn), $categoryId, $categoryName, $total, $available, $coverKey, $deletedAt);
    }

    /** Descuenta un ejemplar (CA-06.3). Nunca deja disponibles < 0. */
    public function withdraw(): void
    {
        if ($this->availableCopies < 1) {
            throw new DomainError(
                DomainError::BOOK_NOT_AVAILABLE,
                "No quedan ejemplares disponibles de «{$this->title}».",
                [['field' => 'book_id', 'issue' => 'sin ejemplares']],
            );
        }
        $this->availableCopies--;
    }

    /** Repone un ejemplar (CA-07.1). Nunca supera el total (CA-07.3). */
    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new \LogicException('Defecto: reponer superaría el total de ejemplares.');
        }
        $this->availableCopies++;
    }

    /** PUT /books/{id}: reemplaza título, autor, categoría y total (CA-03.3). */
    public function replace(string $title, string $author, string $categoryId, string $categoryName, Quantity $total): void
    {
        $newAvailable = $this->availableCopies + ($total->value - $this->totalCopies);
        if ($newAvailable < 0) {
            throw DomainError::validation(
                'El nuevo total dejaría disponibles negativos: hay ejemplares prestados.',
                'total_copies', 'menor que los ejemplares prestados',
            );
        }
        $this->title = self::text($title, 250, 'title', 'título');
        $this->author = self::text($author, 200, 'author', 'autor');
        $this->categoryId = $categoryId;
        $this->categoryName = $categoryName;
        $this->totalCopies = $total->value;
        $this->availableCopies = $newAvailable;
    }

    /** Baja lógica (ADR-003). */
    public function markDeleted(DateTimeImmutable $now): void
    {
        $this->deletedAt = $now;
    }

    public function setCoverKey(?string $key): void
    {
        if ($key === '') {
            throw new \LogicException('cover_key nunca es cadena vacía (CA-03.4).');
        }
        $this->coverKey = $key;
    }

    public function title(): string { return $this->title; }
    public function author(): string { return $this->author; }
    public function categoryId(): string { return $this->categoryId; }
    public function categoryName(): string { return $this->categoryName; }
    public function totalCopies(): int { return $this->totalCopies; }
    public function availableCopies(): int { return $this->availableCopies; }
    public function coverKey(): ?string { return $this->coverKey; }
    public function deletedAt(): ?DateTimeImmutable { return $this->deletedAt; }
    public function isActive(): bool { return $this->deletedAt === null; }

    private static function text(string $value, int $max, string $field, string $label): string
    {
        $v = trim($value);
        if ($v === '') {
            throw DomainError::validation("El {$label} es obligatorio.", $field, 'obligatorio');
        }
        if (mb_strlen($v) > $max) {
            throw DomainError::validation("El {$label} admite máximo {$max} caracteres.", $field, "máximo {$max}");
        }

        return $v;
    }
}

