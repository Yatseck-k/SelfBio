<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BlogPost;
use App\Services\ContactInfoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Whodoku\Models\Puzzle;

/**
 * What the page says about itself to a visitor that does not run scripts: a search or social bot, a messenger preview.
 * The pages are drawn by Vue in the browser, so the server also puts the title, the description, the canonical address,
 * the social tags and a plain-text copy of the content into the HTML. Private areas are marked «noindex».
 */
final class Seo
{
    /** Paths a bot must neither index nor follow (the same list goes to robots.txt and to the X-Robots-Tag header). */
    public const array PRIVATE_PATHS = ['admin', 'game-admin', 'login', 'logout', 'forgot-password', 'reset-password', 'repair', 'account', 'Ремонт', 'archive', 'profile', 'shop', 'player', 'api/catalog', 'api/undo-state', 'api/status', 'api/move', 'api/undo', 'api/import'];

    private const string ALLOWED_TAGS = '<p><ul><ol><li><h2><h3><h4><strong><b><em><i><blockquote><br>';

    /** The path may come percent-encoded (Request::path()): «/Ремонт» arrives as «%D0%A0…» and must still match. */
    public static function isPrivate(string $path): bool
    {
        $path = trim(rawurldecode($path), '/');

        return array_any(self::PRIVATE_PATHS, fn ($private) => $path === $private || str_starts_with($path, $private.'/'));
    }

    /**
     * @return array{title: string, description: string, canonical: string, noindex: bool, image: ?string, type: string, json_ld: ?array<string, mixed>, noscript: string, locale: string}
     */
    public static function forRequest(Request $request): array
    {
        $brand = (string) config('app.name');
        $path = trim($request->path(), '/');
        $base = [
            'title' => $brand,
            'description' => "Сайт {$brand}: контакты, блог и детективная игра Whodoku.",
            'canonical' => url($path === '' ? '/' : $path),
            'noindex' => self::isPrivate($path),
            'image' => null,
            'type' => 'website',
            'json_ld' => null,
            'noscript' => '',
            'locale' => 'ru_RU',
        ];

        $page = rescue(fn (): array => self::cachedPage($path, $brand), [], false);

        return [...$base, ...$page];
    }

    /**
     * The pages that read the database (the blog, a post, a case) are cached like the blog itself: a saved post changes
     * the blog version, and ten minutes cover a post whose time has come and a case published or hidden in the game admin.
     *
     * @return array<string, mixed>
     */
    private static function cachedPage(string $path, string $brand): array
    {
        if ($path !== 'blog' && !str_starts_with($path, 'blog/') && !str_starts_with($path, 'puzzles/')) {
            return self::page($path, $brand);
        }
        $key = 'seo.page.'.Cache::get('selfbio.blog.version', '0').'.'.hash('xxh128', $brand."\n".$path);

        return Cache::remember($key, 600, fn (): array => self::page($path, $brand));
    }

