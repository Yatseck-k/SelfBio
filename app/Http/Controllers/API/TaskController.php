<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = Task::where('user_id', $request->user()->id)
            ->when($request->has('completed'), fn ($query) => $request->completed ? $query->completed() : $query->pending())
            ->when($request->has('priority'), fn ($query) => $query->where('priority', $request->priority))
            ->orderByRaw("CASE WHEN priority = 'high' THEN 1 WHEN priority = 'medium' THEN 2 ELSE 3 END")
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'in:low,medium,high',
            'due_date' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $task = Task::create([
            ...$validator->validated(),
            'user_id' => $request->user()->id,
        ]);

        // Отправляем WebSocket событие
        broadcast(new \App\Events\TaskCreated($task));

        return response()->json($task, 201);
    }

    public function show(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($task);
    }

    public function update(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'in:low,medium,high',
            'completed' => 'boolean',
            'due_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $task->update($validator->validated());

        return response()->json($task);
    }

    public function destroy(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function toggle(Request $request, Task $task)
    {
        if ($task->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $task->update(['completed' => ! $task->completed]);

        return response()->json($task);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'total' => Task::where('user_id', $userId)->count(),
            'completed' => Task::where('user_id', $userId)->completed()->count(),
            'pending' => Task::where('user_id', $userId)->pending()->count(),
            'overdue' => Task::where('user_id', $userId)->overdue()->count(),
            'due_today' => Task::where('user_id', $userId)->dueToday()->count(),
            'high_priority' => Task::where('user_id', $userId)->highPriority()->pending()->count(),
        ]);
    }
}
