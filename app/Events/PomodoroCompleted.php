<?php

namespace App\Events;

use App\Models\PomodoroSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PomodoroCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PomodoroSession $session
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->session->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pomodoro.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => $this->session,
            'type' => $this->session->type,
            'duration' => $this->session->duration,
            'timestamp' => now()->toISOString(),
        ];
    }
}
