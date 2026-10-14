<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Layouts\Puzzle;

use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Whodoku\Models\AdminAuditLog;

class PuzzleAuditLogLayout extends Table
{
    /**
     * @var string
     */
    #[\Override]
    public $target = 'auditLogs';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('action', 'Действие')->cantHide(),

            TD::make('actor_email', 'Исполнитель')
                ->render(fn (AdminAuditLog $log) => $log->actor_email ?? 'Система'),

            TD::make('result', 'Результат')
                ->render(fn (AdminAuditLog $log) => $this->result($log)),

            TD::make('duration_ms', 'Длительность')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn (AdminAuditLog $log) => $log->duration_ms === null ? '—' : "{$log->duration_ms} мс"),

            TD::make('created_at', 'Время')
                ->usingComponent(DateTimeSplit::class),
        ];
    }

    private function result(AdminAuditLog $log): string
    {
        $validation = $log->validation_result ?? [];
        $solver = $log->solver_result ?? [];

        if (isset($validation['seed'])) {
            return "seed {$validation['seed']}";
        }

        return (string) ($validation['status'] ?? $solver['status'] ?? '—');
    }
}
