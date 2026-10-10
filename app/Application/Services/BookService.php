<?php

namespace App\Application\Services;

use App\Application\Page;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\IdGenerator;
use App\Domain\Book;
use App\Domain\DomainError;
use App\Domain\Isbn;
use App\Domain\Quantity;

/**
 * HU-03 & HU-04: Gestión y búsqueda de libros.
 */
final class BookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
        private readonly CoverStorage $covers,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    public function create(string $title, string $author, string $rawIsbn, string $categoryId, int $totalCopies): array
    {
        $category = $this->categories->find($categoryId);
        if ($category === null) {
            throw DomainError::validation('La categoría no existe.', 'category_id', 'categoría no encontrada');
        }

        $isbn = Isbn::of($rawIsbn);
        $total = Quantity::of($totalCopies, 'total_copies');
        $id = $this->ids->next();

        $book = Book::create($id, $title, $author, $isbn, $category['id'], $category['name'], $total);
        $this->books->add($book);

        return $this->present($book);
    }

    public function replace(string $id, string $title, string $author, string $categoryId, int $totalCopies): array
    {
        $book = $this->books->getActive($id);
        if ($book === null) {
            throw DomainError::notFound('El libro no existe o fue dado de baja.');
        }

        $category = $this->categories->find($categoryId);
        if ($category === null) {
            throw DomainError::validation('La categoría no existe.', 'category_id', 'categoría no encontrada');
        }

        $total = Quantity::of($totalCopies, 'total_copies');
        $book->replace($title, $author, $category['id'], $category['name'], $total);

        $this->books->replace($book);

        return $this->present($book);
    }

    public function markDeleted(string $id): void
    {
        $book = $this->books->getActive($id);
        if ($book === null) {
            throw DomainError::notFound('El libro no existe o ya fue dado de baja.');
        }

        if ($this->books->hasOpenLoans($id)) {
            throw new DomainError(
                DomainError::BOOK_HAS_OPEN_LOANS,
                'No se puede dar de baja un libro con préstamos abiertos.',
            );
        }

        $book->markDeleted($this->clock->now());
        $this->books->markDeleted($book);
    }

    public function getActive(string $id): array
    {
        $book = $this->books->getActive($id);
        if ($book === null) {
            throw DomainError::notFound('El libro no existe o fue dado de baja.');
        }

        return $this->present($book);
    }

    public function search(?string $q, ?string $categoryId, int $page, int $size): Page
    {
        $page = max(1, $page);
        $size = min(50, max(1, $size));
        $res = $this->books->search($q, $categoryId, $page, $size);

        $mapped = array_map(fn (Book $b) => $this->present($b), $res->items);

        return new Page($mapped, $res->page, $res->size, $res->total);
    }

    public function uploadCover(string $bookId, string $bytes, string $mimeType): void
    {
        $book = $this->books->getActive($bookId);
        if ($book === null) {
            throw DomainError::notFound('El libro no existe.');
        }

        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            throw DomainError::validation('Solo se permiten imágenes JPEG y PNG.', 'file', 'formato no permitido');
        }

        if (strlen($bytes) > 2 * 1024 * 1024) {
            throw DomainError::validation('La portada no puede superar los 2 MB.', 'file', 'archivo > 2MB');
        }

        $coverKey = $this->ids->next();
        $this->covers->put($coverKey, $bytes);

        $oldKey = $book->coverKey();
        $this->books->setCover($bookId, $coverKey);

        if ($oldKey !== null) {
            $this->covers->delete($oldKey);
        }
    }

    public function removeCover(string $bookId): void
    {
        $book = $this->books->getActive($bookId);
        if ($book === null) {
            throw DomainError::notFound('El libro no existe.');
        }

        $oldKey = $book->coverKey();
        $this->books->setCover($bookId, null);

        if ($oldKey !== null) {
            $this->covers->delete($oldKey);
        }
    }

    public function getCoverBytes(string $bookId): array
    {
        $book = $this->books->getActive($bookId);
        if ($book === null || $book->coverKey() === null) {
            throw DomainError::notFound('El libro no tiene portada.');
        }

        $bytes = $this->covers->get($book->coverKey());
        if ($bytes === null) {
            throw DomainError::notFound('El archivo de portada no se encuentra.');
        }

        return ['bytes' => $bytes];
    }

    private function present(Book $b): array
    {
        return [
            'id'               => $b->id,
            'title'            => $b->title(),
            'author'           => $b->author(),
            'isbn'             => $b->isbn->value,
            'category'         => [
                'id'   => $b->categoryId(),
                'name' => $b->categoryName(),
            ],
            'total_copies'     => $b->totalCopies(),
            'available_copies' => $b->availableCopies(),
            'cover_key'        => $b->coverKey(),
        ];
    }
}

