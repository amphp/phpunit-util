# phpunit-util

![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`amphp/phpunit-util` is a small helper package to ease testing with PHPUnit in combination with the [`Amp`](https://github.com/amphp/amp) concurrency framework.

**Required PHP Version**

- PHP 7.0+

## Installation

```bash
composer require --dev amphp/phpunit-util
```

## Usage

```php
<?php

namespace Foo;

use Amp\ByteStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;

class BarTest extends AsyncTestCase
{
    // Each test case is executed as a coroutine and checked to run to completion
    public function test()
    {
        $socket = yield Socket\connect('tcp://localhost:12345');
        yield $socket->write('foobar');
        
        $this->assertSame('foobar', yield ByteStream\buffer($socket));
    }
}
```

#### Deprecated Functionality

This package also provides a PHPUnit `TestListener` to reset the global event loop after each test, but this is deprecated.

```xml
<phpunit>
    <!-- ... -->

    <!-- DEPRECATED, use AsyncTestCase instead -->
    <listeners>
        <!-- DEPRECATED, use AsyncTestCase instead --> 
        <listener class="Amp\PHPUnit\LoopReset" />
    </listeners>

    <!-- ... -->
</phpunit>
```
