<?php

namespace App\Application;

final class Page
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $size,
        public readonly int $total,
    ) {}

    /**
     * @return array{items:list<mixed>, page:int, size:int, total:int}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'page'  => $this->page,
            'size'  => $this->size,
            'total' => $this->total,
        ];
    }
}

