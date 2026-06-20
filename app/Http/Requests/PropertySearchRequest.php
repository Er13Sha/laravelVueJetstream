<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PropertySearchRequest extends FormRequest
{
    private const ALLOWED_SORTS = ['relevance', 'newest', 'area', 'rooms', 'floor'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string'],
            'has_photo' => ['nullable'],
            'rooms' => ['nullable', 'array'],
            'area_min' => ['nullable', 'numeric'],
            'area_max' => ['nullable', 'numeric'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): array
    {
        $perPage = (int) $this->input('per_page', 25);
        $sort = (string) $this->input('sort', 'relevance');

        $rooms = array_values(array_unique(array_filter(
            array_map('intval', (array) $this->input('rooms', [])),
            static fn (int $value): bool => $value >= 0 && $value <= 5,
        )));

        return [
            'title' => mb_substr(trim((string) $this->input('title', '')), 0, 255) ?: null,
            'has_photo' => $this->boolean('has_photo'),
            'rooms' => $rooms,
            'area_min' => $this->filled('area_min') ? max(0.0, (float) $this->input('area_min')) : null,
            'area_max' => $this->filled('area_max') ? max(0.0, (float) $this->input('area_max')) : null,
            'sort' => in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'relevance',
            'direction' => strtolower((string) $this->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
            'per_page' => $perPage,
        ];
    }
}
