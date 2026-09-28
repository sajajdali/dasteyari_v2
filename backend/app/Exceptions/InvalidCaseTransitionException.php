<?php

namespace App\Exceptions;

use RuntimeException;

/** گذار وضعیت غیرمجاز — بخش ۴ پلن. CaseEventService::record() این را پرتاب می‌کند. */
class InvalidCaseTransitionException extends RuntimeException
{
}
