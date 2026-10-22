<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * The headings and short explanations of the pages that the editors change in the admin panel («Настройки сайта»).
 * A text that is empty or never set shows the default below, so a new site is never left with blank pages.
 */
final class PageTexts
{
    private const string CACHE_KEY = 'site.page_texts';

    private const string PREFIX = 'page.';

    /** @var array<string, array{group: string, label: string, default: string, long: bool}> */
    public const array FIELDS = [
        'contacts.title' => ['group' => 'contacts', 'label' => 'Контакты: заголовок страницы', 'default' => 'Контактная информация', 'long' => false],
        'contacts.lead' => ['group' => 'contacts', 'label' => 'Контакты: пояснение под заголовком', 'default' => '', 'long' => true],
        'blog.title' => ['group' => 'blog', 'label' => 'Блог: заголовок страницы', 'default' => 'Блог', 'long' => false],
        'blog.lead' => ['group' => 'blog', 'label' => 'Блог: пояснение под заголовком', 'default' => '', 'long' => true],
        'puzzles.about' => ['group' => 'puzzles', 'label' => 'Игра: что это за страница (каталог дел)', 'default' => 'Whodoku — детективные логические головоломки. В каждом деле есть карта, гости и их показания: расставьте гостей по комнатам так, чтобы все показания сошлись, и назовите виновного. Гостям открыты гостевые дела; после входа через Telegram — все дела, сюжет «Путь детектива», очки и рейтинг.', 'long' => true],
    ];

    /** @return array<string, array<string, string>> the texts by page: ['contacts' => ['title' => …, 'lead' => …], …] */
    public static function all(): array
    {
        $saved = rescue(
            fn (): array => Cache::remember(self::CACHE_KEY, 3600, fn (): array => SiteSetting::query()->where('key', 'like', self::PREFIX.'%')->pluck('value', 'key')->all()),
            [],
            false,
        );

        $texts = [];
        foreach (self::FIELDS as $field => $meta) {
            [$page, $name] = explode('.', $field);
            $value = trim((string) ($saved[self::PREFIX.$field] ?? ''));
            $texts[$page][$name] = $value !== '' ? $value : $meta['default'];
        }

        return $texts;
    }

    /** The same texts as a flat list `contacts.title => …` for the form of the admin panel. */
    public static function flat(): array
    {
        $flat = [];
        foreach (self::all() as $page => $fields) {
            foreach ($fields as $name => $value) {
                $flat["{$page}.{$name}"] = $value;
            }
        }

        return $flat;
    }

    /** @param  array<string, mixed>  $values  `contacts.title => text`; an empty text goes back to the default */
    public static function save(array $values): void
    {
        foreach (self::FIELDS as $field => $meta) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            $value = trim((string) $values[$field]);
            if ($value === '' || $value === $meta['default']) {
                SiteSetting::query()->where('key', self::PREFIX.$field)->delete();
            } else {
                SiteSetting::query()->updateOrCreate(['key' => self::PREFIX.$field], ['value' => $value]);
            }
        }
        Cache::forget(self::CACHE_KEY);
    }
}
