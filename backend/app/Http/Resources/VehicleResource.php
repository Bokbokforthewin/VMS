<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate_no' => $this->plate_no,
            'brand' => $this->brand,
            'model' => $this->model,
            'seats' => $this->seats,
            'status' => $this->status,
            'deleted_at' => $this->deleted_at,
        ];
    }
}