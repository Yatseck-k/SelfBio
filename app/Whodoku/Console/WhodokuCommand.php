<?php

declare(strict_types=1);

namespace Whodoku\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class WhodokuCommand extends Command
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('whodoku');

        try {
            return parent::run($input, $output);
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
