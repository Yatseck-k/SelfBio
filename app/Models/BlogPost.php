<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Interfaces\BaseModelInterface;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Orchid\Screen\AsSource;

#[Fillable([
    'title',
    'slug',
    'preview',
    'body',
    'image',
    'published_at',
])]
#[Connection('pgsql')]
#[Appends(['image_url'])]
class BlogPost extends Model implements BaseModelInterface
{
    use AsSource;

    /** The public address of a stored cover («blog/x.png» from the form; an old bare file name lives in blog/ too). */
    public static function publicImageUrl(?string $image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $image) === 1) {
            return $image;
        }

        return '/storage/'.(str_contains($image, '/') ? ltrim($image, '/') : 'blog/'.$image);
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => self::publicImageUrl($this->image));
    }

    /** Visible to everyone: has a publication date that has already come. */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function getClassName(): string
    {
        return self::class;
    }

    public function getPosts(): LengthAwarePaginator
    {
        return self::query()->published()
            ->latest('published_at')
            ->paginate(10);
    }

    public function getPost(string $slug): ?BaseModelInterface
    {
        return self::query()->published()->where('slug', $slug)->first();
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
