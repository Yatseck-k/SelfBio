<?php

declare(strict_types=1);

namespace Whodoku\Application\Release;

use RuntimeException;

class ReleaseBusyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Генерация уже идёт: дождитесь её окончания.');
    }
}
