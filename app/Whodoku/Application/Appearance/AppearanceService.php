<?php

declare(strict_types=1);

namespace Whodoku\Application\Appearance;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\AppearanceImage;
use Whodoku\Models\AppearanceSetting;
use Whodoku\Models\ObjectKind;
use Whodoku\Models\RoomTheme;
use Whodoku\Models\User;

/**
 * The look of the public site that the administrator edits: an image per slot (furniture, finds at the scene,
 * seals, room floors), seal captions and animation switches. Every slot has a built-in default (files in
 * `public/icons`); an upload replaces it and «restore» goes back to the default. Uploads are cleaned
 * (see {@see SvgSanitizer}) and kept on the `public` disk.
 */
class AppearanceService
{
    public const MAX_BYTES = 61440;

    public const DISK_DIR = 'appearance';

    /** Finds at the scene, by crime type. */
    public const FINDINGS = [
        'murder' => 'Тело жертвы',
        'theft' => 'Украденная вещь',
        'poisoning' => 'Отравленный бокал',
        'arson' => 'Очаг пожара',
        'forgery' => 'Подлинник картины',
    ];

    /** Seals: slot code => default caption. The image is optional, the caption is edited as text. */
    public const SEALS = [
        'solved' => 'ДЕЛО ЗАКРЫТО',
        'new' => 'НОВОЕ',
        'guest' => 'ГОСТЕВОЕ',
        'rank' => 'НОВОЕ ЗВАНИЕ',
    ];

    /** Animations the administrator can switch: code => label. */
    public const ANIMATIONS = [
        'place' => 'Постановка гостя на клетку',
        'cross' => 'Перекрытие строки и столбца',
        'reveal' => 'Раскрытие находки на месте преступления',
        'ui' => 'Появление и наведение: карточки дел, подсказки, кнопки, подсветка клеток',
        'ending' => 'Печать «Дело закрыто» и итоговая карточка',
        'rank' => 'Повышение звания',
    ];

    /** The floor pattern each built-in room gets until the administrator uploads one. */
    private const array FLOOR_PATTERNS = [
        'библиотека' => 'parquet', 'зимний сад' => 'grass', 'оранжерея' => 'grass', 'музыкальная гостиная' => 'herringbone',
        'кабинет' => 'parquet', 'галерея' => 'checker', 'обеденный зал' => 'planks', 'мастерская' => 'planks', 'холл' => 'tiles',
        'веранда' => 'planks', 'кладовая' => 'stone', 'бильярдная' => 'carpet', 'спальня' => 'carpet', 'кухня' => 'tiles',
    ];

    public function __construct(private readonly SvgSanitizer $sanitizer = new SvgSanitizer) {}

    /**
     * Every slot the administrator can fill, grouped, with the label, the default image and the current upload.
     *
     * @return array<string, list<array{slot: string, label: string, default: ?string, url: ?string, custom: bool}>>
     */
    public function slots(): array
    {
        $uploads = AppearanceImage::query()->get()->keyBy('slot');
        $make = function (string $slot, string $label, ?string $default) use ($uploads): array {
            $upload = $uploads->get($slot);

            return [
                'slot' => $slot, 'label' => $label, 'default' => $default,
                'url' => $upload === null ? $default : $this->url($upload),
                'custom' => $upload !== null,
            ];
        };

        $groups = ['kind' => [], 'finding' => [], 'seal' => [], 'floor' => []];
        foreach (ObjectKind::query()->orderByDesc('occupiable')->orderBy('label')->get() as $kind) {
            $groups['kind'][] = $make('kind:'.$kind->code, $kind->label, $this->builtIn("kind/{$kind->code}.svg"));
        }
        foreach (self::FINDINGS as $type => $label) {
            $groups['finding'][] = $make("finding:{$type}", $label, $this->builtIn("finding/{$type}.svg"));
        }
        foreach (self::SEALS as $code => $caption) {
            $groups['seal'][] = $make("seal:{$code}", "Печать «{$caption}»", null);
        }
        foreach (RoomTheme::query()->orderBy('name')->get() as $theme) {
            $name = mb_strtolower($theme->name);
            $pattern = self::FLOOR_PATTERNS[$name] ?? null;
            $groups['floor'][] = $make("floor:{$name}", mb_convert_case($theme->name, MB_CASE_TITLE), $pattern === null ? null : $this->builtIn("floor/{$pattern}.svg"));
        }

        return $groups;
    }

