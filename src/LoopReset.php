<?php

namespace Amp\PHPUnit;

use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;

class LoopReset extends BaseTestListener
{
    public function endTest(Test $test, $time)
    {
        gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop
    }
}
