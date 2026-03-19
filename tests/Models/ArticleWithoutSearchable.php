<?php

namespace Maestrodimateo\Search\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Search\Traits\WithSearch;

/** Model that uses WithSearch but does NOT implement Searchable — used to test the LogicException. */
class ArticleWithoutSearchable extends Model
{
    use WithSearch;

    protected $table = 'articles';
}
