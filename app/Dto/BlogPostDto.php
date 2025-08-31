<?php

declare(strict_types=1);

namespace App\Dto;

use App\Dto\Interfaces\DtoInterface;
use App\Models\Interfaces\BaseModelInterface;
use DateTime;

class BlogPostDto implements DtoInterface
{
    private string $title;

    private string $slug;

    private ?string $preview = null;

    private ?string $body = null;

    private ?string $image = null;

    private ?string $publishedAt = null;

    public function getDto(?BaseModelInterface $model): ?self
    {
        if (!$model) {
            return null;
        }

        $dto = new self;

        return $dto->setTitle($model->title)
            ->setSlug($model->slug)
            ->setPreview($model->preview)
            ->setBody($model->body)
            ->setImage($model->image)
            ->setPublishedAt($model->published_at);
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'preview' => $this->preview,
            'body' => $this->body,
            'image' => $this->image,
            'published_at' => $this->publishedAt,
        ];
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function setPreview(?string $preview): self
    {
        $this->preview = $preview;

        return $this;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function setPublishedAt($publishedAt): self
    {
        if ($publishedAt === null) {
            $this->publishedAt = null;
        } elseif ($publishedAt instanceof DateTime) {
            $this->publishedAt = $publishedAt->format('Y-m-d H:i:s');
        } elseif (is_string($publishedAt)) {
            $this->publishedAt = $publishedAt;
        } else {
            $this->publishedAt = null;
        }

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getPreview(): ?string
    {
        return $this->preview;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getPublishedAt(): ?string
    {
        return $this->publishedAt;
    }
}
