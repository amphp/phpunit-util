<?php

namespace Amp\PHPUnit\Internal;

use PHPUnit\Framework\TestCase;

if ((new \ReflectionMethod(TestCase::class, 'setName'))->hasReturnType()) {
    // PHPUnit 7+
    trait AsyncTestSetNameTrait
    {
        /** @var string Temporary storage for actual test name. */
        private $realTestName;

        /**
         * @codeCoverageIgnore Invoked before code coverage data is being collected.
         */
        final public function setName(string $name): void
        {
            parent::setName($name);
            $this->realTestName = $name;
        }
    }
} else {
    // PHPUnit 6
    trait AsyncTestSetNameTrait
    {
        /** @var string Temporary storage for actual test name. */
        private $realTestName;

        /**
         * @codeCoverageIgnore Invoked before code coverage data is being collected.
         */
        final public function setName($name)
        {
            parent::setName($name);
            $this->realTestName = $name;
        }
    }
}
