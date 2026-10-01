<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

use Throwable;

/**
 * Every exception the SDK throws. Catch this to handle them all.
 */
interface SendSevenException extends Throwable {}
