<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Puzzle;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Puzzle\GeneratedPuzzlePublicationException;
use Whodoku\Application\Puzzle\GeneratedPuzzlePublisher;
use Whodoku\Application\Puzzle\RandomPuzzleBatchGenerator;
use Whodoku\Domain\Puzzle\Enums\PuzzleType;
use Whodoku\Domain\Puzzle\Generation\GenerationProfile;
use Whodoku\Orchid\Layouts\Puzzle\GenerationProfileLayout;

class PuzzleGenerationScreen extends Screen
{
    private const int BATCH_SIZE = 10;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array<string, array{rows: int, cols: int, max_attempts: int}>
     */
    public function query(): iterable
    {
        return [
            'profile' => ['rows' => 4, 'cols' => 4, 'max_attempts' => 12, 'type' => ''],
        ];
    }

    public function name(): ?string
    {
        return 'Генерация дела';
    }

    public function description(): ?string
    {
        return 'Создаёт и публикует новое уникальное дело без ручного заполнения.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.generate'];
    }

    /**
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Сгенерировать дело')
                ->icon('bs.dice-5')
                ->method('generate'),
            Button::make('Сгенерировать 10 случайных дел')
                ->icon('bs.dice-6')
                ->confirm('Будет создано и опубликовано 10 дел со случайным типом преступления, размером поля (от 4×4 до 9×9) и seed.')
                ->method('generateBatch'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::block([GenerationProfileLayout::class])
                ->title('Параметры запуска')
                ->description('Карта, фигуры, показания, решение и развязка создаются автоматически.'),
        ];
    }

    public function generate(Request $request, GeneratedPuzzlePublisher $publisher): RedirectResponse
    {
        $profile = $request->validate([
            'profile.seed' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'profile.type' => ['nullable', 'in:'.implode(',', array_map(fn (PuzzleType $type) => $type->value, PuzzleType::generated()))],
            'profile.rows' => ['required', 'integer', 'min:4', 'max:9'],
            'profile.cols' => ['required', 'integer', 'min:4', 'max:9'],
            'profile.max_attempts' => ['required', 'integer', 'min:1', 'max:50'],
        ])['profile'];

        $seed = isset($profile['seed']) ? (int) $profile['seed'] : random_int(1, 2147483647);
        $maxAttempts = (int) $profile['max_attempts'];
        $rows = (int) $profile['rows'];
        $cols = (int) $profile['cols'];

        try {
            $puzzle = $publisher->publish(
                GenerationProfile::forGrid($rows, $cols, seed: $seed, maxAttempts: $maxAttempts, type: ($profile['type'] ?? '') !== '' ? PuzzleType::from($profile['type']) : null),
                $request->user(),
            );
        } catch (GeneratedPuzzlePublicationException $exception) {
            Toast::error($exception->reason === 'review_failed'
                ? 'Бот-проверяющий отклонил все варианты дела (он решает дело логикой и проверяет понятность). Повторите запуск с другим seed.'
                : 'Не удалось сгенерировать дело. Повторите запуск с другим seed или большим лимитом попыток.');

            return to_route('platform.puzzles.generate');
        }

        $review = $puzzle->auditLogs()->where('action', 'bot_review')->latest('id')->first()->validation_result ?? [];
        $retries = max(0, ($review['attempt'] ?? 1) - 1);
        Toast::success("Дело «{$puzzle->title}» опубликовано после проверки ботом (seed ".($review['seed'] ?? $seed).($retries > 0 ? ", отклонено вариантов: {$retries}" : '')."): /puzzles/{$puzzle->slug}");

        return to_route('platform.puzzles.generate');
    }

    public function generateBatch(Request $request, RandomPuzzleBatchGenerator $batch): RedirectResponse
    {
        $result = $batch->generate(self::BATCH_SIZE, $request->user());
        $published = count($result['published']);

        if ($published === 0) {
            Toast::error('Не удалось сгенерировать ни одного дела. Повторите запуск.');
        } elseif ($result['failed'] > 0) {
            Toast::warning("Опубликовано дел: {$published} из ".self::BATCH_SIZE.". Не удалось создать: {$result['failed']}.");
        } else {
            Toast::success("Опубликовано дел: {$published}.");
        }

        return to_route('platform.puzzles.generate');
    }
}
