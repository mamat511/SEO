<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlPage extends Model
{
    public $timestamps = false;

    protected $fillable = ['crawl_id', 'url', 'status_code', 'redirect_to', 'title', 'meta_description', 'h1_count', 'canonical', 'noindex', 'response_ms', 'word_count', 'depth', 'issues'];

    protected function casts(): array
    {
        return ['issues' => 'array', 'noindex' => 'boolean'];
    }
}
