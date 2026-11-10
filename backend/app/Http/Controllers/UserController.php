<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->with('driver')
            ->when($request->query('search'), fn ($q, $s) => $q->where(
                fn ($q) => $q->where('name', 'like', "%$s%")
                    ->orWhere('email', 'like', "%$s%")
                    ->orWhere('designation', 'like', "%$s%")
                    ->orWhere('unit', 'like', "%$s%")
                    ->orWhere('division', 'like', "%$s%")
            ))
            ->when($request->query('unit'), fn ($q, $v) => $q->where('unit', $v))
            ->when($request->query('division'), fn ($q, $v) => $q->where('division', $v));

        match ($request->query('trashed')) {
            'with' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => null,
        };

        return UserResource::collection(
            $query->orderBy('name')->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function store(Request $request)
    {
        $user = User::create($request->validate($this->rules()));

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function show(User $user)
    {
        return UserResource::make($user->load('driver'));
    }

    public function update(Request $request, User $user)
    {
        $user->update($request->validate($this->rules($user)));

        return UserResource::make($user->load('driver'));
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $driver = $user->driver;
        if ($driver && $driver->bookings()->active()->whereDate('trip_date', '>=', today())->exists()) {
            return response()->json(
                ['message' => 'This user is a driver with upcoming bookings. Reassign them first.'],
                422
            );
        }

        $user->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return UserResource::make($user->load('driver'));
    }

    private function rules(?User $user = null): array
    {
        $required = $user ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$required, 'string', 'min:8'],
            'designation' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],
        ];
    }
}