<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\MapObject;
use Whodoku\Domain\Puzzle\Room;
use Whodoku\Domain\Puzzle\Token;

class CaseTextFactory
{
    private readonly ObjectCatalog $catalog;

    public function __construct(private readonly AvatarFactory $avatars = new AvatarFactory, ?ObjectCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? ObjectCatalog::defaults();
    }

    /**
     * Guests with memorable names; every code is also listed in {@see Gender} when the guest is a man.
     * The pool covers many initials, because the map shows a guest by the first letter of the name.
     */
    private const array POOL = [
        ['code' => 'aglaya', 'name' => 'Аглая Вишневская'],
        ['code' => 'arseniy', 'name' => 'Арсений Громов'],
        ['code' => 'bogdana', 'name' => 'Богдана Северская'],
        ['code' => 'boris', 'name' => 'Борис Лисов'],
        ['code' => 'vera', 'name' => 'Вера Соколова'],
        ['code' => 'vilgelm', 'name' => 'Вильгельм Штерн'],
        ['code' => 'gedeon', 'name' => 'Гедеон Воронцов'],
        ['code' => 'gleb', 'name' => 'Глеб Морозов'],
        ['code' => 'dina', 'name' => 'Дина Орлова'],
        ['code' => 'dominika', 'name' => 'Доминика Ферро'],
        ['code' => 'egor', 'name' => 'Егор Романов'],
        ['code' => 'evgeniya', 'name' => 'Евгения Белозёрова'],
        ['code' => 'zhanna', 'name' => 'Жанна Кольцова'],
        ['code' => 'zoya', 'name' => 'Зоя Киселёва'],
        ['code' => 'zakhar', 'name' => 'Захар Гордеев'],
        ['code' => 'igor', 'name' => 'Игорь Волков'],
        ['code' => 'ippolit', 'name' => 'Ипполит Верховский'],
        ['code' => 'kira', 'name' => 'Кира Ласточкина'],
        ['code' => 'klavdiy', 'name' => 'Клавдий Рябинин'],
        ['code' => 'lena', 'name' => 'Лена Журавлёва'],
        ['code' => 'leopold', 'name' => 'Леопольд Чижов'],
        ['code' => 'maksim', 'name' => 'Максим Беляев'],
        ['code' => 'miroslava', 'name' => 'Мирослава Тихонова'],
        ['code' => 'nazar', 'name' => 'Назар Скворцов'],
        ['code' => 'nonna', 'name' => 'Нонна Вьюгина'],
        ['code' => 'oskar', 'name' => 'Оскар Бриллиантов'],
        ['code' => 'olga', 'name' => 'Ольга Звонарёва'],
        ['code' => 'platon', 'name' => 'Платон Кречетов'],
        ['code' => 'polina', 'name' => 'Полина Сумарокова'],
        ['code' => 'rodion', 'name' => 'Родион Заславский'],
        ['code' => 'raisa', 'name' => 'Раиса Мещерская'],
        ['code' => 'serafima', 'name' => 'Серафима Луговая'],
        ['code' => 'stanislav', 'name' => 'Станислав Ястребов'],
        ['code' => 'timur', 'name' => 'Тимур Раевский'],
        ['code' => 'tamara', 'name' => 'Тамара Бестужева'],
        ['code' => 'fedor', 'name' => 'Фёдор Зверев'],
        ['code' => 'faina', 'name' => 'Фаина Левицкая'],
        ['code' => 'emiliya', 'name' => 'Эмилия Крылова'],
        ['code' => 'eduard', 'name' => 'Эдуард Бахметьев'],
        ['code' => 'yulian', 'name' => 'Юлиан Ковалевский'],
        ['code' => 'yaroslava', 'name' => 'Ярослава Дроздова'],
    ];

    /**
     * Picks guests with different initials, so no two figures look alike on the map.
     *
     * @return list<array{code: string, name: string, avatar: string}>
     */
    public function characters(SeededRandom $random, int $count = 4): array
    {
        $picked = [];
        $initials = [];
        foreach ($random->shuffle(self::POOL) as $character) {
            $initial = mb_substr($character['name'], 0, 1);
            if (isset($initials[$initial])) {
                continue;
            }
            $initials[$initial] = true;
            $picked[] = [...$character, 'avatar' => $this->avatars->recipe($random, Gender::isMale($character['code']))];
            if (count($picked) === $count) {
                break;
            }
        }

        return $picked;
    }

    /** @return list<string> */
    public function roomNames(SeededRandom $random, int $count = 4): array
    {
        return array_slice($random->shuffle($this->catalog->roomNames()), 0, $count);
    }

