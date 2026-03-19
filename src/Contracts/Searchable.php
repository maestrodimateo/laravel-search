<?php

namespace Maestrodimateo\Search\Contracts;

interface Searchable
{
    /**
     * Columns used for full text search.
     * Supports JSON paths using arrow notation (e.g. "apprenant->nom").
     *
     * @return array<int, string>
     */
    public function getFullTextColumns(): array;
}