<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'rooms' => $this->rooms,
            'rooms_label' => $this->rooms === 0 ? 'Студия' : $this->rooms.'-комн.',
            'floor' => $this->floor,
            'area' => $this->area,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'preview_url' => $this->preview_url,
            'has_photo' => $this->image !== null,
        ];
    }
}