    /**
     * Statements are spoken, not stated: «Я сидел на табурете», «Я разглядывал персидский ковёр», «Я стоял в холле».
     * The wording is picked from a few variants by the guest and the thing named, so the same case always reads
     * the same. A name the declension cannot handle falls back to the quoted form («на предмете “…”»).
     */
    public function roomStatement(Token $token, Room $room, ?MapObject $own = null): string
    {
        $in = RussianCase::phrase($room->name, RussianCase::PREPOSITIONAL);
        if ($in === null) {
            return $this->say($token, "Я {$this->past($token, 'находилась', 'находился')} в комнате “{$room->name}”");
        }

        return $this->say($token, 'Я '.$this->variant($token, $room->name, [
            fn () => $this->standing($token, $own),
            fn () => $this->pose($own) === 'stand' ? $this->past($token, 'находилась', 'находился') : $this->standing($token, $own),
            fn () => $this->pose($own) === 'stand' ? $this->past($token, 'ждала', 'ждал') : $this->standing($token, $own),
            fn () => $this->pose($own) === 'stand' ? $this->past($token, 'задержалась', 'задержался') : $this->standing($token, $own),
        ])." в $in");
    }

    public function aloneStatement(Token $token, Room $room, ?MapObject $own = null): string
    {
        $in = RussianCase::phrase($room->name, RussianCase::PREPOSITIONAL);
        if ($in === null) {
            return $this->say($token, "Я {$this->past($token, 'была', 'был')} {$this->past($token, 'одна', 'один')} в комнате “{$room->name}”");
        }

        return $this->say($token, 'Я '.$this->variant($token, $room->name, [
            fn () => $this->pose($own) === 'stand' ? $this->past($token, 'была', 'был') : $this->standing($token, $own),
            fn () => $this->pose($own) === 'stand' ? $this->past($token, 'оставалась', 'оставался') : $this->standing($token, $own),
        ]).' '.$this->past($token, 'одна', 'один')." в $in");
    }

    public function cornerStatement(Token $token, Room $room, ?MapObject $own = null): string
    {
        $of = RussianCase::phrase($room->name, RussianCase::GENITIVE);
        if ($of === null) {
            return $this->say($token, "Я {$this->past($token, 'находилась', 'находился')} в углу комнаты “{$room->name}”");
        }

        return $this->say($token, 'Я '.$this->variant($token, $room->name, [
            fn () => $this->standing($token, $own),
            fn () => $this->pose($own) === 'stand' ? $this->past($token, 'притаилась', 'притаился') : $this->standing($token, $own),
        ])." в углу $of");
    }

    /** «Рядом»: the guest stood on a cell next to the object, in the same room (not on it). */
    public function adjacentStatement(Token $token, MapObject $object, ?MapObject $own = null): string
    {
        $with = RussianCase::phrase($object->name, RussianCase::INSTRUMENTAL);
        $acc = RussianCase::phrase($object->name, RussianCase::ACCUSATIVE);
        $gen = RussianCase::phrase($object->name, RussianCase::GENITIVE);
        if ($with === null || $acc === null || $gen === null) {
            return $this->say($token, "Я {$this->past($token, 'стояла', 'стоял')} рядом с предметом “{$object->name}”");
        }

        return $this->say($token, 'Я '.$this->variant($token, $object->code, [
            fn () => $this->standing($token, $own)." рядом с $with",
            fn () => $this->past($token, 'разглядывала', 'разглядывал')." $acc",
            fn () => $this->past($token, 'любовалась', 'любовался')." $with",
            fn () => ($this->pose($own) === 'stand' ? $this->past($token, 'задержалась', 'задержался') : $this->standing($token, $own))." у $gen",
        ]));
    }

    /** @param 'north'|'south'|'west'|'east' $direction */
    public function directionStatement(Token $token, MapObject $object, string $direction, ?MapObject $own = null): string
    {
        $where = ['north' => 'выше', 'south' => 'ниже', 'west' => 'левее', 'east' => 'правее'][$direction];
        $of = RussianCase::phrase($object->name, RussianCase::GENITIVE);
        if ($of === null) {
            return $this->say($token, "Я {$this->past($token, 'стояла', 'стоял')} {$where} предмета “{$object->name}”");
        }

        return $this->say($token, 'Я '.$this->variant($token, $object->code.$direction, [
            fn () => $this->standing($token, $own),
            fn () => $this->pose($own) === 'stand' ? $this->past($token, 'оказалась', 'оказался') : $this->standing($token, $own),
        ])." $where $of");
    }

