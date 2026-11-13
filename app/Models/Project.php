<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    protected $fillable = ['user_id', 'name', 'url', 'gsc_property', 'gsc_synced_at'];

    protected function casts(): array
    {
        return ['gsc_synced_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(DailyMetric::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function backlinkImports(): HasMany
    {
        return $this->hasMany(BacklinkImport::class);
    }

    public function crawls(): HasMany
    {
        return $this->hasMany(Crawl::class);
    }

    public function latestCrawl(): HasOne
    {
        return $this->hasOne(Crawl::class)->where('status', 'done')->latestOfMany('finished_at');
    }

    public function host(): string
    {
        return (string) parse_url($this->url, PHP_URL_HOST);
    }

    /**
     * Totaux Search Console sur les $days derniers jours disponibles,
     * comparés à la période précédente de même durée.
     *
     * @return array{clicks:int, impressions:int, ctr:float, position:?float, prev:array}
     */
    public function summary(int $days = 28): array
    {
        $last = $this->dailyMetrics()->max('date');

        if (! $last) {
            return ['clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => null, 'prev' => null];
        }

        $end = \Carbon\Carbon::parse($last);
        $current = $this->aggregate($end->copy()->subDays($days - 1), $end);
        $current['prev'] = $this->aggregate($end->copy()->subDays(2 * $days - 1), $end->copy()->subDays($days));

        return $current;
    }

    private function aggregate($from, $to): array
    {
        $row = $this->dailyMetrics()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(clicks) as clicks, SUM(impressions) as impressions, SUM(position * impressions) as wpos')
            ->first();

        $impressions = (int) $row->impressions;

        return [
            'clicks' => (int) $row->clicks,
            'impressions' => $impressions,
            'ctr' => $impressions ? $row->clicks / $impressions : 0.0,
            'position' => $impressions ? $row->wpos / $impressions : null,
        ];
    }
}
