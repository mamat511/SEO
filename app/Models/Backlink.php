<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backlink extends Model
{
    public $timestamps = false;

    protected $fillable = ['backlink_import_id', 'source', 'domain', 'linking_pages', 'target_pages', 'last_crawled'];

    protected function casts(): array
    {
        return ['last_crawled' => 'date'];
    }
}
