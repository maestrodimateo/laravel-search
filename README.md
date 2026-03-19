# Laravel Search

A Laravel package for simple and multi-column search on Eloquent models, with native support for JSON fields and PostgreSQL / MySQL dialects.

---

## When to use this package

This package is a good fit when:

- **You store structured data as JSON columns** — e.g. an `apprenant` column holding `{ "nom": "Dupont", "prenom": "Jean" }`. Native Eloquent search does not handle JSON path extraction out of the box, and writing raw `->>'key'` syntax every time is error-prone.
- **You need to search across multiple columns at once** — instead of chaining multiple `orWhere` calls, you declare your columns once in `getFullTextColumns()` and call a single scope.
- **Your application targets both PostgreSQL and MySQL** — the package transparently switches between `ILIKE` / `->>` (PostgreSQL) and `LIKE` / `->>'$.path'` (MySQL) with no change to your model code.
- **You want a lightweight solution with no external dependencies** — no Elasticsearch, no Meilisearch, no extra infrastructure. Everything runs on your existing database.

This package is **not** the right choice when:

- You need **relevance ranking** or full-text scoring (use PostgreSQL `tsvector` or a dedicated search engine instead).
- Your tables have **millions of rows** and search performance is critical (consider adding a `pg_trgm` GIN index or delegating to a search engine).
- You need **fuzzy matching** or typo tolerance (use Meilisearch or Algolia).

---

## Requirements

- PHP **8.3** or higher
- Laravel **12.x**
- PostgreSQL or MySQL (SQLite supported for testing)

---

## Installation

```bash
composer require maestrodimateo/laravel-search
```

The service provider is automatically discovered by Laravel.

---

## Setup

### 1. Implement the `Searchable` contract

Add the `WithSearch` trait to your model and implement the `Searchable` interface to declare which columns are included in multi-column search.

```php
use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Search\Contracts\Searchable;
use Maestrodimateo\Search\Traits\WithSearch;

class Article extends Model implements Searchable
{
    use WithSearch;

    protected $fillable = ['titre', 'auteur', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function getFullTextColumns(): array
    {
        return ['titre', 'auteur', 'meta->ville'];
    }
}
```

> `Searchable` is only required for `fullTextSearch()`. The `search()` method works without it.

---

## Usage

### Single-column search — `search()`

Performs a `LIKE` / `ILIKE` on **one column**.

```php
// Search on a regular column
Article::search('titre', 'laravel')->get();

// Search on a JSON field
Article::search('meta->ville', 'paris')->get();
```

SQL generated (PostgreSQL):
```sql
SELECT * FROM articles WHERE titre ILIKE '%laravel%'
SELECT * FROM articles WHERE meta->>'ville' ILIKE '%paris%'
```

SQL generated (MySQL):
```sql
SELECT * FROM articles WHERE titre LIKE '%laravel%'
SELECT * FROM articles WHERE meta->>'$.ville' LIKE '%paris%'
```

---

### Multi-column search — `fullTextSearch()`

Performs a `LIKE` / `ILIKE` across **all columns** declared in `getFullTextColumns()`.

```php
Article::fullTextSearch('dupont')->get();
```

SQL generated (PostgreSQL):
```sql
SELECT * FROM articles
WHERE CONCAT_WS('-', titre, auteur, meta->>'ville') ILIKE '%dupont%'
```

SQL generated (MySQL):
```sql
SELECT * FROM articles
WHERE CONCAT_WS('-', titre, auteur, meta->>'$.ville') LIKE '%dupont%'
```

Spaces in the search term are automatically converted to `%` for flexible matching:

```php
Article::fullTextSearch('jean paris')->get();
// binding: %jean%paris%
```

---

### Chaining with other scopes

Both methods return a `Builder` and can be combined with any Eloquent scope:

```php
Article::fullTextSearch('dupont')
    ->where('meta->ville', 'Paris')
    ->orderBy('created_at', 'desc')
    ->paginate(15);
```

---

## JSON columns

The package automatically converts JSON paths to the correct SQL syntax for the current database driver.

### Declaration syntax

Use `->` notation in `getFullTextColumns()` or in `search()`:

```php
public function getFullTextColumns(): array
{
    return [
        'titre',          // regular column
        'auteur',         // regular column
        'meta->ville',    // JSON field
    ];
}
```

### Conversion per dialect

| Declaration       | PostgreSQL              | MySQL                    |
|-------------------|-------------------------|--------------------------|
| `titre`           | `titre`                 | `titre`                  |
| `meta->ville`     | `meta->>'ville'`        | `meta->>'$.ville'`       |
| `meta->addr->city`| `meta->'addr'->>'city'` | `meta->>'$.addr.city'`   |

---

## Supported dialects

The package automatically detects the driver configured in `config/database.php`.

| Driver           | Operator | JSON extraction         |
|------------------|----------|-------------------------|
| `pgsql`          | `ILIKE`  | `->>` / `->'...'->>`    |
| `mysql` / others | `LIKE`   | `->>'$.path'`           |

---

## `Searchable` contract

```php
namespace Maestrodimateo\Search\Contracts;

interface Searchable
{
    /**
     * Columns used for multi-column full text search.
     * Supports JSON path notation with -> (e.g. "meta->ville").
     *
     * @return array<int, string>
     */
    public function getFullTextColumns(): array;
}
```

If `fullTextSearch()` is called on a model that does not implement `Searchable`, a `LogicException` is thrown:

```
Maestrodimateo\Search\Tests\Models\ArticleWithoutSearchable must implement
Maestrodimateo\Search\Contracts\Searchable to use fullTextSearch.
```

---

## Testing

The package tests use [Pest](https://pestphp.com) and [Orchestra Testbench](https://github.com/orchestral/testbench) with an in-memory SQLite database.

```bash
cd packages/maestrodimateo/laravel-search
./vendor/bin/pest --compact
```

---

## Method reference

### `search(string $attribute, ?string $search): Builder`

| Parameter    | Type      | Description                              |
|--------------|-----------|------------------------------------------|
| `$attribute` | `string`  | Column or JSON path (`column->key`)      |
| `$search`    | `?string` | Search term                              |

---

### `fullTextSearch(?string $search): Builder`

| Parameter | Type      | Description                                                          |
|-----------|-----------|----------------------------------------------------------------------|
| `$search` | `?string` | Search term — spaces are automatically converted to `%`              |

> Requires the model to implement `Searchable`.
