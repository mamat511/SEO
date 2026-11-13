<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeywordMetric extends Model
{
    public $timestamps = false;

    protected $fillable = ['keyword_id', 'date', 'position', 'clicks', 'impressions', 'top_page'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
