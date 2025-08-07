<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $events = CalendarEvent::where('user_id', $request->user()->id)
            ->when($request->has('start'), fn ($query) => $query->whereDate('start_date', '>=', $request->start))
            ->when($request->has('end'), fn ($query) => $query->whereDate('end_date', '<=', $request->end))
            ->orderBy('start_date')
            ->get();

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'all_day' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $event = CalendarEvent::create([
            ...$validator->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($event, 201);
    }

    public function show(Request $request, CalendarEvent $event)
    {
        if ($event->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($event);
    }

    public function update(Request $request, CalendarEvent $event)
    {
        if ($event->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'all_day' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $event->update($validator->validated());

        return response()->json($event);
    }

    public function destroy(Request $request, CalendarEvent $event)
    {
        if ($event->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }

    public function upcoming(Request $request)
    {
        $days = $request->get('days', 7);

        $events = CalendarEvent::where('user_id', $request->user()->id)
            ->upcoming($days)
            ->take(10)
            ->get();

        return response()->json($events);
    }
}
