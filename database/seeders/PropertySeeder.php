<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Property;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $total = (int) 10000;

        $dir = public_path('images');
        $sourceImages = File::isDirectory($dir)
            ? array_map(fn($f) => $f->getRealPath(), File::files($dir))
            : [];

        $sourceImages = array_filter($sourceImages, fn($p) => in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true));

        if (empty($sourceImages)) {
            $this->command->error('Исходные изображения в public/images не найдены!');
            return;
        }

        Storage::disk('public')->deleteDirectory('properties');
        Storage::disk('public')->makeDirectory('properties');

        Schema::disableForeignKeyConstraints();
        Property::query()->truncate();
        Schema::enableForeignKeyConstraints();

        $this->command->info("Генерация {$total}");

        $now = now();
        $bar = $this->command->getOutput()->createProgressBar($total);
        $bar->start();

        $chunkSize = 500;

        for ($i = 0; $i < $total; $i += $chunkSize) {
            $currentChunkSize = min($chunkSize, $total - $i);

            PropertyFactory::$imagePool = [];
            $rawData = Property::factory()->count($currentChunkSize)->raw();

            $rows = [];
            foreach ($rawData as $attributes) {
                $attributes['title_normalized'] = Str::lower($attributes['title']);

                if (fake()->boolean(70)) {
                    $sourcePath = fake()->randomElement($sourceImages);
                    $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
                    $newFileName = 'properties/' . \Illuminate\Support\Str::random(40) . '.' . $extension;

                    File::copy($sourcePath, Storage::disk('public')->path($newFileName));

                    $attributes['image'] = $newFileName;
                    $attributes['preview'] = $newFileName;
                } else {
                    $attributes['image'] = null;
                    $attributes['preview'] = null;
                }

                $rows[] = [
                    ...$attributes,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('properties')->insert($rows);
            $bar->advance($currentChunkSize);
        }

        $bar->finish();
        $this->command->newLine(2);
        $this->command->info("Готово: {$total} объектов.");
    }
}
