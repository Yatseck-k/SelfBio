<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Illuminate\Support\Facades\DB;
use Whodoku\Domain\Puzzle\Generation\ObjectCatalog;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\ObjectKind;
use Whodoku\Models\ObjectName;
use Whodoku\Models\RoomTheme;
use Whodoku\Models\User;

/**
 * The lists of furniture the administrator edits: kinds (usable or not), their names and the furniture of each
 * room. Generation reads the active part of them as a plain `ObjectCatalog`. Every change is checked so that the
 * generator can still build a map from what is left, and a kind that stands on a map cannot be removed or
 * change its «usable» flag: old maps keep what they show.
 */
class ObjectCatalogService
{
    /** Nine guests need nine different usable names; obstacles and rooms need a few as well. */
    public const MIN_USABLE_NAMES = 9;

    public const MIN_UNUSABLE_NAMES = 3;

    public const MIN_ROOM_THEMES = 9;

    /** The active lists as the generator sees them; the built-in vocabulary when nothing is stored. */
    public function current(): ObjectCatalog
    {
        $kinds = [];
        foreach (ObjectKind::query()->where('active', true)->orderBy('id')->with(['names' => fn ($query) => $query->where('active', true)->orderBy('id')])->get() as $kind) {
            $entry = ['icon' => $kind->icon, 'label' => $kind->label, 'occupiable' => $kind->occupiable, 'names' => $kind->names->pluck('name')->all()];
            if ($kind->standing_phrase !== null && $kind->standing_phrase !== '') {
                $entry['on'] = $kind->standing_phrase;
            }
            if ($entry['names'] !== []) {
                $kinds[$kind->code] = $entry;
            }
        }
        if ($kinds === []) {
            return ObjectCatalog::defaults();
        }

        $themes = [];
        foreach (RoomTheme::query()->where('active', true)->orderBy('id')->get() as $theme) {
            $themes[mb_strtolower($theme->name)] = array_values(array_filter($theme->kinds, fn ($code) => isset($kinds[$code])));
        }

        return new ObjectCatalog($kinds, $themes);
    }

    /** @return array<string, int> how many objects of each kind stand on saved maps */
    public function usage(): array
    {
        return DB::table('puzzle_objects')->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type')->map(fn ($total) => (int) $total)->all();
    }

    /** @return list<string> what is missing for the generator, empty when the lists are enough */
    public function problems(): array
    {
        if (!ObjectKind::query()->where('active', true)->exists()) {
            return ['Нужен хотя бы один включённый вид предметов.'];
        }

        $catalog = $this->current();
        $problems = [];
        $usable = count($catalog->usableEntries());
        $unusable = count($catalog->unusableEntries());
        if ($usable < self::MIN_USABLE_NAMES) {
            $problems[] = 'Нужно минимум '.self::MIN_USABLE_NAMES." включённых названий используемых предметов (сейчас $usable): гостям нужны разные предметы.";
        }
        if ($unusable < self::MIN_UNUSABLE_NAMES) {
            $problems[] = 'Нужно минимум '.self::MIN_UNUSABLE_NAMES." включённых названий неиспользуемых предметов (сейчас $unusable): ими закрываются клетки.";
        }
        if (count($catalog->roomNames()) < self::MIN_ROOM_THEMES) {
            $problems[] = 'Нужно минимум '.self::MIN_ROOM_THEMES.' включённых комнат (сейчас '.count($catalog->roomNames()).').';
        }

        return $problems;
    }

