<?php

declare(strict_types=1);

/*
| The personal account («ЛК») of the site: a signed-in user lands here and moves between its tabs.
| A new part of the account (a dashboard of tasks, bots, servers, payments…) is one more entry in this list.
*/
return [
    'tabs' => [
        ['key' => 'repair', 'title' => 'Ремонт', 'path' => '/account/repair'],
    ],
];
