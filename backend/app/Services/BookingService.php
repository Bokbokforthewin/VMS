<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private DocumentCompliance $compliance)
    {
    }

    public function create(array $data, int $requesterId): Booking
    {
        return DB::transaction(function () use ($data, $requesterId) {
            $this->checkResources(
                $data['trip_date'],
                $data['period'],
                $data['vehicle_id'] ?? null,
                $data['driver_id'] ?? null
            );

            return Booking::create([...$data, 'requester_id' => $requesterId, 'status' => 'pending']);
        });
    }

    public function update(Booking $booking, array $data): Booking
    {
        $this->requireStatus($booking, ['pending'], 'edited');

        return DB::transaction(function () use ($booking, $data) {
            $date = Carbon::parse($data['trip_date'] ?? $booking->trip_date)->toDateString();
            $period = $data['period'] ?? $booking->period;
            $vehicleId = array_key_exists('vehicle_id', $data) ? $data['vehicle_id'] : $booking->vehicle_id;
            $driverId = array_key_exists('driver_id', $data) ? $data['driver_id'] : $booking->driver_id;

            $this->checkResources($date, $period, $vehicleId, $driverId, $booking->id);

            $booking->update($data);

            return $booking;
        });
    }

    public function delete(Booking $booking): void
    {
        $this->requireStatus($booking, ['pending', 'rejected'], 'deleted');
        $booking->delete();
    }

    public function approve(Booking $booking, int $vehicleId, int $driverId, int $userId): Booking
    {
        $this->requireStatus($booking, ['pending', 'approved'], 'approved');

        if ($booking->started_at) {
            $this->fail('status', 'This trip has already started.');
        }

        return DB::transaction(function () use ($booking, $vehicleId, $driverId, $userId) {
            $this->checkResources(
                $booking->trip_date->toDateString(),
                $booking->period,
                $vehicleId,
                $driverId,
                $booking->id
            );

            $booking->update([
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'status' => 'approved',
                'approved_by' => $userId,
            ]);

            return $booking;
        });
    }

    public function reject(Booking $booking, int $userId): Booking
    {
        $this->requireStatus($booking, ['pending'], 'rejected');
        $booking->update(['status' => 'rejected', 'approved_by' => $userId]);

        return $booking;
    }

    public function start(Booking $booking): Booking
    {
        $this->requireStatus($booking, ['approved'], 'started');

        if ($booking->started_at) {
            $this->fail('status', 'This trip has already started.');
        }
        if (! $booking->trip_date->isToday()) {
            $this->fail('trip_date', 'A trip can only be started on its trip date.');
        }

        $booking->update(['started_at' => now()]);

        return $booking;
    }

    public function complete(Booking $booking): Booking
    {
        $this->requireStatus($booking, ['approved'], 'completed');

        if (! $booking->started_at) {
            $this->fail('status', 'The trip has not been started.');
        }

        $booking->update(['ended_at' => now(), 'status' => 'completed']);

        return $booking;
    }

    /**
     * Vehicles and drivers that are free and compliant for a date and period.
     * Pass $exceptBookingId when editing so the booking's own slot isn't counted as taken.
     */
    public function availability(string $date, string $period, ?int $exceptBookingId = null): array
    {
        $taken = fn (string $column) => Booking::query()
            ->active()
            ->whereDate('trip_date', $date)
            ->where('period', $period)
            ->whereNotNull($column)
            ->when($exceptBookingId, fn ($q) => $q->where('id', '!=', $exceptBookingId))
            ->pluck($column);

        $vehicles = Vehicle::query()
            ->where('status', 'available')
            ->whereNotIn('id', $taken('vehicle_id'))
            ->orderBy('plate_no')
            ->get()
            ->filter(fn (Vehicle $v) => ! $this->compliance->missing('vehicle', $v->id, $date))
            ->values();

        $drivers = Driver::query()
            ->with('user')
            ->whereNotIn('id', $taken('driver_id'))
            ->get()
            ->filter(fn (Driver $d) => ! $this->compliance->missing('driver', $d->id, $date))
            ->sortBy(fn (Driver $d) => $d->user?->name)
            ->values();

        return ['vehicles' => $vehicles, 'drivers' => $drivers];
    }

    // ---- rules -------------------------------------------------------

    /** The core rules: vehicle usable, no double booking, no expired documents. */
    private function checkResources(
        string $date,
        string $period,
        ?int $vehicleId,
        ?int $driverId,
        ?int $ignoreBookingId = null
    ): void {
        $errors = [];

        if ($vehicleId) {
            $vehicle = Vehicle::lockForUpdate()->find($vehicleId);

            if (! $vehicle) {
                $errors['vehicle_id'][] = 'Vehicle not found.';
            } else {
                if ($vehicle->status !== 'available') {
                    $errors['vehicle_id'][] = 'Vehicle is '.str_replace('_', ' ', $vehicle->status).'.';
                }
                if ($this->isTaken('vehicle_id', $vehicleId, $date, $period, $ignoreBookingId)) {
                    $errors['vehicle_id'][] = "Vehicle is already booked for $date $period.";
                }
                if ($missing = $this->compliance->missing('vehicle', $vehicleId, $date)) {
                    $errors['vehicle_id'][] = 'Blocked: missing or expired '.implode(', ', $missing).' on the trip date.';
                }
            }
        }

        if ($driverId) {
            $driver = Driver::lockForUpdate()->find($driverId);

            if (! $driver) {
                $errors['driver_id'][] = 'Driver not found.';
            } else {
                if ($this->isTaken('driver_id', $driverId, $date, $period, $ignoreBookingId)) {
                    $errors['driver_id'][] = "Driver is already booked for $date $period.";
                }
                if ($missing = $this->compliance->missing('driver', $driverId, $date)) {
                    $errors['driver_id'][] = 'Blocked: missing or expired '.implode(', ', $missing).' on the trip date.';
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isTaken(string $column, int $id, string $date, string $period, ?int $ignoreBookingId): bool
    {
        return Booking::query()
            ->active()
            ->where($column, $id)
            ->whereDate('trip_date', $date)
            ->where('period', $period)
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->exists();
    }

    private function requireStatus(Booking $booking, array $allowed, string $action): void
    {
        if (! in_array($booking->status, $allowed, true)) {
            $this->fail('status', 'Only '.implode(' or ', $allowed)." bookings can be $action.");
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}