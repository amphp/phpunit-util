<?php

namespace Amp\PHPUnit;

use Amp\Loop;
use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;

class LoopReset extends BaseTestListener {
    private $previousDriver;

    public function startTest(Test $test) {
        $this->previousDriver = Loop::get();
        Loop::set(new Loop\NativeDriver);
    }

    public function endTest(Test $test, $time) {
        Loop::set($this->previousDriver);
    }
}
