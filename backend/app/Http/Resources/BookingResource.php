<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purpose' => $this->purpose,
            'trip_date' => $this->trip_date?->toDateString(),
            'period' => $this->period,
            'status' => $this->status,
            'requester_id' => $this->requester_id,
            'destination_id' => $this->destination_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'approved_by' => $this->approved_by,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'requester' => UserResource::make($this->whenLoaded('requester')),
            'approver' => UserResource::make($this->whenLoaded('approver')),
            'destination' => DestinationResource::make($this->whenLoaded('destination')),
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'driver' => DriverResource::make($this->whenLoaded('driver')),
            'deleted_at' => $this->deleted_at,
        ];
    }
}