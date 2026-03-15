# WpPack Console

A WP-CLI command framework providing type-safe input/output, automatic command registration via DI, and a Symfony Console-inspired `configure()` + `execute()` pattern.

## Installation

```bash
composer require wppack/console
```

## Usage

### Command Definition

```php
use WpPack\Component\Console\AbstractCommand;
use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\Input\InputArgument;
use WpPack\Component\Console\Input\InputDefinition;
use WpPack\Component\Console\Input\InputInterface;
use WpPack\Component\Console\Input\InputOption;
use WpPack\Component\Console\Output\OutputStyle;

#[AsCommand(name: 'myapp import-users', description: 'Import users from CSV')]
final class ImportUsersCommand extends AbstractCommand
{
    protected function configure(InputDefinition $definition): void
    {
        $definition
            ->addArgument(new InputArgument('file', InputArgument::REQUIRED, 'CSV file path'))
            ->addOption(new InputOption('role', InputOption::VALUE_OPTIONAL, 'Default role', 'subscriber'))
            ->addOption(new InputOption('skip-email', InputOption::VALUE_NONE, 'Skip welcome emails'));
    }

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        $file = $input->getArgument('file');
        $output->info("Importing from {$file}...");

        // ...

        $output->success('Import complete.');
        return self::SUCCESS;
    }
}
```

### Command with Dependency Injection

```php
#[AsCommand(name: 'myapp sync-users', description: 'Sync users from external API')]
final class SyncUsersCommand extends AbstractCommand
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly HttpClientInterface $httpClient,
    ) {}

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        $output->info('Syncing users...');
        // Dependencies are injected via the DI container
        $output->success('Users synced.');
        return self::SUCCESS;
    }
}
```

### Input Definition

```php
use WpPack\Component\Console\Input\InputArgument;
use WpPack\Component\Console\Input\InputOption;

// Required argument
$definition->addArgument(new InputArgument('file', InputArgument::REQUIRED, 'CSV file path'));

// Optional argument with default
$definition->addArgument(new InputArgument('format', InputArgument::OPTIONAL, 'Output format', 'json'));

// Array argument
$definition->addArgument(new InputArgument('files', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Files'));

// Flag (--verbose)
$definition->addOption(new InputOption('verbose', InputOption::VALUE_NONE, 'Verbose output'));

// Option with required value (--format=csv)
$definition->addOption(new InputOption('format', InputOption::VALUE_REQUIRED, 'Output format'));

// Option with optional value (--role[=editor])
$definition->addOption(new InputOption('role', InputOption::VALUE_OPTIONAL, 'User role', 'subscriber'));
```

### Output Methods

```php
$output->info('Informational message');
$output->success('Success message');
$output->warning('Warning message');
$output->error('Error message');
$output->line('Plain text');
$output->newLine();

// Table
$output->table(['ID', 'Name'], [['1', 'Alice'], ['2', 'Bob']]);

// Progress bar
$progress = $output->progress(count($items), 'Processing');
foreach ($items as $item) {
    $progress->advance();
}
$progress->finish();
```

### CommandRegistry

```php
use WpPack\Component\Console\CommandRegistry;

$registry = new CommandRegistry();
$registry->add(new ImportUsersCommand());
$registry->register(); // Calls WP_CLI::add_command() for each command
```

### Testing

```php
use WpPack\Component\Console\Input\ArrayInput;
use WpPack\Component\Console\Output\BufferedOutput;
use WpPack\Component\Console\Output\OutputStyle;

$input = new ArrayInput(
    arguments: ['file' => '/path/to/users.csv'],
    options: ['role' => 'editor', 'skip-email' => true],
);
$buffer = new BufferedOutput();
$output = new OutputStyle($buffer);

$exitCode = $command->run($input, $output);

self::assertSame(AbstractCommand::SUCCESS, $exitCode);
self::assertStringContainsString('Import complete', $buffer->getBuffer());
```

## Documentation

See [docs/components/console/](../../../docs/components/console/) for details.

## License

MIT
