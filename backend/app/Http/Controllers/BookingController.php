<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\DriverResource;
use App\Http\Resources\VehicleResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// NOTE: roles are not set up yet, so any logged-in user can do everything here.
// Add the role checks (head approves, dispatcher assigns, driver starts/completes)
// when we build roles and permissions.
class BookingController extends Controller
{
    private const WITH = ['requester', 'approver', 'destination', 'vehicle', 'driver.user'];

    public function __construct(private BookingService $bookings)
    {
    }

    public function index(Request $request)
    {
        $query = Booking::query()
            ->with(self::WITH)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('period'), fn ($q, $v) => $q->where('period', $v))
            ->when($request->query('vehicle_id'), fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->query('driver_id'), fn ($q, $v) => $q->where('driver_id', $v))
            ->when($request->boolean('mine'), fn ($q) => $q->where('requester_id', $request->user()->id))
            ->when($request->query('date'), fn ($q, $v) => $q->whereDate('trip_date', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('trip_date', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('trip_date', '<=', $v));

        match ($request->query('trashed')) {
            'with' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => null,
        };

        // orderBy period: 'AM' sorts before 'PM'
        return BookingResource::collection(
            $query->orderBy('trip_date', 'desc')->orderBy('period')->orderBy('id')
                ->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function store(Request $request)
    {
        $booking = $this->bookings->create($this->validated($request), $request->user()->id);

        return BookingResource::make($booking->load(self::WITH))->response()->setStatusCode(201);
    }

    public function show(Booking $booking)
    {
        return BookingResource::make($booking->load(self::WITH));
    }

    public function update(Request $request, Booking $booking)
    {
        $booking = $this->bookings->update($booking, $this->validated($request, partial: true));

        return BookingResource::make($booking->load(self::WITH));
    }

    public function destroy(Booking $booking)
    {
        $this->bookings->delete($booking);

        return response()->noContent();
    }

    public function approve(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'driver_id' => ['required', Rule::exists('drivers', 'id')->whereNull('deleted_at')],
        ]);

        $booking = $this->bookings->approve($booking, $data['vehicle_id'], $data['driver_id'], $request->user()->id);

        return BookingResource::make($booking->load(self::WITH));
    }

    public function reject(Request $request, Booking $booking)
    {
        return BookingResource::make(
            $this->bookings->reject($booking, $request->user()->id)->load(self::WITH)
        );
    }

    public function start(Booking $booking)
    {
        return BookingResource::make($this->bookings->start($booking)->load(self::WITH));
    }

    public function complete(Booking $booking)
    {
        return BookingResource::make($this->bookings->complete($booking)->load(self::WITH));
    }

    // GET /bookings/availability?trip_date=2026-11-12&period=AM[&except=ID]
    public function availability(Request $request)
    {
        $data = $request->validate([
            'trip_date' => ['required', 'date_format:Y-m-d'],
            'period' => ['required', Rule::in(Booking::PERIODS)],
            'except' => ['nullable', 'integer'],
        ]);

        $result = $this->bookings->availability($data['trip_date'], $data['period'], $data['except'] ?? null);

        return response()->json([
            'vehicles' => VehicleResource::collection($result['vehicles']),
            'drivers' => DriverResource::collection($result['drivers']),
        ]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'destination_id' => [$required, Rule::exists('destinations', 'id')->whereNull('deleted_at')],
            'purpose' => [$required, 'string', 'max:255'],
            'trip_date' => [$required, 'date_format:Y-m-d', 'after_or_equal:today'],
            'period' => [$required, Rule::in(Booking::PERIODS)],
            // optional at request time; the dispatcher can assign later via /approve
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'driver_id' => ['nullable', Rule::exists('drivers', 'id')->whereNull('deleted_at')],
        ]);
    }
}