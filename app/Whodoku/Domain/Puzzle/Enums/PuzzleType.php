<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Enums;

enum PuzzleType: string
{
    case Murder = 'murder';
    case Theft = 'theft';
    case Poisoning = 'poisoning';
    case Arson = 'arson';
    case Forgery = 'forgery';
    // Final conditions from the specification that the generator does not produce yet.
    case MissingItem = 'missing_item';
    case Location = 'location';

    /** @return list<self> crime types the procedural generator can build */
    public static function generated(): array
    {
        return [self::Murder, self::Theft, self::Poisoning, self::Arson, self::Forgery];
    }

    public function label(): string
    {
        return match ($this) {
            self::Murder => 'Убийство',
            self::Theft => 'Кража',
            self::Poisoning => 'Отравление',
            self::Arson => 'Поджог',
            self::Forgery => 'Подлог',
            self::MissingItem => 'Пропажа предмета',
            self::Location => 'Поиск места',
        };
    }

    /** Who the player has to name, in the accusative case ("назовите …"). */
    public function culpritAccusative(): string
    {
        return match ($this) {
            self::Murder => 'убийцу',
            self::Theft => 'вора',
            self::Poisoning => 'отравителя',
            self::Arson => 'поджигателя',
            self::Forgery => 'автора подлога',
            default => 'виновного',
        };
    }

    /** Who the culprit is, in the nominative case ("… — Имя"). */
    public function culpritNominative(): string
    {
        return match ($this) {
            self::Murder => 'Убийца',
            self::Theft => 'Вор',
            self::Poisoning => 'Отравитель',
            self::Arson => 'Поджигатель',
            self::Forgery => 'Автор подлога',
            default => 'Виновный',
        };
    }
}
