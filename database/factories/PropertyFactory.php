<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public static array $imagePool = [];

    public function definition(): array
    {
        $rooms = $this->faker->randomElement([0, 1, 1, 1, 2, 2, 2, 3, 3, 4, 5]);
        $picked = self::$imagePool !== [] && $this->faker->boolean(70)
            ? $this->faker->randomElement(self::$imagePool)
            : [
                'image' => null,
                'preview' => null
            ];

        return [
            'title' => $this->makeTitle($rooms),
            'image' => $picked['image'] ?? null,
            'preview' => $picked['preview'] ?? null,
            'rooms' => $rooms,
            'floor' => $this->faker->numberBetween(1, 25),
            'area' => $this->faker->randomFloat(2, 12, 250),
            'description' => $this->faker->realText(300),
        ];
    }

    private function makeTitle(int $rooms): string
    {
        $type = match (true) {
            $rooms === 0 => 'Студия',
            $rooms >= 5 => 'Многокомнатная квартира',
            default => "{$rooms}-комнатная квартира",
        };

        $adjective = $this->faker->randomElement([
            'Уютная', 'Просторная', 'Светлая', 'Современная',
            'Стильная', 'Тёплая', 'Видовая', 'Семейная',
        ]);

        return match ($this->faker->numberBetween(1, 3)) {
            1 => "{$adjective} {$type}",
            2 => "{$type}",
            default => $rooms === 0
                ? "Студия"
                : "Квартира {$rooms}-комн.",
        };
    }
}
