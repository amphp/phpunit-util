<?php

namespace Amp\PHPUnit\Test;

use Amp\PHPUnit\AsyncTestCase;
use PHPUnit\Framework\AssertionFailedError;

if ((new \ReflectionMethod(AsyncTestCase::class, 'setUp'))->hasReturnType()) {
    // PHPUnit 8+
    class InvalidAsyncTestCaseTest extends AsyncTestCase
    {
        protected function setUp(): void
        {
            // No call to parent::setUp()

            $this->expectException(AssertionFailedError::class);
            $this->expectExceptionMessage('without calling the parent method');
        }

        public function testMethod()
        {
            // Test will fail because setUp() did not call the parent method
        }
    }
} else {
    // PHPUnit 6 or 7
    class InvalidAsyncTestCaseTest extends AsyncTestCase
    {
        protected function setUp()
        {
            // No call to parent::setUp()

            $this->expectException(AssertionFailedError::class);
            $this->expectExceptionMessage('without calling the parent method');
        }

        public function testMethod()
        {
            // Test will fail because setUp() did not call the parent method
        }
    }
}
