<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $fillable = ['project_id', 'query'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(KeywordMetric::class);
    }
}
