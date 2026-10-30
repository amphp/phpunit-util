<?php declare(strict_types=1);

namespace Amp\PHPUnit;

use PHPUnit\Framework\AssertionFailedError;

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
