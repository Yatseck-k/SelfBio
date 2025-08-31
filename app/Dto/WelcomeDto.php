<?php

declare(strict_types=1);

namespace App\Dto;

use App\Dto\Interfaces\DtoInterface;
use App\Models\Interfaces\BaseModelInterface;

class WelcomeDto implements DtoInterface
{
    private string $title;

    private string $body;

    public function getDto(?BaseModelInterface $model): ?self
    {
        if (!$model) {
            return null;
        }

        $dto = new self;

        return $dto->setTitle($model->title)
            ->setBody($model->body);
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
