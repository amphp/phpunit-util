<?php declare(strict_types=1);

namespace Amp\PHPUnit\Test;

use Amp\PHPUnit\TestContext;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use RuntimeException;
use stdClass;

class TestContextTest extends PHPUnitTestCase {

    public function testPromiseResolvesWithValue() {
        $subject = new TestContext();
        $testData = new stdClass();
        $testData->val = null;
        $subject->promise()->onResolve(function($err = null, $data = null) use($testData) {
            $testData->val = 'resolved with ' . $data;
        });

        $subject->resolve('foo');

        $this->assertEquals('resolved with foo', $testData->val, 'Expected the Promise to resolve with correct data');
    }

    public function testPromiseFailsWithError() {
        $subject = new TestContext();
        $testData = new stdClass();
        $testData->err = null;
        $subject->promise()->onResolve(function($err = null) use($testData) {
            $testData->err = $err;
        });

        $exception = new RuntimeException('We encountered some error');
        $subject->fail($exception);

        $this->assertEquals($exception, $testData->err, 'Expected the Promise to resolve with an error');
    }

    public function testPromiseDoesNotResolveIfNotEnoughCheckpoints() {
        $subject = new TestContext();
        $subject->promise()->onResolve(function() {
            $this->fail('We did not expect to see this resolved because we did not trigger enough checkpoints');
        });

        $subject->requireCheckpoints(3);
        $subject->checkpoint();
        $subject->checkpoint();

        // There isn't anything to test, simply a regression to ensure that our checkpoint does not resolve erroneously
        $this->assertTrue(true);
    }

    public function testPromiseResolvesIfEnoughCheckpoints() {
        $subject = new TestContext();
        $testData = new stdClass();
        $testData->val = null;
        $subject->promise()->onResolve(function() use($testData) {
            $testData->val = 'checkpoints resolved';
        });

        $subject->requireCheckpoints(3);
        $subject->checkpoint();
        $subject->checkpoint();
        $subject->checkpoint();

        $this->assertEquals('checkpoints resolved', $testData->val, 'Expected the third checkpoint to resolve the Promise');
    }

}