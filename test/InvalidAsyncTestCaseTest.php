<?php

namespace Amp\PHPUnit\Test;

use Amp\PHPUnit\AsyncTestCase;
use PHPUnit\Framework\AssertionFailedError;

class InvalidAsyncTestCaseTest extends AsyncTestCase
{
    protected function setUp()
    {
        // No call to parent::setUp()

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('without calling the parent method');
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage without calling the parent method
     */
    public function testMethod()
    {
        // Test will fail because setUp() did not call the parent method
    }
}
