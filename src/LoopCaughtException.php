<?php

namespace Amp\PHPUnit;

final class LoopCaughtException extends \Exception
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct(\sprintf(
            "%s thrown to event loop error handler: %s",
            \str_replace("\0", '@', \get_class($previous)), // replace NUL-byte in anonymous class name
            $previous->getMessage()
        ), 0, $previous);
    }
}
