<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Whodoku\Domain\Puzzle\Generation\AvatarFactory;
use Whodoku\Models\Player;

/** The playable character: a detective with a name and a portrait. */
class DetectiveProfile
{
    /** @var list<string> */
    private const array EXTRAS = ['none', 'glasses', 'beard', 'mustache', 'earrings', 'freckles'];

    /**
     * @param  array{name: string, gender?: string, skin?: int, hair?: int, style?: int, shirt?: int, extra?: string}  $data
     */
    public function save(Player $player, array $data): Player
    {
        $name = preg_replace('/\s+/u', ' ', trim($data['name']));
        if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} \-]{1,22}[\p{L}\p{N}]$/u', (string) $name)) {
            throw ValidationException::withMessages(['name' => 'Имя детектива — от 3 до 24 символов: буквы, цифры, пробел и дефис.']);
        }
        if (!ForbiddenNames::isAllowed($name)) {
            throw ValidationException::withMessages(['name' => 'Это имя нельзя использовать. Выберите другое.']);
        }

        $key = mb_strtolower($name);
        $taken = Player::query()->where('detective_key', $key)->whereKeyNot($player->id)->exists();
        $message = ['name' => 'Это имя уже занято другим детективом.'];
        if ($taken) {
            throw ValidationException::withMessages($message);
        }

        try {
            $player->update(['detective_name' => $name, 'detective_key' => $key, 'avatar' => $this->recipe($data)]);
        } catch (UniqueConstraintViolationException) {
            // Two players took the same name at the same moment: the database let only one.
            throw ValidationException::withMessages($message);
        }

        return $player;
    }

    /** @param array<string, mixed> $data */
    private function recipe(array $data): string
    {
        $male = ($data['gender'] ?? 'f') === 'm';
        $extra = in_array($data['extra'] ?? 'none', self::EXTRAS, true) ? ($data['extra'] ?? 'none') : 'none';

        return sprintf(
            'v1;g=%s;skin=%d;hair=%d;style=%d;shirt=%d;extra=%s',
            $male ? 'm' : 'f',
            $this->within($data['skin'] ?? 0, AvatarFactory::SKINS),
            $this->within($data['hair'] ?? 0, AvatarFactory::HAIRS),
            $this->within($data['style'] ?? 0, 7),
            $this->within($data['shirt'] ?? 0, AvatarFactory::SHIRTS),
            $extra,
        );
    }

    private function within(mixed $value, int $size): int
    {
        return max(0, min($size - 1, (int) $value));
    }
}