    /** @return array<string, mixed> */
    private static function page(string $path, string $brand): array
    {
        if (preg_match('#^blog/([^/]+)$#', $path, $m) === 1) {
            return self::post($m[1], $brand);
        }

        return match (true) {
            $path === '' => self::home($brand),
            $path === 'contacts' => self::titled('Контакты', "Как связаться: контакты {$brand}.", $brand),
            $path === 'blog' => self::blog($brand),
            $path === 'puzzles' || $path === 'game' => self::titled('Каталог дел Whodoku', 'Логические детективные дела с картой, показаниями и одним правильным ответом. Гостям открыты гостевые дела.', $brand),
            $path === 'story' => self::titled('Путь детектива', 'Сюжетная кампания Whodoku из пятидесяти глав: от стажёра до старшего детектива.', $brand),
            $path === 'rules' => self::titled('Правила Whodoku', 'Как расставить гостей по показаниям и найти виновного: правила детективной головоломки.', $brand),
            $path === 'rating' => self::titled('Рейтинг детективов', 'Лучшие детективы Whodoku по очкам и раскрытым делам.', $brand),
            $path === 'offer' => self::titled('Оферта', 'Условия покупки подсказок в игре Whodoku.', $brand),
            $path === 'privacy' => self::titled('Политика конфиденциальности', 'Какие данные хранит игра Whodoku и как их удалить.', $brand),
            str_starts_with($path, 'puzzles/') => self::puzzle(substr($path, 8), $brand),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private static function titled(string $title, string $description, string $brand): array
    {
        return ['title' => "{$title} - {$brand}", 'description' => $description];
    }

    /** @return array<string, mixed> */
    private static function home(string $brand): array
    {
        $texts = PageTexts::all()['contacts'];
        // The same cached contacts the page itself gets (forgotten when the contacts are saved).
        $contact = resolve(ContactInfoService::class)->getContactInfo()?->toArray();
        $html = '<h1>'.e($texts['title']).'</h1>'.($texts['lead'] !== '' ? '<p>'.e($texts['lead']).'</p>' : '');
        if ($contact !== null) {
            $html .= '<h2>Контакты</h2><p>'.e((string) $contact['name']).(empty($contact['email']) ? '' : ' — <a href="mailto:'.e($contact['email']).'">'.e($contact['email']).'</a>').'</p>';
        }

        return [
            'title' => $brand,
            'description' => $texts['lead'] !== '' ? self::excerpt($texts['lead']) : "Сайт {$brand}: контакты, блог и детективная игра Whodoku.",
            'noscript' => $html.self::navigation(),
        ];
    }

    /** @return array<string, mixed> */
    private static function blog(string $brand): array
    {
        $posts = BlogPost::query()->published()->latest('published_at')->limit(20)->get();
        $list = $posts->map(fn (BlogPost $post): string => '<li><a href="'.e(url('/blog/'.$post->slug)).'">'.e($post->title).'</a> — '.e(self::excerpt((string) $post->preview, 160)).'</li>')->implode('');

        return [
            'title' => "Блог - {$brand}",
            'description' => $posts->isEmpty() ? "Записи блога {$brand}." : self::excerpt($posts->pluck('title')->implode(', '), 160),
            'noscript' => '<h1>Блог</h1>'.($list === '' ? '<p>Записей пока нет.</p>' : "<ul>{$list}</ul>").self::navigation(),
        ];
    }

    /** @return array<string, mixed> */
    private static function post(string $slug, string $brand): array
    {
        $post = BlogPost::query()->published()->where('slug', $slug)->first();
        if ($post === null) {
            return ['title' => "Запись не найдена - {$brand}", 'noindex' => true];
        }

        $image = $post->image_url === null ? null : url($post->image_url);
        $description = self::excerpt((string) ($post->preview ?: $post->body));

        return [
            'title' => "{$post->title} - {$brand}",
            'description' => $description,
            'image' => $image,
            'type' => 'article',
            'json_ld' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $post->title,
                'description' => $description,
                'image' => $image,
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->updated_at?->toIso8601String(),
                'mainEntityOfPage' => url('/blog/'.$post->slug),
                'publisher' => ['@type' => 'Organization', 'name' => $brand],
            ]),
            'noscript' => '<article><h1>'.e($post->title).'</h1>'.($image ? '<img src="'.e($image).'" alt="'.e($post->title).'">' : '').'<div>'.self::plainHtml((string) $post->body).'</div></article>'.self::navigation(),
        ];
    }

    /** @return array<string, mixed> */
    private static function puzzle(string $slug, string $brand): array
    {
        // A case that needs sign-in is not shown to a bot: its page is only «noindex».
        $puzzle = Puzzle::query()->where('slug', $slug)->where('status', 'published')->first();
        if ($puzzle === null || !$puzzle->guest_access) {
            return ['title' => "Дело - Whodoku - {$brand}", 'noindex' => true];
        }

        return [
            'title' => "{$puzzle->title} - Whodoku - {$brand}",
            'description' => self::excerpt((string) ($puzzle->description ?: 'Детективное дело Whodoku: расставьте гостей по показаниям и найдите виновного.')),
        ];
    }

    /** The text of a post with only the tags that give it structure: no attributes, so no handlers and no foreign links. */
    private static function plainHtml(string $html): string
    {
        return (string) preg_replace('/<(\/?[a-z0-9]+)\b[^>]*?(\/?)>/i', '<$1$2>', strip_tags($html, self::ALLOWED_TAGS));
    }

    private static function navigation(): string
    {
        return '<nav><a href="'.e(url('/')).'">Главная</a> <a href="'.e(url('/blog')).'">Блог</a> <a href="'.e(url('/contacts')).'">Контакты</a> <a href="'.e(url('/puzzles')).'">Whodoku</a></nav>';
    }

    private static function excerpt(string $text, int $limit = 200): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags($text))), $limit, '…');
    }
}
