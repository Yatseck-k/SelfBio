<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->task->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.created';
    }

    public function broadcastWith(): array
    {
        return [
            'task' => $this->task,
            'timestamp' => now()->toISOString(),
        ];
    }
}