    /** @param array{code?: string, label: string, icon: string, occupiable?: bool, standing_phrase?: ?string} $data */
    public function saveKind(?ObjectKind $kind, array $data, ?User $actor = null): ObjectKind
    {
        return $this->change('kind', $kind?->exists ? 'update' : 'create', $actor, function () use ($kind, $data): array {
            $label = trim((string) $data['label']);
            $icon = trim((string) $data['icon']);
            if ($label === '' || $icon === '') {
                throw new ObjectCatalogException('У вида должны быть название и значок.');
            }
            $phrase = isset($data['standing_phrase']) && trim((string) $data['standing_phrase']) !== '' ? trim((string) $data['standing_phrase']) : null;

            if ($kind?->exists) {
                $occupiable = (bool) ($data['occupiable'] ?? $kind->occupiable);
                if ($occupiable !== $kind->occupiable && ($this->usage()[$kind->code] ?? 0) > 0) {
                    throw new ObjectCatalogException('Вид уже стоит на картах: признак «можно разместить» менять нельзя. Создайте новый вид.');
                }
                $kind->update(['label' => $label, 'icon' => $icon, 'occupiable' => $occupiable, 'standing_phrase' => $phrase]);

                return [$kind, $kind->code];
            }

            $code = mb_strtolower(trim((string) ($data['code'] ?? '')));
            if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $code)) {
                throw new ObjectCatalogException('Код вида — латиница, цифры и «_», от 2 до 32 символов, начинается с буквы.');
            }
            if (ObjectKind::query()->where('code', $code)->exists()) {
                throw new ObjectCatalogException("Вид с кодом «{$code}» уже есть.");
            }
            $created = ObjectKind::query()->create(['code' => $code, 'label' => $label, 'icon' => $icon, 'occupiable' => (bool) ($data['occupiable'] ?? false), 'standing_phrase' => $phrase, 'active' => true]);

            return [$created, $code];
        });
    }

    public function setKindActive(ObjectKind $kind, bool $active, ?User $actor = null): void
    {
        $this->change('kind', $active ? 'enable' : 'disable', $actor, function () use ($kind, $active): array {
            $kind->update(['active' => $active]);

            return [$kind, $kind->code];
        });
    }

    public function deleteKind(ObjectKind $kind, ?User $actor = null): void
    {
        $this->change('kind', 'delete', $actor, function () use ($kind): array {
            if (($this->usage()[$kind->code] ?? 0) > 0) {
                throw new ObjectCatalogException('Вид уже стоит на картах дел: его можно только отключить, удалить нельзя.');
            }
            foreach (RoomTheme::query()->get() as $theme) {
                $theme->update(['kinds' => array_values(array_diff($theme->kinds, [$kind->code]))]);
            }
            $kind->delete();

            return [$kind, $kind->code];
        });
    }

    public function saveName(?ObjectName $name, int $kindId, string $text, ?User $actor = null): ObjectName
    {
        return $this->change('name', $name?->exists ? 'update' : 'create', $actor, function () use ($name, $kindId, $text): array {
            $text = trim($text);
            if ($text === '' || mb_strlen($text) > 120) {
                throw new ObjectCatalogException('Название — от 1 до 120 символов.');
            }
            if (!ObjectKind::query()->whereKey($kindId)->exists()) {
                throw new ObjectCatalogException('Выберите вид предмета.');
            }
            $taken = ObjectName::query()->whereRaw('lower(name) = ?', [mb_strtolower($text)])->when($name?->exists, fn ($query) => $query->whereKeyNot($name->id))->exists();
            if ($taken) {
                throw new ObjectCatalogException("Название «{$text}» уже есть: в показаниях предметы различаются по названию.");
            }
            $saved = $name?->exists ? tap($name)->update(['object_kind_id' => $kindId, 'name' => $text]) : ObjectName::query()->create(['object_kind_id' => $kindId, 'name' => $text, 'active' => true]);

            return [$saved, $text];
        });
    }

    public function setNameActive(ObjectName $name, bool $active, ?User $actor = null): void
    {
        $this->change('name', $active ? 'enable' : 'disable', $actor, function () use ($name, $active): array {
            $name->update(['active' => $active]);

            return [$name, $name->name];
        });
    }

    public function deleteName(ObjectName $name, ?User $actor = null): void
    {
        $this->change('name', 'delete', $actor, function () use ($name): array {
            $name->delete();

            return [$name, $name->name];
        });
    }

    /** @param list<string> $kinds */
    public function saveTheme(?RoomTheme $theme, string $name, array $kinds, ?User $actor = null): RoomTheme
    {
        return $this->change('theme', $theme?->exists ? 'update' : 'create', $actor, function () use ($theme, $name, $kinds): array {
            $name = mb_strtolower(trim($name));
            if ($name === '' || mb_strlen($name) > 60) {
                throw new ObjectCatalogException('Название комнаты — от 1 до 60 символов.');
            }
            $kinds = array_values(array_unique($kinds));
            $known = ObjectKind::query()->whereIn('code', $kinds)->pluck('code')->all();
            if ($kinds === [] || array_diff($kinds, $known) !== []) {
                throw new ObjectCatalogException('Выберите хотя бы один существующий вид предметов для комнаты.');
            }
            $taken = RoomTheme::query()->where('name', $name)->when($theme?->exists, fn ($query) => $query->whereKeyNot($theme->id))->exists();
            if ($taken) {
                throw new ObjectCatalogException("Комната «{$name}» уже есть.");
            }
            $saved = $theme?->exists ? tap($theme)->update(['name' => $name, 'kinds' => $kinds]) : RoomTheme::query()->create(['name' => $name, 'kinds' => $kinds, 'active' => true]);

            return [$saved, $name];
        });
    }

    public function setThemeActive(RoomTheme $theme, bool $active, ?User $actor = null): void
    {
        $this->change('theme', $active ? 'enable' : 'disable', $actor, function () use ($theme, $active): array {
            $theme->update(['active' => $active]);

            return [$theme, $theme->name];
        });
    }

    public function deleteTheme(RoomTheme $theme, ?User $actor = null): void
    {
        $this->change('theme', 'delete', $actor, function () use ($theme): array {
            $theme->delete();

            return [$theme, $theme->name];
        });
    }

    /**
     * Applies a change and keeps it only when the generator still has enough to work with.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  callable(): array{T, string}  $apply
     * @return T
     */
    private function change(string $entity, string $operation, ?User $actor, callable $apply)
    {
        return DB::transaction(function () use ($entity, $operation, $actor, $apply) {
            [$model, $label] = $apply();

            // Taking things away must not leave the generator short; adding never does.
            if (in_array($operation, ['disable', 'delete', 'update'], true) && ($problems = $this->problems()) !== []) {
                throw new ObjectCatalogException('Изменение отклонено. '.implode(' ', $problems));
            }

            AdminAuditLog::query()->create([
                'user_id' => $actor?->id, 'actor_email' => $actor?->email, 'action' => 'catalog_change',
                'validation_result' => ['entity' => $entity, 'operation' => $operation, 'item' => $label],
            ]);

            return $model;
        });
    }
}
