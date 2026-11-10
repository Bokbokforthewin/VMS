<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'designation' => $this->designation,
            'unit' => $this->unit,
            'division' => $this->division,
            'driver' => DriverResource::make($this->whenLoaded('driver')),
            'deleted_at' => $this->deleted_at,
        ];
    }
}