<?php

namespace Amp\PHPUnit;

use Amp\Loop;
use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;

class LoopReset extends BaseTestListener
{
    public function endTest(Test $test, $time)
    {
        gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop

        $info = Loop::getInfo();
        $total = 0;

        foreach (["defer", "delay", "repeat", "on_readable", "on_writable", "on_signal"] as $type) {
            foreach (["enabled", "disabled"] as $state) {
                $total += $info[$type][$state];
            }
        }

        if ($total !== 0) {
            \trigger_error("Event loop has remaining watchers after test: " . \json_encode($info, \JSON_PRETTY_PRINT), \E_USER_WARNING);
        }
    }
}
