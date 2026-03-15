# WpPack Command Component

A WP-CLI command framework providing type-safe input/output, automatic command registration via DI, and a Symfony Console-inspired `configure()` + `execute()` pattern.

## Installation

```bash
composer require wppack/command
```

## Usage

```php
use WpPack\Component\Command\AbstractCommand;
use WpPack\Component\Command\Attribute\AsCommand;
use WpPack\Component\Command\Input\InputArgument;
use WpPack\Component\Command\Input\InputDefinition;
use WpPack\Component\Command\Input\InputInterface;
use WpPack\Component\Command\Input\InputOption;
use WpPack\Component\Command\Output\OutputStyle;

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

## Documentation

Full documentation is available at [docs/components/command.md](../../docs/components/command.md).

## License

MIT
