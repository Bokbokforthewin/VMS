<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $query = Driver::query()
            ->with('user')
            ->when($request->query('search'), fn ($q, $s) => $q->where(
                fn ($q) => $q->where('license_no', 'like', "%$s%")
                    ->orWhere('contact_no', 'like', "%$s%")
                    ->orWhereHas('user', fn ($u) => $u->withTrashed()->where('name', 'like', "%$s%"))
            ));

        match ($request->query('trashed')) {
            'with' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => null,
        };

        return DriverResource::collection(
            $query->latest('id')->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // an existing, non-deleted user who is not already a driver
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                Rule::unique('drivers', 'user_id'),
            ],
            'license_no' => ['nullable', 'string', 'max:50'],
            'contact_no' => ['nullable', 'string', 'max:30'],
        ]);

        $driver = Driver::create($data)->load('user');

        return DriverResource::make($driver)->response()->setStatusCode(201);
    }

    public function show(Driver $driver)
    {
        return DriverResource::make($driver->load('user'));
    }

    public function update(Request $request, Driver $driver)
    {
        // user_id cannot change; to switch the person, create a new driver record
        $driver->update($request->validate([
            'license_no' => ['nullable', 'string', 'max:50'],
            'contact_no' => ['nullable', 'string', 'max:30'],
        ]));

        return DriverResource::make($driver->load('user'));
    }

    public function destroy(Driver $driver)
    {
        $upcoming = $driver->bookings()->active()->whereDate('trip_date', '>=', today())->exists();

        if ($upcoming) {
            return response()->json(
                ['message' => 'This driver has upcoming bookings. Reassign or reject them first.'],
                422
            );
        }

        $driver->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $driver = Driver::onlyTrashed()->findOrFail($id);
        $driver->restore();

        return DriverResource::make($driver->load('user'));
    }
}