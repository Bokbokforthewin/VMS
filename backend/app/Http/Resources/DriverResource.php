<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'license_no' => $this->license_no,
            'contact_no' => $this->contact_no,
            'user' => UserResource::make($this->whenLoaded('user')),
            'deleted_at' => $this->deleted_at,
        ];
    }
}