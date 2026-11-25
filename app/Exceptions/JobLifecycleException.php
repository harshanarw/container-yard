<?php

namespace App\Exceptions;

/**
 * A job status change the life-cycle rules refuse (Rule 5), with a message
 * written for the person who asked for it.
 */
class JobLifecycleException extends \RuntimeException
{
}
