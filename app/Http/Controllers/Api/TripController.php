<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateTicketRequest;
use App\Http\Requests\Api\TripRequest;
use App\Http\Resources\Api\SeatsResource;
use App\Http\Resources\Api\TripsResource;
use App\Models\Route;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    /**
     * @param TripRequest $request
     * @return JsonResponse
     */
    public function index(TripRequest $request): JsonResponse
    {
        $data = [intval($request->from), intval($request->to)];

        $routes = Route::query()
            ->whereJsonContains('stations', $data)
            ->pluck('id');

        $trips = Trip::query()
            ->with('route:id,name')
            ->select('buses.name as bus_number', 'buses.capacity', 'trips.*')
            ->join('buses', 'buses.id', '=', 'trips.bus_id')
            ->withCount(['tickets', 'tickets as half_tickets_count' => function ($q) use($data) {
                $q->where('end_station', $data[0]);
            }])
            ->where('status', Trip::AVAILABLE)
            ->whereIn('route_id', $routes)
            ->havingRaw('tickets_count < capacity OR half_tickets_count > 0')
            ->paginate();

        return response()->json([
            'data' => TripsResource::collection($trips)
        ]);
    }

    /**
     * @param Trip $trip
     * @param TripRequest $request
     * @return JsonResponse
     */
    public function show(Trip $trip, TripRequest $request): JsonResponse
    {
        abort_unless($trip->status === Trip::AVAILABLE, 404);

        $trip->load(['bus', 'route']);

        $seats = SeatsResource::collection(Seat::query()
            ->select('id', 'name')
            ->where('bus_id', $trip->bus_id)
            ->withCount([
                'tickets as is_available' => function($q) use($trip) {
                    $q->where('trip_id', $trip->id);
                },
                'tickets as half_tickets_count' => function($q) use($trip) {
                    $q->where('trip_id', $trip->id)->where('end_station', request()->from);
                }
            ])
            ->get());

        return response()->json([
            'trip' => [
                'id' => $trip->id,
                'route_name' => $trip->route->name,
                'moves_at' => $trip->moves_at->toDateTimeString(),
            ],
            'seats' => $seats
        ]);
    }

    public function store(Trip $trip, CreateTicketRequest $request)
    {
        $inputs = [intval($request->from), intval($request->to)];

        $trip->load(['route', 'bus']);

        $trip->bus->seats()->where('id', $request->seat_id)->firstOrFail();

        $routeStations = $trip->route->stations;

        abort_unless($routeStations->contains($inputs[0]) && $routeStations->contains($inputs[1]), 422, __('Invalid route.'));

        $seat = Seat::query()
            ->where('id', $request->seat_id)
            ->withCount([
                'tickets as is_available' => function($q) use($trip) {
                    $q->where('trip_id', $trip->id);
                },
                'tickets as half_tickets_count' => function($q) use($trip) {
                    $q->where('trip_id', $trip->id)->where('end_station', request()->from);
                }
            ])->first();

        abort_unless(!$seat->is_available || $seat->half_tickets_count > 0, 422, __('Seat already reserved before.'));

        $startIndex = $routeStations->search($inputs[0]);
        $lastIndex = $routeStations->search($inputs[1]);

        $ticket = Ticket::create([
            'user_id' => auth()->id(),
            'seat_id' => $seat->id,
            'trip_id' => $trip->id,
            'start_station' => $request->from,
            'stations' => $routeStations->slice($startIndex, $lastIndex - $startIndex + 1),
            'end_station' => $request->to,
            'price' => 50 // TODO:: implement pricing logic
        ]);

        return response()->json([
            'msg' => __('Ticket Created successfully.'),
            'ticket' => $ticket,
        ]);
    }
}
