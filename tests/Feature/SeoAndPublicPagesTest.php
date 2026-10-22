<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeoAndPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_blog_cover_gets_a_working_public_address(): void
    {
        $this->assertSame('/storage/blog/x.png', BlogPost::publicImageUrl('blog/x.png'));
        $this->assertSame('/storage/blog/old.png', BlogPost::publicImageUrl('old.png'));
        $this->assertSame('https://cdn.example.test/a.png', BlogPost::publicImageUrl('https://cdn.example.test/a.png'));
        $this->assertNull(BlogPost::publicImageUrl(null));

        $this->post_('with-cover', ['image' => 'blog/cover.png']);
        $this->getJson('/api/blog')->assertOk()->assertJsonPath('data.0.image_url', '/storage/blog/cover.png');
        $this->getJson('/api/blog/with-cover')->assertOk()->assertJsonPath('image_url', '/storage/blog/cover.png');
    }

    public function test_the_login_page_does_not_name_the_private_catalog(): void
    {
        $page = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('ремонт', $page);
        $this->assertStringNotContainsStringIgnoringCase('renovation', $page);
        $this->assertStringContainsString('noindex', $page);
        foreach (['ru', 'en'] as $lang) {
            $this->assertStringNotContainsStringIgnoringCase('renovation', file_get_contents(lang_path("{$lang}.json")));
            $this->assertStringNotContainsStringIgnoringCase('ремонт', file_get_contents(lang_path("{$lang}.json")));
        }
    }

    public function test_pages_have_a_real_document_with_title_description_and_social_tags(): void
    {
        config(['app.name' => 'Мой дом']);
        $this->post_('hello', ['title' => 'Первая запись', 'preview' => 'Коротко о главном']);

        $html = $this->get('/blog/hello')->assertOk()->getContent();

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title inertia>Первая запись - Мой дом</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Коротко о главном">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/blog/hello').'">', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('"@type":"BlogPosting"', $html);
        $this->assertStringNotContainsString('noindex', $html);
        // A bot that does not run scripts gets the text of the post.
        $this->assertStringContainsString('<noscript>', $html);
        $this->assertStringContainsString('Текст записи', $html);
    }

    public function test_the_copy_for_bots_drops_attributes_and_scripts_of_the_post(): void
    {
        $this->post_('risky', ['body' => '<p onclick="steal()">Текст <a href="javascript:evil()">ссылка</a></p><script>alert(1)</script><img src=x onerror=evil()>']);

        $html = $this->get('/blog/risky')->getContent();
        $noscript = substr($html, (int) strpos($html, '<noscript>'));

        $this->assertStringContainsString('<p>Текст ссылка</p>', $noscript);
        $this->assertStringNotContainsString('onclick', $noscript);
        $this->assertStringNotContainsString('javascript:', $noscript);
        $this->assertStringNotContainsString('onerror', $noscript);
    }

    public function test_a_hidden_or_missing_post_is_a_real_404_and_is_not_in_the_map(): void
    {
        $this->post_('future', ['published_at' => now()->addDay()]);

        $this->get('/blog/future')->assertNotFound();
        $this->get('/blog/no-such-post')->assertNotFound();
        $this->assertStringNotContainsString('future', (string) $this->get('/sitemap.xml')->getContent());
    }

    public function test_robots_and_sitemap_hide_the_private_areas_and_show_the_public_ones(): void
    {
        $this->post_('open-post');

        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        foreach (['/admin', '/login', '/repair', '/Ремонт', '/archive', '/profile', '/api/catalog'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $robots);
        }
        $this->assertStringNotContainsString('Disallow: /api/blog', $robots);
        $this->assertStringNotContainsString('Disallow: /build', $robots);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $robots);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('<loc>'.url('/blog/open-post').'</loc>', $sitemap);
        $this->assertStringContainsString('<loc>'.url('/puzzles').'</loc>', $sitemap);
        $this->assertStringNotContainsString('/admin', $sitemap);
        $this->assertStringNotContainsString('/repair', $sitemap);
    }

    public function test_private_areas_say_noindex_in_the_header(): void
    {
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/repair')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/api/catalog')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/blog')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_game_pages_have_their_own_titles_and_a_closed_case_is_not_described(): void
    {
        DB::connection('whodoku')->table('puzzles')->insert([
            ['slug' => 'guest-case', 'title' => 'Гостевое дело', 'type' => 'murder', 'difficulty' => 1, 'rows' => 4, 'cols' => 4, 'status' => 'published', 'guest_access' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'closed-case', 'title' => 'Секретное дело', 'type' => 'murder', 'difficulty' => 2, 'rows' => 4, 'cols' => 4, 'status' => 'published', 'guest_access' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->get('/rules')->assertSeeHtml('<title inertia>Правила Whodoku');
        $this->get('/puzzles/guest-case')->assertSeeHtml('Гостевое дело - Whodoku')->assertDontSeeHtml('noindex');
        $this->get('/puzzles/no-such-case')->assertNotFound();
        $closed = $this->get('/puzzles/closed-case')->assertOk()->getContent();
        $this->assertStringNotContainsString('Секретное дело', $closed);
        $this->assertStringContainsString('noindex', $closed);

        $sitemap = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('/puzzles/guest-case', $sitemap);
        $this->assertStringNotContainsString('closed-case', $sitemap);
    }

    /** @param  array<string, mixed>  $extra */
    private function post_(string $slug, array $extra = []): void
    {
        DB::connection('pgsql')->table('blog_posts')->insert([
            'title' => 'Запись', 'slug' => $slug, 'preview' => 'Анонс', 'body' => '<p>Текст записи</p>',
            'published_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now(), ...$extra,
        ]);
    }
}
