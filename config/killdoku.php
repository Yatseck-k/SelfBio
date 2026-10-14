<?php

return [
    /*
    | The release of cases (admin screen «Выпуск дел»): the starting set, the guest cases, the daily batch
    | and the reserve that is generated and checked ahead of time.
    */
    'release' => [
        // Cases are released at midnight of this timezone.
        'timezone' => env('KILLDOKU_RELEASE_TIMEZONE', 'Europe/Moscow'),

        // The starting set: how many cases of each difficulty (level 5 is left to the daily cases).
        'starter' => [1 => 30, 2 => 30, 3 => 25, 4 => 15],

        // Guests open only these cases of the starting set: how many of each difficulty.
        'guest' => [1 => 2, 2 => 1],

        // How many new cases appear every day, and how many checked ones wait in the reserve.
        'daily' => (int) env('KILLDOKU_DAILY_COUNT', 10),
        'reserve' => (int) env('KILLDOKU_RESERVE_COUNT', 30),

        // Difficulty of the cases the reserve is filled with, per day's batch of ten.
        'daily_mix' => [1 => 1, 2 => 2, 3 => 3, 4 => 2, 5 => 2],

        // Profiles per difficulty: the map size and the conditions of the whole case (every_room, room_exact_count,
        // room_limit). The generator picks one at random; the rating is measured on the finished case (logic of the
        // bot, clarity of the statements, size, conditions), so a profile only makes a level likely, and the bot
        // skips variants that came out at another difficulty. A small map with conditions can be harder than a
        // big one without them, and a big map with plain statements stays easy.
        'profiles' => [
            1 => [
                ['size' => [4, 4], 'conditions' => []],
                ['size' => [5, 5], 'conditions' => []],
                ['size' => [4, 6], 'conditions' => []],
                ['size' => [5, 7], 'conditions' => []],
            ],
            2 => [
                // Small with conditions, middle without, and a big one with plain statements.
                ['size' => [4, 4], 'conditions' => ['every_room', 'room_exact_count']],
                ['size' => [6, 6], 'conditions' => ['every_room', 'room_exact_count', 'room_limit']],
                ['size' => [7, 7], 'conditions' => []],
                ['size' => [7, 7], 'conditions' => ['every_room']],
                ['size' => [8, 8], 'conditions' => ['every_room', 'room_exact_count']],
                ['size' => [9, 9], 'conditions' => []],
            ],
            3 => [
                ['size' => [6, 6], 'conditions' => ['every_room', 'room_exact_count', 'room_limit']],
                ['size' => [7, 7], 'conditions' => ['every_room', 'room_exact_count', 'room_limit']],
                ['size' => [8, 8], 'conditions' => ['every_room', 'room_exact_count', 'room_limit']],
                ['size' => [9, 9], 'conditions' => ['every_room', 'room_exact_count']],
                ['size' => [9, 9], 'conditions' => ['every_room', 'room_exact_count', 'room_limit']],
            ],
            4 => [
                ['size' => [8, 8], 'conditions' => ['every_room', 'room_exact_count', 'room_limit']],
                ['size' => [9, 9], 'conditions' => ['every_room', 'room_exact_count']],
                ['size' => [9, 9], 'conditions' => ['every_room', 'room_exact_count', 'room_limit']],
            ],
            // The hardest: reachable only with a lot of intellect, one such case a day.
            5 => [
                ['size' => [9, 9], 'conditions' => ['every_room', 'room_exact_count', 'room_limit']],
                ['size' => [8, 8], 'conditions' => ['every_room', 'room_limit']],
            ],
        ],

        // How many variants the bot may skip while looking for a case of the wanted difficulty.
        'variants' => 24,
    ],

    /*
    | Development of the detective (task 45). Every level of the character gives one development point (and each
    | chapter of the story a few more, `config/story.php`), which the
    | player puts into one of three skills. A skill opens abilities at its thresholds ('at' = points in the skill).
    | The numbers come from the simulation in docs/progression.md and may be edited freely.
    */
    'skills' => [
        // Cases above the difficulty opened by intellect are closed to a signed-in player (guest cases stay open).
        'lock_levels' => env('KILLDOKU_LOCK_LEVELS', true),

        // The points can be taken back and put again, but only once per this many levels gained (the first time at once).
        'respec_levels' => 20,

        // Hints of the attention skill: one for each `solves_per_hint` solved cases, and one more for each
        // `stock_step` points above the first threshold.
        'solves_per_hint' => 3,
        'stock_step' => 5,

        'intellect' => [
            'label' => 'Интеллект',
            'about' => 'Открывает более сложные дела и прибавку к очкам за самые трудные.',
            'steps' => [
                ['at' => 6, 'ability' => 'level_2', 'title' => 'Дела сложности 2'],
                ['at' => 15, 'ability' => 'level_3', 'title' => 'Дела сложности 3'],
                ['at' => 26, 'ability' => 'level_4', 'title' => 'Дела сложности 4'],
                ['at' => 38, 'ability' => 'level_5', 'title' => 'Дела сложности 5'],
                ['at' => 50, 'ability' => 'hard_bonus_1', 'title' => 'Азарт I: +10 % очков за дела сложности 4–5'],
                ['at' => 62, 'ability' => 'hard_bonus_2', 'title' => 'Азарт II: +20 % очков за дела сложности 4–5'],
                ['at' => 76, 'ability' => 'hard_bonus_3', 'title' => 'Азарт III: +30 % очков за дела сложности 4–5'],
                ['at' => 90, 'ability' => 'hard_bonus_4', 'title' => 'Азарт IV: +40 % очков за дела сложности 4–5'],
            ],
        ],
        'attention' => [
            'label' => 'Внимательность',
            'about' => 'Открывает подсказки, подсветку на карте, бесплатные подсказки и запас.',
            'steps' => [
                ['at' => 2, 'ability' => 'hints', 'title' => 'Подсказки: по одной за каждые три решённых дела'],
                ['at' => 10, 'ability' => 'locate', 'title' => 'Подсветка названного на карте («На карте»)'],
                ['at' => 20, 'ability' => 'free_hints_1', 'title' => 'Острый глаз I: первая подсказка к делу не отнимает бонус за решение без подсказок'],
                ['at' => 32, 'ability' => 'free_hints_2', 'title' => 'Острый глаз II: две подсказки к делу не отнимают бонус'],
                ['at' => 44, 'ability' => 'free_hints_3', 'title' => 'Острый глаз III: все три подсказки к делу не отнимают бонус'],
                ['at' => 56, 'ability' => 'hint_pace_2', 'title' => 'Быстрая память: подсказка за каждые два решённых дела вместо трёх'],
                ['at' => 72, 'ability' => 'stock_bonus_1', 'title' => 'Походный запас I: +3 подсказки в запас'],
                ['at' => 88, 'ability' => 'stock_bonus_2', 'title' => 'Походный запас II: +6 подсказок в запас'],
            ],
        ],
        'expertise' => [
            'label' => 'Экспертность',
            'about' => 'Открывает блокнот, автоотметку линий, помощника, кнопку «Поставить» и прибавку к очкам.',
            'steps' => [
                ['at' => 1, 'ability' => 'notes', 'title' => 'Блокнот: пометки на клетках'],
                ['at' => 3, 'ability' => 'cover', 'title' => 'Автоотметка линий: строка и столбец поставленного гостя перекрываются сами'],
                ['at' => 6, 'ability' => 'helper', 'title' => 'Помощник: обводит подходящие клетки'],
                ['at' => 13, 'ability' => 'mark', 'title' => '«Отметить кандидатов» одним нажатием'],
                ['at' => 25, 'ability' => 'points_bonus_1', 'title' => 'Опыт I: +10 % очков за каждое решённое дело'],
                ['at' => 37, 'ability' => 'points_bonus_2', 'title' => 'Опыт II: +20 % очков за каждое решённое дело'],
                ['at' => 50, 'ability' => 'autoplace', 'title' => 'Уверенная рука: кнопка «Поставить» ставит гостя на единственную подходящую клетку'],
                ['at' => 62, 'ability' => 'points_bonus_3', 'title' => 'Опыт III: +30 % очков за каждое решённое дело'],
                ['at' => 78, 'ability' => 'points_bonus_4', 'title' => 'Опыт IV: +40 % очков за каждое решённое дело'],
                ['at' => 92, 'ability' => 'points_bonus_5', 'title' => 'Опыт V: +50 % очков за каждое решённое дело'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shop
    |--------------------------------------------------------------------------
    |
    | Hint packs. Prices are whole kopecks and live only here: the client sends a product code, never a sum.
    | `driver` is `fake` (a stand-in payment page for development, tests and the alpha test) or `yookassa`; production refuses `fake` unless `alpha` is on.
    | The seller is a self-employed person, so no online cash register is needed; `receipt` switches the receipt
    | data of YooKassa on for a sole proprietor or a company.
    |
    */
    'shop' => [
        'driver' => env('PAYMENTS_DRIVER', 'fake'),
        // Alpha test: the `fake` driver may run in production. Players see an ordinary payment page that says no real
        // money is taken, and the hints are granted on "Оплатить". Switch off (and set `yookassa`) before the real launch.
        'alpha' => (bool) env('PAYMENTS_ALPHA', false),
        'currency' => 'RUB',
        'receipt' => (bool) env('YOOKASSA_RECEIPT', false),
        'products' => [
            'hints_5' => ['title' => '5 подсказок', 'hints' => 5, 'price_kopecks' => 9900],
        ],
        // An unpaid order older than this is dropped from the "needs attention" list of the admin.
        'pending_minutes' => 60,
    ],
];
