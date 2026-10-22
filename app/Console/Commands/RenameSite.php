<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\SiteName;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Show or change the name of the site (the same setting as «Настройки сайта» in the admin panel)')]
#[Signature('site:rename {name? : The new name of the site} {--show : Only print the current name} {--reset : Go back to APP_NAME}')]
class RenameSite extends Command
{
    public function handle(): int
    {
        if ($this->option('reset')) {
            SiteName::reset();
            $this->info('Название сайта сброшено к APP_NAME.');

            return self::SUCCESS;
        }

        $name = $this->argument('name');
        if ($this->option('show') || $name === null) {
            $this->line('Название сайта: '.config('app.name'));

            return self::SUCCESS;
        }

        $name = trim((string) $name);
        if (!SiteName::valid($name)) {
            $this->error('Название: от 1 до '.SiteName::MAX_LENGTH.' символов, без управляющих символов.');

            return self::FAILURE;
        }

        SiteName::save($name);
        $this->info("Название сайта изменено на «{$name}».");

        return self::SUCCESS;
    }
}
