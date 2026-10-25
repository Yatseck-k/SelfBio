<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

/**
 * A player's report about a case. It stays in the table until an admin has looked at it: `new` → `in_progress`
 * → `resolved` (the case was fixed) or `rejected` (nothing wrong).
 *
 * @property int $id
 * @property int|null $puzzle_id
 * @property string $puzzle_slug
 * @property int $puzzle_revision
 * @property int|null $player_id
 * @property string $category
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property string $status
 * @property string|null $resolution
 */
#[Fillable([
    'puzzle_id', 'puzzle_slug', 'puzzle_revision', 'player_id', 'category', 'message', 'context', 'reporter_hash',
    'status', 'resolution', 'resolved_by', 'resolved_at',
])]
class Incident extends Model
{
    use AsSource;

    /** Named outright: the menu counts new reports on screens of the other site too. */
    protected $connection = 'whodoku';

    public const NEW = 'new';

    public const IN_PROGRESS = 'in_progress';

    public const RESOLVED = 'resolved';

    public const REJECTED = 'rejected';

    /** What the player can say is wrong: the keys are stored, the words are shown. */
    public const CATEGORIES = [
        'unsolvable' => 'Дело нельзя решить',
        'unclear' => 'Непонятное показание',
        'display' => 'Ошибка на экране',
        'other' => 'Другое',
    ];

    public const STATUSES = [
        self::NEW => 'новое',
        self::IN_PROGRESS => 'в работе',
        self::RESOLVED => 'решено',
        self::REJECTED => 'отклонено',
    ];

    /** @return BelongsTo<Puzzle, $this> */
    public function puzzle(): BelongsTo
    {
        return $this->belongsTo(Puzzle::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'puzzle_revision' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }
}
