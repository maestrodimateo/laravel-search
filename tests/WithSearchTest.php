<?php

use Maestrodimateo\Search\Contracts\Searchable;
use Maestrodimateo\Search\Tests\Models\Article;
use Maestrodimateo\Search\Tests\Models\ArticleWithoutSearchable;

// ─── scopeSearch ──────────────────────────────────────────────────────────────

describe('scopeSearch', function () {

    it('generates a LIKE query on mysql', function () {
        config(['database.default' => 'sqlite']);

        $sql = Article::search('titre', 'laravel')->toSql();

        expect($sql)->toContain('like')
            ->and($sql)->toContain('titre');
    });

    it('generates an ILIKE query on pgsql', function () {
        config(['database.default' => 'pgsql']);

        $sql = Article::search('titre', 'laravel')->toSql();

        expect($sql)->toContain('ilike')
            ->and($sql)->toContain('titre');
    });

    it('wraps the search term with % wildcards', function () {
        config(['database.default' => 'sqlite']);

        $bindings = Article::search('titre', 'laravel')->getBindings();

        expect($bindings)->toBe(['%laravel%']);
    });

    it('accepts a null search term', function () {
        config(['database.default' => 'sqlite']);

        $sql = Article::search('titre', null)->toSql();

        expect($sql)->toContain('titre');
    });
});

// ─── scopeFullTextSearch ──────────────────────────────────────────────────────

describe('scopeFullTextSearch', function () {

    it('generates a CONCAT_WS query with LIKE on sqlite/mysql', function () {
        config(['database.default' => 'sqlite']);

        $sql = Article::fullTextSearch('laravel')->toSql();

        expect($sql)->toContain('CONCAT_WS')
            ->and($sql)->toContain('like');
    });

    it('generates a CONCAT_WS query with ILIKE on pgsql', function () {
        config(['database.default' => 'pgsql']);

        $sql = Article::fullTextSearch('laravel')->toSql();

        expect($sql)->toContain('CONCAT_WS')
            ->and($sql)->toContain('ilike');
    });

    it('wraps the search term with % wildcards', function () {
        config(['database.default' => 'sqlite']);

        $bindings = Article::fullTextSearch('laravel')->getBindings();

        expect($bindings)->toBe(['%laravel%']);
    });

    it('replaces spaces with % in the search term', function () {
        config(['database.default' => 'sqlite']);

        $bindings = Article::fullTextSearch('laravel framework')->getBindings();

        expect($bindings)->toBe(['%laravel%framework%']);
    });

    it('throws a LogicException when Searchable is not implemented', function () {
        config(['database.default' => 'sqlite']);

        ArticleWithoutSearchable::fullTextSearch('laravel')->toSql();
    })->throws(LogicException::class);

    it('includes the model class name in the LogicException message', function () {
        config(['database.default' => 'sqlite']);

        expect(fn () => ArticleWithoutSearchable::fullTextSearch('test')->toSql())
            ->toThrow(LogicException::class, ArticleWithoutSearchable::class);
    });
});

// ─── JSON path conversion ─────────────────────────────────────────────────────

describe('JSON path conversion', function () {

    it('converts a simple JSON path to PostgreSQL syntax', function () {
        config(['database.default' => 'pgsql']);

        $sql = Article::fullTextSearch('paris')->toSql();

        expect($sql)->toContain("meta->>'ville'");
    });

    it('converts a simple JSON path to MySQL syntax', function () {
        config(['database.default' => 'sqlite']);

        $sql = Article::fullTextSearch('paris')->toSql();

        expect($sql)->toContain("meta->>'$.ville'");
    });

    it('converts a nested JSON path to PostgreSQL syntax', function () {
        config(['database.default' => 'pgsql']);

        $model = new class extends \Illuminate\Database\Eloquent\Model implements Searchable {
            use \Maestrodimateo\Search\Traits\WithSearch;

            protected $table = 'articles';

            public function getFullTextColumns(): array
            {
                return ['meta->adresse->ville'];
            }
        };

        $sql = $model->newQuery()->fullTextSearch('paris')->toSql();

        expect($sql)->toContain("meta->'adresse'->>'ville'");
    });

    it('converts a nested JSON path to MySQL syntax', function () {
        config(['database.default' => 'sqlite']);

        $model = new class extends \Illuminate\Database\Eloquent\Model implements Searchable {
            use \Maestrodimateo\Search\Traits\WithSearch;

            protected $table = 'articles';

            public function getFullTextColumns(): array
            {
                return ['meta->adresse->ville'];
            }
        };

        $sql = $model->newQuery()->fullTextSearch('paris')->toSql();

        expect($sql)->toContain("meta->>'$.adresse.ville'");
    });

    it('leaves plain columns unchanged', function () {
        config(['database.default' => 'pgsql']);

        $sql = Article::fullTextSearch('dupont')->toSql();

        expect($sql)->toContain('titre')
            ->and($sql)->toContain('auteur');
    });
});

// ─── Searchable contract ──────────────────────────────────────────────────────

describe('Searchable contract', function () {

    it('Article implements Searchable', function () {
        expect(new Article)->toBeInstanceOf(Searchable::class);
    });

    it('getFullTextColumns returns the declared columns', function () {
        expect((new Article)->getFullTextColumns())
            ->toBe(['titre', 'auteur', 'meta->ville']);
    });
});
