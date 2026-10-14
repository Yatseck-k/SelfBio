<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Enums\PuzzleType;

/**
 * The injured party of a case. The card holds a veiled remark of the victim about where the culprit was, like the
 * card of the victim in a paper investigation.
 */
class VictimFactory
{
    private const array POOL = [
        ['Леонтий Арнаутов', true], ['Аристарх Бельский', true], ['Модест Ферапонтов', true], ['Евграф Седов', true],
        ['Генриетта Мальцева', false], ['Матильда Крымова', false], ['Агата Бородина', false], ['Лукерья Островская', false],
    ];

    public function __construct(private readonly AvatarFactory $avatars = new AvatarFactory) {}

    /** @return array{name: string, role: string, male: bool, avatar: string, statement: string} */
    public function create(CrimePlan $plan, SeededRandom $random): array
    {
        [$name, $male] = $random->pick(self::POOL);

        return [
            'name' => $name,
            'role' => $this->role($plan->type, $male),
            'male' => $male,
            'avatar' => $this->avatars->recipe($random, $male),
            'statement' => $this->statement($plan, $male, $random),
        ];
    }

    private function role(PuzzleType $type, bool $male): string
    {
        return match ($type) {
            PuzzleType::Theft => $male ? 'Потерпевший' : 'Потерпевшая',
            PuzzleType::Poisoning => $male ? 'Отравленный' : 'Отравленная',
            PuzzleType::Arson => $male ? 'Хозяин дома' : 'Хозяйка дома',
            PuzzleType::Forgery => $male ? 'Коллекционер' : 'Коллекционерка',
            default => 'Жертва',
        };
    }

    /**
     * The victim never spells the rule out: a hint in their own words that the culprit was close to the scene,
     * one of several per crime so that the cards do not repeat.
     */
    private function statement(CrimePlan $plan, bool $male, SeededRandom $random): string
    {
        $a = fn (string $masculine, string $feminine): string => $male ? $masculine : $feminine;

        return $random->pick(match ($plan->type) {
            PuzzleType::Theft => [
                'Пропажу я заметил'.$a('', 'а').' сразу: воздух в комнате ещё хранил чужое присутствие.',
                'Вор не мог уйти далеко — стены этой комнаты видели, кто здесь был.',
                'Шкатулка лежала на виду. Тому, кто её взял, не пришлось выходить за порог.',
            ],
            PuzzleType::Poisoning => [
                'Бокал мне подали из чьих-то рук — и тот человек не отходил от меня дальше этой комнаты.',
                'Горечь я почувствовал'.$a('', 'а').' поздно. Яд подсыпали там, где никто посторонний не бывал.',
                'Последний тост пили без лишних глаз: за стеной комнаты нас было двое.',
            ],
            PuzzleType::Arson => [
                'Огонь вспыхнул в тишине. Тот, кто его поджёг, всё ещё стоял под тем же потолком.',
                'Пламя я увидел'.$a('', 'а').' раньше других — и ещё чьи-то глаза в отблесках, в той же комнате.',
                'Дым ел глаза, но я успел'.$a('', 'а').' заметить: никто не покидал комнату, где занялся огонь.',
            ],
            PuzzleType::Forgery => [
                'Подлинник висел здесь, пока кто-то в этой комнате не оказался слишком близко к раме.',
                'Холст подменили тихо. Тот, кто это сделал, не выходил из комнаты, где висела картина.',
                'Копию можно было вынести куда угодно — но человек, которого я подозреваю, не покидал зал.',
            ],
            default => [
                'Закрывая глаза, я видел'.$a('', 'а').' только одно лицо — того, кто был со мной в комнате.',
                'Не ищите виноватого далеко: тот, кто это сделал, не успел выйти из комнаты.',
                'Я так и не понял'.$a('', 'а').', кто стоял за спиной, — но двери были закрыты, а гость рядом был один.',
            ],
        });
    }
}
