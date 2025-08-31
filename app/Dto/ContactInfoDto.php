<?php

declare(strict_types=1);

namespace App\Dto;

use App\Dto\Interfaces\DtoInterface;
use App\Models\Interfaces\BaseModelInterface;

class ContactInfoDto implements DtoInterface
{
    private string $name;

    private string $phone;

    private string $email;

    private string $telegram;

    private string $github;

    private array $socials;

    public function getDto(?BaseModelInterface $model): ?self
    {
        if (!$model) {
            return null;
        }

        $dto = new self;

        return $dto->setName($model->name)
            ->setPhone($model->phone)
            ->setEmail($model->email)
            ->setTelegram($model->telegram)
            ->setGithub($model->github)
            ->setSocials($model->socials);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'telegram' => $this->telegram,
            'github' => $this->github,
            'socials' => $this->socials,
        ];
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function setTelegram(string $telegram): self
    {
        $this->telegram = $telegram;

        return $this;
    }

    public function setGithub(string $github): self
    {
        $this->github = $github;

        return $this;
    }

    public function setSocials($socials): self
    {
        if (is_array($socials)) {
            $this->socials = $socials;
        } elseif (is_string($socials)) {
            $this->socials = json_decode($socials, true) ?? [];
        } else {
            $this->socials = [];
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTelegram(): string
    {
        return $this->telegram;
    }

    public function getGithub(): string
    {
        return $this->github;
    }

    public function getSocials(): array
    {
        return $this->socials;
    }
}
