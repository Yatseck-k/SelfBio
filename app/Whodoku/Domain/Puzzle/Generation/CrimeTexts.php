<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Enums\PuzzleType;
use Whodoku\Domain\Puzzle\Token;

/** Original Russian titles, stories, hints and endings for every crime type. */
class CrimeTexts
{
    public function title(PuzzleType $type, SeededRandom $random): string
    {
        return $random->pick(match ($type) {
            PuzzleType::Theft => ['Кража в старом особняке', 'Пустая витрина', 'Дело о пропавшей шкатулке', 'Ночная кража'],
            PuzzleType::Poisoning => ['Отравленный бокал', 'Горький вечер', 'Дело о яде в графине', 'Последний тост'],
            PuzzleType::Arson => ['Пожар в особняке', 'Дым над крышей', 'Дело о поджоге', 'Огонь в зимнем саду'],
            PuzzleType::Forgery => ['Подмена в галерее', 'Фальшивый портрет', 'Дело о подложной картине', 'Копия вместо оригинала'],
            default => ['Тайна вечернего приёма', 'Шёпот старого особняка', 'Дело о погасшей лампе', 'Последний гость'],
        });
    }

    /** The teaser on the card of the catalog: one line that makes the case worth opening. */
    public function description(PuzzleType $type, string $title, SeededRandom $random): string
    {
        return $random->pick(match ($type) {
            PuzzleType::Theft => [
                'Замок цел, витрина нетронута, а вещь исчезла. Вор остался в доме.',
                'Пропажу заметили слишком поздно — но никто не успел уйти.',
                'Все были на виду. Кроме одного человека и одной вещи.',
            ],
            PuzzleType::Poisoning => [
                'Один тост, один бокал — и один человек, который знал, что в нём.',
                'Горькое вино разливали при всех. Кто подмешал яд?',
                'Бокал подали с улыбкой. Улыбавшийся всё ещё в комнате.',
            ],
            PuzzleType::Arson => [
                'Огонь вспыхнул тихо и сразу в нужном месте. Случайностей не бывает.',
                'Пока дом горел, кто-то спокойно смотрел на пламя. Узнайте, кто.',
                'Поджигатель не бежал: он знал, что его не ищут.',
            ],
            PuzzleType::Forgery => [
                'Копия висит на месте оригинала, и никто не заметил разницы. Почти никто.',
                'Подмена сделана мастерски. Подпись автора — не на холсте.',
                'Оригинал исчез под носом у всех. Один из гостей знает куда.',
            ],
            default => [
                'Дверь была заперта изнутри, а в доме — только свои.',
                'Утро в особняке началось с тишины. Убийца всё ещё завтракает вместе со всеми.',
                'Никто ничего не видел. Но кто-то лжёт.',
            ],
        });
    }

