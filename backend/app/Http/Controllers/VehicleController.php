<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $query = Vehicle::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where(
                fn ($q) => $q->where('plate_no', 'like', "%$s%")
                    ->orWhere('brand', 'like', "%$s%")
                    ->orWhere('model', 'like', "%$s%")
            ))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s));

        match ($request->query('trashed')) {
            'with' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => null,
        };

        return VehicleResource::collection(
            $query->orderBy('plate_no')->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function store(Request $request)
    {
        $vehicle = Vehicle::create($request->validate($this->rules()));

        return VehicleResource::make($vehicle)->response()->setStatusCode(201);
    }

    public function show(Vehicle $vehicle)
    {
        return VehicleResource::make($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $vehicle->update($request->validate($this->rules($vehicle)));

        return VehicleResource::make($vehicle);
    }

    public function destroy(Vehicle $vehicle)
    {
        $upcoming = $vehicle->bookings()->active()->whereDate('trip_date', '>=', today())->exists();

        if ($upcoming) {
            return response()->json(
                ['message' => 'This vehicle has upcoming bookings. Reassign or reject them first.'],
                422
            );
        }

        $vehicle->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $vehicle = Vehicle::onlyTrashed()->findOrFail($id);
        $vehicle->restore();

        return VehicleResource::make($vehicle);
    }

    private function rules(?Vehicle $vehicle = null): array
    {
        $required = $vehicle ? 'sometimes' : 'required';

        return [
            'plate_no' => [$required, 'string', 'max:20', Rule::unique('vehicles', 'plate_no')->ignore($vehicle)],
            'brand' => [$required, 'string', 'max:100'],
            'model' => [$required, 'string', 'max:100'],
            'seats' => [$required, 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::in(Vehicle::STATUSES)],
        ];
    }
}