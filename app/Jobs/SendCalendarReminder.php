<?php

namespace App\Jobs;

use App\Events\CalendarEventReminder;
use App\Models\CalendarEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCalendarReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public CalendarEvent $event,
        public int $minutesBefore = 15
    ) {}

    public function handle(): void
    {
        try {
            // Проверяем, что событие еще не началось
            if ($this->event->start_date->isFuture()) {
                // Отправляем WebSocket уведомление
                broadcast(new CalendarEventReminder($this->event, $this->minutesBefore));

                Log::info("Calendar reminder sent for event: {$this->event->title}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to send calendar reminder: '.$e->getMessage());
        }
    }
}