    /**
     * The introduction of the case: where we are, what happened and why it is strange. It does not repeat the task,
     * the rules or the list of witnesses: the page shows them next to it.
     *
     * @param  list<Token>  $tokens
     * @param  array{name: string, role: string, male: bool, avatar: string, statement: string}  $victim
     */
    public function story(CrimePlan $plan, array $tokens, SeededRandom $random, array $victim): string
    {
        $name = $victim['name'];
        $male = $victim['male'];
        $guests = count($tokens);
        $who = "{$victim['role']} {$name}";
        $mid = mb_strtolower($victim['role']).' '.$name;

        return $random->pick(match ($plan->type) {
            PuzzleType::Theft => [
                sprintf('В особняке %s. Витрина цела, замок не тронут, а гости в тот вечер были только приглашённые. %s узнал%s об этом последним.', $random->pick(['пропало фамильное колье', 'пропала шкатулка с письмами', 'пропали коллекционные часы', 'пропала старинная карта']), $who, $male ? '' : 'а'),
                sprintf('Дверь никто не взламывал. Но утром %s. %s не находит себе места: в доме никто не может спать спокойно.', $random->pick(['колье из семейной шкатулки пропало', 'исчезли коллекционные часы', 'на месте старинной карты осталась лишь рамка']), $who),
            ],
            PuzzleType::Poisoning => [
                sprintf('Все подняли бокалы за хозяйку вечера. Через минуту %s %s и опустил%s на стул. Доктор лишь пожал плечами: яд. Бокал подали при всех, и кто-то из них знал, что в нём.', $name, $male ? 'побледнел' : 'побледнела', $male ? 'ся' : 'ась'),
                sprintf('Вино было выбрано заранее, бокалы расставлены у всех на виду. И всё же %s %s после первого глотка. Значит, яд появился позже, уже в руках у кого-то из присутствующих.', $name, $male ? 'отравился' : 'отравилась'),
            ],
            PuzzleType::Arson => [
                sprintf('Среди ночи в доме запахло дымом. Пламя занялось тихо, без единого крика, — так горит только то, что подожгли. %s не сразу поверил%s: огонь начался там, где никого не должно было быть.', $who, $male ? '' : 'а'),
                sprintf('Дом горел недолго — успели вовремя. Но пожарные уверены: это не проводка и не свеча. %s не находит слов. Теперь все гости твердят, что в ту ночь не выходили из комнат.', $who),
            ],
            PuzzleType::Forgery => [
                sprintf('%s давно подозревал%s, что %s в доме не тот, что висел раньше. Эксперт подтвердил: подделка. Настоящий оригинал исчез, а подмену сделали ловко — под самым носом у гостей.', $who, $male ? '' : 'а', $random->pick(['портрет основателя дома', 'пейзаж старого мастера', 'семейный герб', 'натюрморт из столовой'])),
                sprintf('Рама на месте, подпись на месте, даже трещинка в углу. Но %s уверен%s: это копия. Оригинал кто-то вынес раньше, чем хозяин успел застраховать его.', $mid, $male ? '' : 'а'),
            ],
            default => [
                sprintf('Гроза отрезала особняк от города, а в полночь погас свет. Когда лампы вспыхнули снова, %s был%s %s. Двери заперты изнутри — убийца всё ещё среди своих.', $name, $male ? '' : 'а', $male ? 'найден мёртвым' : 'найдена мёртвой'),
                sprintf('Вечер в особняке задумывался как праздник. Он закончился тишиной: %s %s мёртв%s, а каждый из гостей уверяет, что в ту минуту был совсем в другой комнате.', $name, $male ? 'найден' : 'найдена', $male ? 'ым' : 'ой'),
            ],
        });
    }

    /** @return list<string> */
    public function hints(CrimePlan $plan): array
    {
        return [
            'Сначала расставьте всех гостей: когда строки и столбцы заняты, остаётся одна пустая клетка.',
            "{$plan->type->culpritNominative()} — тот, кто оказался в одной комнате с пустой клеткой.",
            'Найдите комнату пустой клетки: в ней стоит ровно один гость.',
        ];
    }

    /** @return array<string, mixed> */
    public function ending(CrimePlan $plan): array
    {
        $name = $plan->culprit->name;
        $room = $plan->room->name ?? null;

        $in = $room === null ? 'доме' : RussianCase::phrase($room, RussianCase::PREPOSITIONAL);
        $reason = ($in === null ? "В комнате “{$room}”" : "В {$in}").', где всё случилось, находил'.Gender::past($plan->culprit, 'ась', 'ся')." только {$name}.";

        [$icon, $label, $note] = match ($plan->type) {
            PuzzleType::Theft => ['💎', 'Украденная вещь', 'Пропажа нашлась в пустой клетке.'],
            PuzzleType::Poisoning => ['🍷', 'Отравленный бокал', 'Бокал с ядом остался в пустой клетке.'],
            PuzzleType::Arson => ['🔥', 'Очаг пожара', 'Огонь начался в пустой клетке.'],
            PuzzleType::Forgery => ['🖼️', 'Подлинник', 'Настоящая картина обнаружилась в пустой клетке.'],
            default => ['⚰️', 'Тело жертвы', 'Тело нашли в пустой клетке.'],
        };

        return [
            'title' => 'Дело раскрыто',
            'text' => "{$plan->type->culpritNominative()} — {$name}. $note $reason",
            'culprit' => $name,
            'scene' => ['cell' => [$plan->scene->row, $plan->scene->col], 'icon' => $icon, 'label' => $label],
        ];
    }
}
