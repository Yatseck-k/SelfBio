<?php

declare(strict_types=1);

namespace Whodoku\Application\Endless;

use RuntimeException;

/** The player has solved every case that is open to them: new ones come out with the daily release. */
class NoCaseLeft extends RuntimeException {}
