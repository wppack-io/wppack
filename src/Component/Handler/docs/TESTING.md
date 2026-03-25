# Testing

## Running Tests

The Handler tests are standalone and do not require WordPress or a database.

```bash
# Run Handler tests only
vendor/bin/phpunit src/Component/Handler/tests/

# Run a specific test file
vendor/bin/phpunit src/Component/Handler/tests/ConfigurationTest.php
```

## Test Architecture

Tests use temporary directories created in `setUp()` and cleaned up in `tearDown()`. This allows testing processors with real filesystem operations (file existence, directory detection, etc.) without fixtures.

### Bootstrap

The test bootstrap (`tests/bootstrap.php`) loads only the Composer autoloader — no WordPress bootstrap is needed.

### Test Categories

| Test | What it covers |
|------|---------------|
| `ConfigurationTest` | Configuration normalization, dot-notation access, defaults |
| `EnvironmentTest` | Platform detection, Lambda setup |
| `PathValidatorTest` | Security validation (traversal, null bytes, symlinks) |
| `SecurityProcessorTest` | Blocked patterns, path validation integration |
| `TrailingSlashProcessorTest` | Directory redirect behavior |
| `DirectoryProcessorTest` | Index file resolution, directory listing |
| `PhpFileProcessorTest` | PHP file detection and SCRIPT_* setup |
| `MultisiteProcessorTest` | URL rewriting for multisite |
| `WordPressProcessorTest` | WordPress fallback routing |
| `ExceptionTest` | Exception hierarchy and error codes |

## Writing Custom Processor Tests

```php
use PHPUnit\Framework\TestCase;
use WpPack\Component\Handler\Configuration;
use WpPack\Component\HttpFoundation\Request;

final class MyProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $request = Request::create('/path');
        $config = new Configuration(['web_root' => sys_get_temp_dir()]);

        $processor = new MyProcessor();
        $result = $processor->process($request, $config);

        // null = continue, Response = stop, Request = modified
        self::assertNull($result);
    }
}
```
