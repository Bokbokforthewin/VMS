<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationResource;
use App\Models\Destination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DestinationController extends Controller
{
    public function index()
    {
        return DestinationResource::collection(Destination::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $destination = Destination::create($request->validate($this->rules()));

        return DestinationResource::make($destination)->response()->setStatusCode(201);
    }

    public function update(Request $request, Destination $destination)
    {
        $destination->update($request->validate($this->rules($destination)));

        return DestinationResource::make($destination);
    }

    public function destroy(Destination $destination)
    {
        $destination->delete();

        return response()->noContent();
    }

    private function rules(?Destination $destination = null): array
    {
        $required = $destination ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', Rule::unique('destinations', 'name')->ignore($destination)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}