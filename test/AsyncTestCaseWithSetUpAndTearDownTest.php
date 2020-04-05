<?php

namespace Amp\PHPUnit\Test;

use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Promise;

class AsyncTestCaseWithSetUpAndTearDownTest extends AsyncTestCase
{
    private static $firstRun = true;

    protected function setUpAsync(): Promise
    {
        if (self::$firstRun) {
            self::$firstRun = false;
            return parent::setUpAsync();
        }

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('setUpAsync() failed');

        return new Failure(new TestException);
    }

    protected function tearDownAsync(): Promise
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('tearDownAsync() failed');

        return new Failure(new TestException);
    }

    public function testFailingTearDownAsync()
    {
        // Expected exception set in tearDownAsync().
    }

    public function testFailingSetUpAsync()
    {
        // Expected exception set in setUpAsync().
    }

    public function testThatExceptionIsNotThrownCorrectly(): \Generator
    {
        $this->expectException(\Exception::class);
    }

}