    public function windowStatement(Token $token, ?Room $room = null, ?MapObject $own = null): string
    {
        $in = $room === null ? null : RussianCase::phrase($room->name, RussianCase::PREPOSITIONAL);
        if ($room !== null && $in === null) {
            return $this->say($token, "Я {$this->past($token, 'стояла', 'стоял')} возле окна в комнате “{$room->name}”");
        }
        $where = $in === null ? '' : " в $in";

        return $this->say($token, 'Я '.$this->variant($token, 'window'.($room->code ?? ''), [
            fn () => $this->standing($token, $own)." возле окна$where",
            fn () => $this->past($token, 'любовалась', 'любовался')." видом из окна$where",
            fn () => $this->past($token, 'смотрела', 'смотрел')." в окно$where",
        ]));
    }

    /** «На предмете»: the verb follows the furniture, a person sits on a stool, lies on a bed, stands on a rug. */
    public function onObjectStatement(Token $token, MapObject $object): string
    {
        $on = RussianCase::phrase($object->name, RussianCase::PREPOSITIONAL);
        if ($on === null) {
            return $this->say($token, "Я {$this->past($token, 'стояла', 'стоял')} на предмете “{$object->name}”");
        }

        $verb = $this->pose($object) === 'stand' ? $this->past($token, 'стояла', 'стоял') : $this->standing($token, $object);

        return $this->say($token, "Я $verb на $on");
    }

    /** A condition of the whole case, from the investigator's notes: every room had somebody. */
    public function everyRoomCondition(): string
    {
        return 'Из записей следователя: в тот вечер в каждой комнате был хотя бы один гость.';
    }

    /** «В комнате … был ровно один гость»; a name the declension cannot handle is quoted. */
    public function roomCountCondition(Room $room, int $count): string
    {
        $in = RussianCase::phrase($room->name, RussianCase::PREPOSITIONAL) ?? "комнате “{$room->name}”";
        $guests = match (true) {
            $count === 1 => 'был ровно один гость',
            $count >= 2 && $count <= 4 => "было ровно {$this->number($count)} гостя",
            default => "было ровно $count гостей",
        };

        return "Из записей следователя: в $in $guests.";
    }

    /** «Ни в одной комнате не было больше двух гостей». */
    public function roomLimitCondition(int $max): string
    {
        $limit = match ($max) {
            1 => 'больше одного гостя',
            default => "больше {$this->genitive($max)} гостей",
        };

        return "Из записей следователя: ни в одной комнате не было $limit.";
    }

    private function number(int $count): string
    {
        return [2 => 'два', 3 => 'три', 4 => 'четыре'][$count] ?? (string) $count;
    }

    private function genitive(int $count): string
    {
        return [2 => 'двух', 3 => 'трёх', 4 => 'четырёх'][$count] ?? (string) $count;
    }

    /**
     * The verb for «stood» that fits what the guest does on the furniture they use: a person on a stool sits, on a
     * bed lies, on a rug or among other things just stands. A statement that names only a room, a corner or a window
     * must not make a seated guest «stand» (the pose is part of the text, not only of the picture).
     */
    private function standing(Token $token, ?MapObject $own): string
    {
        return match ($this->pose($own)) {
            'sit' => $this->variant($token, 'sit'.($own->code ?? ''), [fn () => $this->past($token, 'сидела', 'сидел'), fn () => $this->past($token, 'устроилась', 'устроился')]),
            'lie' => $this->variant($token, 'lie'.($own->code ?? ''), [fn () => $this->past($token, 'лежала', 'лежал'), fn () => $this->past($token, 'отдыхала', 'отдыхал')]),
            default => $this->past($token, 'стояла', 'стоял'),
        };
    }

    /** @return 'sit'|'lie'|'stand' */
    private function pose(?MapObject $object): string
    {
        return match ($object?->type) {
            'armchair', 'sofa', 'stool', 'chair', 'bench', 'pouf' => 'sit',
            'bed' => 'lie',
            default => 'stand',
        };
    }

    private function say(Token $token, string $statement): string
    {
        return "{$token->name} утверждает: «{$statement}».";
    }

    /** @param list<callable(): string> $options deterministic pick by the guest and the thing named */
    private function variant(Token $token, string $salt, array $options): string
    {
        return $options[crc32($token->code.'|'.$salt) % count($options)]();
    }

    private function past(Token $token, string $feminine, string $masculine): string
    {
        return Gender::past($token, $feminine, $masculine);
    }
}
