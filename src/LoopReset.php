<?php

namespace Amp\PHPUnit;

use Amp\Internal\Scheduler;
use Amp\Loop;
use Amp\Loop\TracingDriver;
use Concurrent\TaskScheduler;
use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;

class LoopReset extends BaseTestListener
{
    private $scheduler;
    private $watcherCount;
    private $previousInfo;

    public function startTest(Test $test)
    {
        TaskScheduler::register($this->scheduler = new Scheduler);

        Loop::setErrorHandler(function (\Throwable $error) {
            \trigger_error((string) $error, \E_USER_ERROR);
        });

        $this->watcherCount = $this->countWatchers();
        $this->previousInfo = Loop::getInfo();
    }

    public function endTest(Test $test, $time)
    {
        TaskScheduler::unregister($this->scheduler);

        gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop

        if ($this->countWatchers() - $this->watcherCount !== 0) {
            $infoDiff = $this->calculateInfoDiff($this->previousInfo);
            $info = \json_encode($infoDiff, \JSON_PRETTY_PRINT);

            $loop = Loop::get();
            if ($loop instanceof TracingDriver) {
                $info .= "\r\n\r\n" . $loop->getDump();
            }

            \trigger_error("Event loop has remaining watchers after test: " . $info, \E_USER_WARNING);
        }
    }

    private function countWatchers(): int
    {
        $info = Loop::getInfo();
        $total = 0;

        foreach (["defer", "delay", "repeat", "on_readable", "on_writable", "on_signal"] as $type) {
            foreach (["enabled", "disabled"] as $state) {
                $total += $info[$type][$state];
            }
        }

        return $total;
    }

    private function calculateInfoDiff(array $previous): array
    {
        $info = Loop::getInfo();

        foreach (["defer", "delay", "repeat", "on_readable", "on_writable", "on_signal"] as $type) {
            foreach (["enabled", "disabled"] as $state) {
                $info[$type][$state] -= $previous[$type][$state];
            }
        }

        return $info;
    }
}
