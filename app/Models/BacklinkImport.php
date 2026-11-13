<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BacklinkImport extends Model
{
    protected $fillable = ['project_id', 'kind', 'filename', 'rows_count'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function backlinks(): HasMany
    {
        return $this->hasMany(Backlink::class);
    }

    /** Import précédent du même type, pour calculer les liens gagnés / perdus. */
    public function previous(): ?self
    {
        return self::where('project_id', $this->project_id)
            ->where('kind', $this->kind)
            ->where('id', '<', $this->id)
            ->latest('id')
            ->first();
    }
}
