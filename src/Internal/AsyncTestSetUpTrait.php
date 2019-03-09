<?php

namespace Amp\PHPUnit\Internal;

use Amp\Loop;
use PHPUnit\Framework\TestCase;

if ((new \ReflectionMethod(TestCase::class, 'setUp'))->hasReturnType()) {
    // PHPUnit 8+
    trait AsyncTestSetUpTrait
    {
        /** @var bool */
        private $setUpInvoked = false;

        protected function setUp(): void
        {
            $this->setUpInvoked = true;
            Loop::set((new Loop\DriverFactory)->create());
            \gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop
        }
    }
} else {
    // PHPUnit 6 or 7
    trait AsyncTestSetUpTrait
    {
        /** @var bool */
        private $setUpInvoked = false;

        protected function setUp()
        {
            $this->setUpInvoked = true;
            Loop::set((new Loop\DriverFactory)->create());
            \gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop
        }
    }
}
