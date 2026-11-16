<?php

namespace App\Services\Fiscal;

use RuntimeException;

/** FDMS (or the zimra-fdms CLI) refused or failed; the message is safe to show to a superuser. */
class FdmsException extends RuntimeException
{
}
