<?php

namespace Maestrodimateo\Search\Traits;

use Maestrodimateo\Search\Contracts\Searchable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Trait allowing simple and full text search on Eloquent models.
 *
 * The model must implement {@see Searchable} to use scopeFullTextSearch().
 */
trait WithSearch
{
    /**
     * Returns dialect-specific configuration for the current database driver.
     *
     * @return array{ operator: string, jsonColumn: callable(string): string }
     */
    private function dialect(): array
    {
        return match (config('database.default')) {
            'pgsql' => [
                'operator' => 'ilike',
                'jsonColumn' => fn (string $column) => $this->toPgsqlJsonColumn($column),
            ],
            default => [
                'operator' => 'like',
                'jsonColumn' => fn (string $column) => $this->toMysqlJsonColumn($column),
            ],
        };
    }

    /**
     * Converts a JSON path to PostgreSQL text extraction syntax.
     *
     * e.g. "apprenant->nom"           → "apprenant->>'nom'"
     * e.g. "apprenant->adresse->ville" → "apprenant->'adresse'->>'ville'"
     */
    private function toPgsqlJsonColumn(string $column): string
    {
        $parts = explode('->', $column);
        $field = array_shift($parts);
        $last = array_pop($parts);

        foreach ($parts as $part) {
            $field .= "->'$part'";
        }

        return "$field->>'$last'";
    }

    /**
     * Converts a JSON path to MySQL text extraction syntax.
     *
     * e.g. "apprenant->nom"            → "apprenant->>'$.nom'"
     * e.g. "apprenant->adresse->ville" → "apprenant->>'$.adresse.ville'"
     */
    private function toMysqlJsonColumn(string $column): string
    {
        $parts = explode('->', $column);
        $field = array_shift($parts);
        $path = implode('.', $parts);

        return "$field->>'$.$path'";
    }

    /**
     * Resolves a column to its SQL equivalent, handling JSON paths if needed.
     */
    private function toSqlColumn(string $column): string
    {
        if (! str_contains($column, '->')) {
            return $column;
        }

        return ($this->dialect()['jsonColumn'])($column);
    }

    /**
     * Simple single-column search.
     *
     * @example Model::search('nom', 'dupont')->get()
     */
    public function scopeSearch(Builder $query, string $attribute, ?string $search = null): Builder
    {
        return $query->where($attribute, $this->dialect()['operator'], "%$search%");
    }

    /**
     * Full text search across all columns defined in {@see Searchable::getFullTextColumns()}.
     *
     * @example Model::fullTextSearch('dupont')->get()
     *
     * @throws LogicException
     */
    public function scopeFullTextSearch(Builder $query, ?string $search = null): Builder
    {
        if (! $this instanceof Searchable) {
            throw new LogicException(self::class.' must implement '.Searchable::class.' to use fullTextSearch.');
        }

        $dialect = $this->dialect();

        $search = str($search)->replace(' ', '%')->toString();

        $columns = implode(', ', array_map(
            fn (string $column) => $this->toSqlColumn($column),
            $this->getFullTextColumns()
        ));

        return $query->whereRaw("CONCAT_WS('-', $columns) {$dialect['operator']} ?", ["%$search%"]);
    }
}