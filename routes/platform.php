<?php

declare(strict_types=1);

use App\Orchid\Screens\Account\AccountUserEditScreen;
use App\Orchid\Screens\Account\AccountUserListScreen;
use App\Orchid\Screens\Account\RepairDataScreen;
use App\Orchid\Screens\BlogPostEditScreen;
use App\Orchid\Screens\BlogPostListScreen;
use App\Orchid\Screens\CardSettingsScreen;
use App\Orchid\Screens\ContactInfoScreen;
use App\Orchid\Screens\GameSettingsScreen;
use App\Orchid\Screens\ProfileContentScreen;
use App\Orchid\Screens\SiteSettingsScreen;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;
use Whodoku\Orchid\Screens\Appearance\AppearanceScreen;
use Whodoku\Orchid\Screens\Catalog\ObjectCatalogScreen;
use Whodoku\Orchid\Screens\PlatformScreen;
use Whodoku\Orchid\Screens\Player\PlayerListScreen;
use Whodoku\Orchid\Screens\Puzzle\DifficultyReviewScreen;
use Whodoku\Orchid\Screens\Puzzle\PuzzleDetailsScreen;
use Whodoku\Orchid\Screens\Puzzle\PuzzleGenerationScreen;
use Whodoku\Orchid\Screens\Puzzle\PuzzleListScreen;
use Whodoku\Orchid\Screens\Puzzle\PuzzleMapScreen;
use Whodoku\Orchid\Screens\Puzzle\PuzzleValidationScreen;
use Whodoku\Orchid\Screens\Release\ReleaseScreen;
use Whodoku\Orchid\Screens\Role\RoleEditScreen;
use Whodoku\Orchid\Screens\Role\RoleListScreen;
use Whodoku\Orchid\Screens\Shop\OrderListScreen;
use Whodoku\Orchid\Screens\Skills\SkillSettingsScreen;
use Whodoku\Orchid\Screens\User\UserEditScreen;
use Whodoku\Orchid\Screens\User\UserListScreen;
use Whodoku\Orchid\Screens\User\UserProfileScreen;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the need "dashboard" middleware group. Now create something great!
|
*/

// Main
Route::screen('selfbio/blog/create', BlogPostEditScreen::class)->name('platform.selfbio.blog.create');
Route::screen('selfbio/blog/{post}/edit', BlogPostEditScreen::class)->name('platform.selfbio.blog.edit');
Route::screen('selfbio/blog', BlogPostListScreen::class)->name('platform.selfbio.blog');
Route::screen('selfbio/contacts', ContactInfoScreen::class)->name('platform.selfbio.contacts');
Route::screen('selfbio/profile', ProfileContentScreen::class)->name('platform.selfbio.profile');
Route::screen('selfbio/settings', SiteSettingsScreen::class)->name('platform.selfbio.settings');
Route::screen('selfbio/card-settings', CardSettingsScreen::class)->name('platform.selfbio.card');
Route::screen('selfbio/game-settings', GameSettingsScreen::class)->name('platform.selfbio.game');

// The personal account of the site: its users and the «Ремонт» tab.
Route::screen('account/users/create', AccountUserEditScreen::class)->name('platform.account.users.create');
Route::screen('account/users/{user}/edit', AccountUserEditScreen::class)->name('platform.account.users.edit');
Route::screen('account/users', AccountUserListScreen::class)->name('platform.account.users');
Route::screen('account/repair', RepairDataScreen::class)->name('platform.account.repair');

Route::screen('/main', PlatformScreen::class)
    ->name('platform.main');

// Platform > Release of cases
Route::screen('release', ReleaseScreen::class)
    ->name('platform.release')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Выпуск дел', route('platform.release')));

// Platform > Orders
Route::screen('orders', OrderListScreen::class)
    ->name('platform.orders')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Заказы', route('platform.orders')));

// Platform > Players
Route::screen('players', PlayerListScreen::class)
    ->name('platform.players')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Игроки', route('platform.players')));

// Platform > Blind check of the difficulty
Route::screen('difficulty', DifficultyReviewScreen::class)
    ->name('platform.difficulty')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Оценка сложности', route('platform.difficulty')));

// Platform > Skills of the detective
Route::screen('skills', SkillSettingsScreen::class)
    ->name('platform.skills')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Навыки детектива', route('platform.skills')));

// Platform > Object catalog
Route::screen('objects', ObjectCatalogScreen::class)
    ->name('platform.objects')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Каталог предметов', route('platform.objects')));

// Platform > Appearance
Route::screen('appearance', AppearanceScreen::class)
    ->name('platform.appearance')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Оформление', route('platform.appearance')));

// Platform > Puzzles > Generate
Route::screen('puzzles/generate', PuzzleGenerationScreen::class)
    ->name('platform.puzzles.generate')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Сгенерировать дело', route('platform.puzzles.generate')));

// Platform > Puzzles
Route::screen('puzzles', PuzzleListScreen::class)
    ->name('platform.puzzles')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Дела', route('platform.puzzles')));

// Platform > Puzzles > Puzzle
Route::screen('puzzles/{puzzle}', PuzzleDetailsScreen::class)
    ->name('platform.puzzles.show')
    ->breadcrumbs(fn (Trail $trail, $puzzle) => $trail
        ->parent('platform.puzzles')
        ->push($puzzle->title, route('platform.puzzles.show', $puzzle)));

Route::screen('puzzles/{puzzle}/map', PuzzleMapScreen::class)
    ->name('platform.puzzles.map')
    ->breadcrumbs(fn (Trail $trail, $puzzle) => $trail
        ->parent('platform.puzzles.show', $puzzle)
        ->push('Карта', route('platform.puzzles.map', $puzzle)));

Route::screen('puzzles/{puzzle}/validate', PuzzleValidationScreen::class)
    ->name('platform.puzzles.validate')
    ->breadcrumbs(fn (Trail $trail, $puzzle) => $trail
        ->parent('platform.puzzles.show', $puzzle)
        ->push('Проверка', route('platform.puzzles.validate', $puzzle)));

// Platform > Profile
Route::screen('profile', UserProfileScreen::class)
    ->name('platform.profile')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Profile'), route('platform.profile')));

// Platform > System > Users > User
Route::screen('users/{user}/edit', UserEditScreen::class)
    ->name('platform.systems.users.edit')
    ->breadcrumbs(fn (Trail $trail, $user) => $trail
        ->parent('platform.systems.users')
        ->push($user->name, route('platform.systems.users.edit', $user)));

// Platform > System > Users > Create
Route::screen('users/create', UserEditScreen::class)
    ->name('platform.systems.users.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.users')
        ->push(__('Create'), route('platform.systems.users.create')));

// Platform > System > Users
Route::screen('users', UserListScreen::class)
    ->name('platform.systems.users')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Users'), route('platform.systems.users')));

// Platform > System > Roles > Role
Route::screen('roles/{role}/edit', RoleEditScreen::class)
    ->name('platform.systems.roles.edit')
    ->breadcrumbs(fn (Trail $trail, $role) => $trail
        ->parent('platform.systems.roles')
        ->push($role->name, route('platform.systems.roles.edit', $role)));

// Platform > System > Roles > Create
Route::screen('roles/create', RoleEditScreen::class)
    ->name('platform.systems.roles.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.roles')
        ->push(__('Create'), route('platform.systems.roles.create')));

// Platform > System > Roles
Route::screen('roles', RoleListScreen::class)
    ->name('platform.systems.roles')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Roles'), route('platform.systems.roles')));
