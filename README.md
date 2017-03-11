# phpunit-util

![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`amphp/phpunit-util` is a small helper package to ease testing with PHPUnit in combination with the [`amp`](https://github.com/amphp/amp) concurrency framework.

**Required PHP Version**

- PHP 7.0+

**Installation**

```bash
composer require --dev amphp/phpunit-util
```

**Usage**

Currently, this package only provides a PHPUnit `TestListener` to reset the global event loop after each test. By adding the listener to your PHPUnit configuration, each test will be executed with a completely new event loop instance.

```xml
<phpunit>
    <!-- ... -->

    <listeners>
        <listener class="Amp\PHPUnit\LoopReset" />
    </listeners>

    <!-- ... -->
</phpunit>
```
