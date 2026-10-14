<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use InvalidArgumentException;

class SeededRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & 0x7FFFFFFF;
    }

    public function nextInt(int $maximumExclusive): int
    {
        if ($maximumExclusive < 1) {
            throw new InvalidArgumentException('Maximum must be positive.');
        }

        $this->state = ($this->state * 1_103_515_245 + 12_345) & 0x7FFFFFFF;

        return $this->state % $maximumExclusive;
    }

    /** @template T
     * @param  list<T>  $values
     * @return T
     */
    public function pick(array $values): mixed
    {
        if ($values === []) {
            throw new InvalidArgumentException('Cannot pick from an empty list.');
        }

        return $values[$this->nextInt(count($values))];
    }

    /** @template T
     * @param  list<T>  $values
     * @return list<T>
     */
    public function shuffle(array $values): array
    {
        for ($index = count($values) - 1; $index > 0; $index--) {
            $swap = $this->nextInt($index + 1);
            [$values[$index], $values[$swap]] = [$values[$swap], $values[$index]];
        }

        return $values;
    }
}
