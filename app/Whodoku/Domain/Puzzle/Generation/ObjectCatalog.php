<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

/**
 * The furniture vocabulary of generated maps. Every kind is either usable (a figure may stand or sit on it:
 * seats, beds, rugs) or unusable (it fills the cell: shelves, tables, plants, a piano). The kind code is stored
 * as the object type, so the public map and the editor can show the rule without guessing it from names.
 *
 * The catalog is a plain value: the built-in one (`defaults()`) or one assembled from the lists an administrator
 * edits, so the generator never depends on the database.
 */
final readonly class ObjectCatalog
{
    /** @var array<string, array{icon: string, label: string, occupiable: bool, names: list<string>, on?: string}> */
    private const array DEFAULT_KINDS = [
        'armchair' => ['icon' => '💺', 'on' => 'на кресле', 'label' => 'Кресло', 'occupiable' => true, 'names' => ['старинное кресло', 'мягкое кресло']],
        'sofa' => ['icon' => '🛋️', 'on' => 'на диване', 'label' => 'Диван', 'occupiable' => true, 'names' => ['бархатный диван', 'кожаный диван']],
        'stool' => ['icon' => '🪑', 'on' => 'на табурете', 'label' => 'Табурет', 'occupiable' => true, 'names' => ['высокий табурет', 'барный табурет']],
        'chair' => ['icon' => '🪑', 'on' => 'на стуле', 'label' => 'Стул', 'occupiable' => true, 'names' => ['венский стул']],
        'bench' => ['icon' => '🛋️', 'on' => 'на скамье', 'label' => 'Скамья', 'occupiable' => true, 'names' => ['садовая скамья']],
        'bed' => ['icon' => '🛏️', 'on' => 'на кровати', 'label' => 'Кровать', 'occupiable' => true, 'names' => ['кровать с балдахином']],
        'rug' => ['icon' => '🟫', 'on' => 'на ковре', 'label' => 'Ковёр', 'occupiable' => true, 'names' => ['персидский ковёр']],
        'pouf' => ['icon' => '⭕', 'on' => 'на пуфике', 'label' => 'Пуфик', 'occupiable' => true, 'names' => ['бархатный пуфик']],
        'shelf' => ['icon' => '🗄️', 'label' => 'Шкаф', 'occupiable' => false, 'names' => ['книжный шкаф', 'стеллаж', 'витрина']],
        'table' => ['icon' => '🍽️', 'label' => 'Стол', 'occupiable' => false, 'names' => ['письменный стол', 'журнальный столик']],
        'piano' => ['icon' => '🎹', 'label' => 'Рояль', 'occupiable' => false, 'names' => ['камерный рояль']],
        'plant' => ['icon' => '🌿', 'label' => 'Растение', 'occupiable' => false, 'names' => ['комнатное растение', 'кадка с пальмой']],
        'cabinet' => ['icon' => '🗃️', 'label' => 'Тумба', 'occupiable' => false, 'names' => ['комод', 'тумба с лампой']],
        'chest' => ['icon' => '🧰', 'label' => 'Сундук', 'occupiable' => false, 'names' => ['старинный сундук']],
        'screen' => ['icon' => '🚪', 'label' => 'Ширма', 'occupiable' => false, 'names' => ['расписная ширма']],
        'statue' => ['icon' => '🗿', 'label' => 'Статуя', 'occupiable' => false, 'names' => ['мраморный бюст']],
        'fireplace' => ['icon' => '🔥', 'label' => 'Камин', 'occupiable' => false, 'names' => ['каменный камин']],
    ];

    /**
     * Furniture that belongs in each room. The generator prefers these kinds for objects inside the room,
     * so a bedroom gets beds and a library gets shelves, but it still sprinkles in other items.
     *
     * @var array<string, list<string>>
     */
    private const array DEFAULT_THEMES = [
        'библиотека' => ['shelf', 'armchair', 'table', 'chair', 'rug', 'plant'],
        'зимний сад' => ['plant', 'bench', 'chair', 'stool'],
        'оранжерея' => ['plant', 'bench', 'stool'],
        'музыкальная гостиная' => ['piano', 'sofa', 'armchair', 'rug', 'fireplace', 'pouf'],
        'кабинет' => ['table', 'shelf', 'chair', 'armchair', 'cabinet'],
        'галерея' => ['statue', 'bench', 'plant', 'screen', 'pouf'],
        'обеденный зал' => ['table', 'chair', 'cabinet', 'stool'],
        'мастерская' => ['table', 'chest', 'stool', 'shelf', 'cabinet'],
        'холл' => ['bench', 'plant', 'rug', 'statue', 'cabinet'],
        'веранда' => ['bench', 'plant', 'chair', 'stool', 'table'],
        'кладовая' => ['chest', 'shelf', 'cabinet', 'stool'],
        'бильярдная' => ['table', 'stool', 'armchair', 'fireplace', 'sofa'],
        'спальня' => ['bed', 'cabinet', 'pouf', 'rug', 'armchair', 'screen'],
        'кухня' => ['table', 'stool', 'cabinet', 'chair', 'shelf'],
    ];

    /**
     * @param  array<string, array{icon?: string, label: string, occupiable: bool, names: list<string>, on?: string}>  $kinds
     * @param  array<string, list<string>>  $themes  room name (lower case) => kind codes that fit the room
     */
    public function __construct(private array $kinds, private array $themes) {}

    public static function defaults(): self
    {
        return new self(self::DEFAULT_KINDS, self::DEFAULT_THEMES);
    }

    /** @return list<string> */
    public function roomNames(): array
    {
        return array_keys($this->themes);
    }

    /** @return list<string> kind codes that fit the room, empty for an unknown room name */
    public function preferredKinds(string $roomName): array
    {
        return $this->themes[mb_strtolower($roomName)] ?? [];
    }

    /** @return array<string, array{icon?: string, label: string, occupiable: bool, names: list<string>, on?: string}> */
    public function kinds(): array
    {
        return $this->kinds;
    }

    /** @return array<string, list<string>> */
    public function themes(): array
    {
        return $this->themes;
    }

    /** "на кресле", "на диване": where a figure stands on a usable kind; null for unusable kinds. */
    public function standingPhrase(string $kind): ?string
    {
        return $this->kinds[$kind]['on'] ?? null;
    }

    public function isOccupiable(string $kind): ?bool
    {
        return $this->kinds[$kind]['occupiable'] ?? null;
    }

    /** @return list<array{kind: string, name: string}> */
    public function usableEntries(): array
    {
        return $this->entries(true);
    }

    /** @return list<array{kind: string, name: string}> */
    public function unusableEntries(): array
    {
        return $this->entries(false);
    }

    /** @return list<array{kind: string, name: string}> */
    public function allEntries(): array
    {
        return [...$this->entries(true), ...$this->entries(false)];
    }

    /**
     * Kinds as the map editor shows them.
     *
     * @return list<array{code: string, label: string, name: string, occupiable: bool}>
     */
    public function forEditor(): array
    {
        $kinds = [];
        foreach ($this->kinds as $code => $kind) {
            $kinds[] = ['code' => $code, 'label' => $kind['label'], 'name' => $kind['names'][0] ?? $kind['label'], 'occupiable' => $kind['occupiable']];
        }

        return $kinds;
    }

    /** @return list<array{kind: string, name: string}> */
    private function entries(bool $occupiable): array
    {
        $entries = [];
        foreach ($this->kinds as $code => $kind) {
            if ($kind['occupiable'] !== $occupiable) {
                continue;
            }
            foreach ($kind['names'] as $name) {
                $entries[] = ['kind' => $code, 'name' => $name];
            }
        }

        return $entries;
    }
}
