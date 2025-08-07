<?php

namespace App\Events;

use App\Models\CalendarEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CalendarEventReminder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CalendarEvent $event,
        public int $minutesBefore = 15
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->event->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'calendar.event.reminder';
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'minutes_before' => $this->minutesBefore,
            'message' => "Напоминание: '{$this->event->title}' через {$this->minutesBefore} минут",
            'timestamp' => now()->toISOString(),
        ];
    }
}
