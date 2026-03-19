<?php

namespace Maestrodimateo\Search\Tests\Models;

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
