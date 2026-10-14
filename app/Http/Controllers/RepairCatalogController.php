<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class RepairCatalogController extends Controller
{
    public function undoState(Request $request): JsonResponse
    {
        return response()->json(['can_undo' => Cache::has($this->undoKey($request))]);
    }

    public function status(Request $request): JsonResponse
    {
        $input = $request->validate([
            'message_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $this->change($request, function (array &$catalog) use ($input): void {
            [$sectionIndex, $messageIndex] = $this->findMessageLocation($catalog, $input['message_id']);
            $message = &$catalog['sections'][$sectionIndex]['messages'][$messageIndex];
            if ($input['status'] === 'inactive') {
                $message['status'] = 'inactive';
                $message['status_changed_at'] = now()->toIso8601String();
            } else {
                unset($message['status'], $message['status_changed_at']);
            }
        });

        return response()->json(['ok' => true, 'message_id' => $input['message_id'], 'status' => $input['status']]);
    }

    public function move(Request $request): JsonResponse
    {
        $input = $request->validate([
            'message_id' => ['required', 'integer', 'min:1'],
            'target_section_id' => ['required', 'string', 'max:100'],
        ]);

        $this->change($request, function (array &$catalog) use ($input): void {
            $sourceIndex = null;
            $messageIndex = null;
            $targetIndex = null;
            foreach ($catalog['sections'] as $sectionIndex => $section) {
                if ($section['id'] === $input['target_section_id']) {
                    $targetIndex = $sectionIndex;
                }
                foreach ($section['messages'] as $index => $message) {
                    if ($message['id'] === $input['message_id']) {
                        abort_if($sourceIndex !== null, 422, 'ID сообщения повторяется');
                        $sourceIndex = $sectionIndex;
                        $messageIndex = $index;
                    }
                }
            }
            abort_if($sourceIndex === null || $targetIndex === null || $sourceIndex === $targetIndex, 422, 'Перенос невозможен');
            $message = array_splice($catalog['sections'][$sourceIndex]['messages'], $messageIndex, 1)[0];
            $catalog['sections'][$targetIndex]['messages'][] = $message;
        });

        return response()->json(['ok' => true, 'message_id' => $input['message_id']]);
    }

    public function undo(Request $request): JsonResponse
    {
        $key = $this->undoKey($request);
        $this->withLock(function () use ($key): void {
            $undo = Cache::get($key);
            $path = $this->catalogPath();
            abort_unless(is_array($undo) && is_file($path) && hash_file('sha256', $path) === $undo['after'], 409, 'Отмена недоступна: каталог уже изменился');
            $this->atomicWrite($path, $undo['before']);
            Cache::forget($key);
        });

        return response()->json(['ok' => true, 'undone' => true]);
    }

    public function import(Request $request): JsonResponse
    {
        $kind = $request->header('X-Import-Kind');
        abort_unless(in_array($kind, ['export', 'parsed'], true), 422, 'Выберите тип импорта');
        abort_unless($request->header('Content-Type') === 'application/zip', 415, 'Ожидался ZIP-файл');
        $dataRoot = dirname($this->catalogPath(), 2);
        if (!is_dir($dataRoot)) {
            mkdir($dataRoot, 0775, true);
        }
        $temporary = $dataRoot.'/.repair-upload-'.Str::random(24).'.zip';

        try {
            $input = $request->getContent(true);
            $output = fopen($temporary, 'xb');
            abort_unless(is_resource($input) && $output !== false, 500, 'Не удалось прочитать файл');
            $size = stream_copy_to_stream($input, $output, 1024 * 1024 * 1024 + 1);
            fclose($output);
            abort_unless($size !== false && $size > 0 && $size <= 1024 * 1024 * 1024, 413, 'ZIP-файл слишком большой');

            $this->withLock(function () use ($kind, $temporary, $dataRoot, $request): void {
                $process = new Process(['python3', base_path('scripts/import-repair.py'), $kind, $temporary], base_path(), ['REPAIR_DATA_ROOT' => $dataRoot]);
                $process->setTimeout(600);
                $process->run();
                abort_unless($process->isSuccessful(), 422, trim($process->getErrorOutput()) ?: 'Импорт не выполнен');
                Cache::forget($this->undoKey($request));
            });
        } finally {
            @unlink($temporary);
        }

        $catalog = json_decode(file_get_contents($this->catalogPath()), true, flags: JSON_THROW_ON_ERROR);

        return response()->json(['ok' => true, 'messages' => array_sum(array_map(fn (array $section): int => count($section['messages']), $catalog['sections']))]);
    }

    private function change(Request $request, callable $change): void
    {
        $this->withLock(function () use ($request, $change): void {
            $path = $this->catalogPath();
            abort_unless(is_file($path), 404, 'Каталог не найден');
            $before = file_get_contents($path);
            $catalog = json_decode($before, true, flags: JSON_THROW_ON_ERROR);
            $change($catalog);
            $after = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
            $this->atomicWrite($path, $after);
            Cache::put($this->undoKey($request), ['before' => $before, 'after' => hash('sha256', $after)], now()->addHours(2));
        });
    }

    private function findMessageLocation(array $catalog, int $id): array
    {
        $found = null;
        foreach ($catalog['sections'] as $sectionIndex => $section) {
            foreach ($section['messages'] as $messageIndex => $message) {
                if ($message['id'] === $id) {
                    abort_if($found !== null, 422, 'ID сообщения повторяется');
                    $found = [$sectionIndex, $messageIndex];
                }
            }
        }
        abort_if($found === null, 422, 'Сообщение не найдено');

        return $found;
    }

    private function withLock(callable $callback): void
    {
        $lock = fopen(dirname($this->catalogPath(), 2).'/.catalog.lock', 'c');
        abort_unless($lock !== false && flock($lock, LOCK_EX), 500, 'Каталог недоступен');
        try {
            $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $temporary = dirname($path).'/.'.Str::random(24).'.tmp';
        try {
            file_put_contents($temporary, $contents, LOCK_EX);
            chmod($temporary, fileperms($path) & 0777);
            rename($temporary, $path);
        } finally {
            @unlink($temporary);
        }
    }

    private function catalogPath(): string
    {
        return rtrim(config('services.repair.root'), '/').'/catalog.json';
    }

    private function undoKey(Request $request): string
    {
        return 'repair.undo.user.'.$request->user()->getAuthIdentifier();
    }
}
