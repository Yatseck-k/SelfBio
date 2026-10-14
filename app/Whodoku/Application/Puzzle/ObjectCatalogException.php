<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use RuntimeException;

/** A change to the furniture lists that is not allowed; the message is shown to the administrator. */
class ObjectCatalogException extends RuntimeException {}
