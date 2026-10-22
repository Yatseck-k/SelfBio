<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class AccountController extends Controller
{
    /** The account opens on its first tab; a landing dashboard will come before the tabs when there is something to show. */
    public function index(): RedirectResponse
    {
        return redirect((string) config('account.tabs.0.path', '/'));
    }
}