    /** What the public pages need: the effective URL per slot (only slots that have an image) and the settings. */
    public function publicPayload(): array
    {
        $images = [];
        foreach ($this->slots() as $slots) {
            foreach ($slots as $slot) {
                if ($slot['url'] !== null) {
                    $images[$slot['slot']] = $slot['url'];
                }
            }
        }

        return ['images' => $images, 'seals' => $this->seals(), 'animations' => $this->animations()];
    }

    /** @return array<string, string> */
    public function seals(): array
    {
        $stored = AppearanceSetting::query()->find('seals')->value ?? [];

        $captions = [];
        foreach (self::SEALS as $code => $default) {
            $captions[$code] = $this->caption($stored[$code] ?? null, $default);
        }

        return $captions;
    }

    /** @return array<string, bool> */
    public function animations(): array
    {
        $stored = AppearanceSetting::query()->find('animations')->value ?? [];
        $result = ['enabled' => (bool) ($stored['enabled'] ?? true)];
        foreach (array_keys(self::ANIMATIONS) as $code) {
            $result[$code] = (bool) ($stored[$code] ?? true);
        }

        return $result;
    }

    /** @param  array<string, mixed>  $seals  @param  array<string, mixed>  $animations */
    public function saveSettings(array $seals, array $animations, ?User $actor): void
    {
        $captions = [];
        foreach (self::SEALS as $code => $default) {
            $captions[$code] = $this->caption($seals[$code] ?? null, $default);
        }
        $switches = ['enabled' => (bool) ($animations['enabled'] ?? false)];
        foreach (array_keys(self::ANIMATIONS) as $code) {
            $switches[$code] = (bool) ($animations[$code] ?? false);
        }
        AppearanceSetting::query()->updateOrCreate(['key' => 'seals'], ['value' => $captions]);
        AppearanceSetting::query()->updateOrCreate(['key' => 'animations'], ['value' => $switches]);
        $this->audit($actor, 'settings', 'seals, animations');
    }

    /** @throws InvalidImage */
    public function upload(string $slot, UploadedFile $file, ?User $actor): void
    {
        $this->assertKnown($slot);
        $content = $this->prepare($file);

        $extension = $content['extension'];
        $path = self::DISK_DIR.'/'.str_replace([':', ' ', '/'], '-', md5($slot)).'-'.substr(md5($content['data']), 0, 8).'.'.$extension;
        Storage::disk('public')->put($path, $content['data']);

        $previous = AppearanceImage::query()->where('slot', $slot)->first();
        AppearanceImage::query()->updateOrCreate(['slot' => $slot], ['path' => $path]);
        if ($previous !== null && $previous->path !== $path) {
            Storage::disk('public')->delete($previous->path);
        }
        $this->audit($actor, 'upload', $slot);
    }

    public function restore(string $slot, ?User $actor): void
    {
        $upload = AppearanceImage::query()->where('slot', $slot)->first();
        if ($upload === null) {
            return;
        }
        Storage::disk('public')->delete($upload->path);
        $upload->delete();
        $this->audit($actor, 'restore', $slot);
    }

    /** @return array{data: string, extension: string} */
    private function prepare(UploadedFile $file): array
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidImage('Файл больше 60 КБ.');
        }
        $data = (string) file_get_contents($file->getRealPath());
        if (str_starts_with(ltrim($data), '<')) {
            return ['data' => $this->sanitizer->clean($data), 'extension' => 'svg'];
        }

        $info = @getimagesizefromstring($data);
        $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if ($info === false || !isset($types[$info[2]])) {
            throw new InvalidImage('Допустимы SVG, PNG и WebP.');
        }
        if ($info[0] > 1024 || $info[1] > 1024) {
            throw new InvalidImage('Изображение больше 1024×1024.');
        }

        return ['data' => $data, 'extension' => $types[$info[2]]];
    }

    private function assertKnown(string $slot): void
    {
        foreach ($this->slots() as $slots) {
            foreach ($slots as $known) {
                if ($known['slot'] === $slot) {
                    return;
                }
            }
        }
        throw new InvalidImage('Неизвестное место для изображения.');
    }

    private function url(AppearanceImage $image): string
    {
        return '/storage/'.$image->path.'?v='.($image->updated_at->timestamp ?? 0);
    }

    private function builtIn(string $file): ?string
    {
        return is_file(public_path("icons/{$file}")) ? "/icons/{$file}" : null;
    }

    private function caption(mixed $value, string $default): string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? $default : mb_substr($text, 0, 24);
    }

    private function audit(?User $actor, string $operation, string $item): void
    {
        AdminAuditLog::query()->create([
            'user_id' => $actor?->id, 'actor_email' => $actor?->email, 'action' => 'appearance_change',
            'validation_result' => ['operation' => $operation, 'item' => $item],
        ]);
    }
}
