<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertySearchRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(PropertySearchRequest $request): Response
    {
        return Inertia::render('Properties/Index', [
            'initialFilters' => $request->filters(),
            'meta' => $this->meta(),
        ]);
    }

    public function search(PropertySearchRequest $request): AnonymousResourceCollection
    {
        $filters = $request->filters();

        [$min, $max] = $this->orderedRange($filters['area_min'], $filters['area_max']);

        $properties = Property::query()
            ->searchTitle($filters['title'])
            ->withPhoto($filters['has_photo'])
            ->rooms($filters['rooms'])
            ->areaBetween($min, $max)
            ->sorted($filters['sort'], $filters['direction'], $filters['title'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        return PropertyResource::collection($properties);
    }

    private function meta(): array
    {
        return [
            'area_bounds' => [
                'min' => (float) floor((float) (Property::query()->min('area') ?? 0)),
                'max' => (float) ceil((float) (Property::query()->max('area') ?? 300)),
            ],
            'room_options' => [
                ['value' => 0, 'label' => 'Студия'],
                ['value' => 1, 'label' => '1'],
                ['value' => 2, 'label' => '2'],
                ['value' => 3, 'label' => '3'],
                ['value' => 4, 'label' => '4'],
                ['value' => 5, 'label' => '5+'],
            ],
            'sort_options' => [
                ['value' => 'relevance', 'label' => 'По релевантности'],
                ['value' => 'newest', 'label' => 'Сначала новые'],
                ['value' => 'area', 'label' => 'По площади'],
                ['value' => 'rooms', 'label' => 'По комнатам'],
                ['value' => 'floor', 'label' => 'По этажу'],
            ],
            'default_per_page' => 25,
        ];
    }

    private function orderedRange(?float $min, ?float $max): array
    {
        if ($min !== null && $max !== null && $min > $max) {
            return [$max, $min];
        }

        return [$min, $max];
    }
}
