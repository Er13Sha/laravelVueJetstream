<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 *
 * @property int $id
 * @property string $title
 * @property string $title_normalized
 * @property string|null $image
 * @property string|null $preview
 * @property int $rooms
 * @property int $floor
 * @property float $area
 * @property string $description
 */
class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'image',
        'preview',
        'rooms',
        'floor',
        'area',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'rooms' => 'integer',
            'floor' => 'integer',
            'area' => 'float',
        ];
    }

    protected function title(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): array => [
                'title' => $value,
                'title_normalized' => Str::lower($value),
            ],
        );
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->image ? Storage::disk('public')->url($this->image) : null,
        );
    }

    protected function previewUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->preview ? Storage::disk('public')->url($this->preview) : null,
        );
    }

    public function scopeSearchTitle(Builder $query, ?string $term): Builder
    {
        $needle = Str::lower(trim((string) $term));

        if ($needle === '') {
            return $query;
        }

        return $query->where(
            'title_normalized',
            'like',
            '%'.$this->escapeLike($needle).'%'
        );
    }

    public function scopeWithPhoto(Builder $query, bool $only = true): Builder
    {
        return $only ? $query->whereNotNull('image') : $query;
    }

    /**
     *
     * @param  array<int, int>  $rooms
     */
    public function scopeRooms(Builder $query, array $rooms): Builder
    {
        $rooms = array_values(array_filter(
            $rooms,
            static fn ($value): bool => $value !== null && $value !== '',
        ));

        return $rooms === [] ? $query : $query->whereIn('rooms', $rooms);
    }

    public function scopeAreaBetween(Builder $query, ?float $min, ?float $max): Builder
    {
        return $query
            ->when($min !== null, fn (Builder $q): Builder => $q->where('area', '>=', $min))
            ->when($max !== null, fn (Builder $q): Builder => $q->where('area', '<=', $max));
    }

    public function scopeSorted(
        Builder $query,
        string $sort,
        string $direction,
        ?string $term = null,
    ): Builder {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return match ($sort) {
            'area' => $query->orderBy('area', $direction),
            'rooms' => $query->orderBy('rooms', $direction),
            'floor' => $query->orderBy('floor', $direction),
            'newest' => $query->orderByDesc('created_at'),
            default => $this->applyRelevanceOrder($query, $term),
        };
    }

    private function applyRelevanceOrder(Builder $query, ?string $term): Builder
    {
        $needle = Str::lower(trim((string) $term));

        if ($needle === '') {
            // Без поискового запроса релевантность бессмысленна — показываем свежие.
            return $query->orderByDesc('id');
        }

        $prefix = $this->escapeLike($needle).'%';

        return $query
            ->orderByRaw(
                'CASE'
                .' WHEN title_normalized = ? THEN 0'   // точное совпадение
                .' WHEN title_normalized LIKE ? THEN 1' // начинается с запроса
                .' ELSE 2'                              // содержит запрос
                .' END',
                [$needle, $prefix],
            )
            ->orderBy('title_normalized');
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );
    }
}
