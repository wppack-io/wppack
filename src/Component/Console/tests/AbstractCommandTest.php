<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\AbstractCommand;
use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\Exception\LogicException;
use WpPack\Component\Console\Input\ArrayInput;
use WpPack\Component\Console\Input\InputArgument;
use WpPack\Component\Console\Input\InputDefinition;
use WpPack\Component\Console\Input\InputInterface;
use WpPack\Component\Console\Input\InputOption;
use WpPack\Component\Console\Output\BufferedOutput;
use WpPack\Component\Console\Output\OutputStyle;

final class AbstractCommandTest extends TestCase
{
    #[Test]
    public function getCommandAttribute(): void
    {
        $attribute = TestGreetCommand::getCommandAttribute();

        self::assertSame('test greet', $attribute->name);
        self::assertSame('Greet someone', $attribute->description);
        self::assertFalse($attribute->hidden);
    }

    #[Test]
    public function getCommandAttributeThrowsWithoutAttribute(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must have the #[AsCommand] attribute');

        CommandWithoutAttribute::getCommandAttribute();
    }

    #[Test]
    public function getDefinitionCallsConfigure(): void
    {
        $command = new TestGreetCommand();
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasArgument('name'));
        self::assertTrue($definition->hasOption('loud'));
    }

    #[Test]
    public function getDefinitionIsCached(): void
    {
        $command = new TestGreetCommand();

        $definition1 = $command->getDefinition();
        $definition2 = $command->getDefinition();

        self::assertSame($definition1, $definition2);
    }

    #[Test]
    public function executeReturnsSuccess(): void
    {
        $command = new TestGreetCommand();
        $input = new ArrayInput(['name' => 'World'], ['loud' => false]);
        $output = new OutputStyle(new BufferedOutput());

        $exitCode = $command->run($input, $output);

        self::assertSame(AbstractCommand::SUCCESS, $exitCode);
    }

    #[Test]
    public function executeOutputsMessage(): void
    {
        $command = new TestGreetCommand();
        $input = new ArrayInput(['name' => 'World'], ['loud' => true]);
        $buffer = new BufferedOutput();
        $output = new OutputStyle($buffer);

        $command->run($input, $output);

        self::assertStringContainsString('HELLO WORLD!', $buffer->getBuffer());
    }

    #[Test]
    public function exitCodeConstants(): void
    {
        self::assertSame(0, AbstractCommand::SUCCESS);
        self::assertSame(1, AbstractCommand::FAILURE);
        self::assertSame(2, AbstractCommand::INVALID);
    }
}

#[AsCommand(name: 'test greet', description: 'Greet someone')]
final class TestGreetCommand extends AbstractCommand
{
    protected function configure(InputDefinition $definition): void
    {
        $definition
            ->addArgument(new InputArgument('name', InputArgument::REQUIRED, 'Who to greet'))
            ->addOption(new InputOption('loud', InputOption::VALUE_NONE, 'Shout the greeting'));
    }

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        $name = $input->getArgument('name');

        if ($input->getOption('loud')) {
            $output->success(strtoupper("Hello {$name}!"));
        } else {
            $output->info("Hello {$name}!");
        }

        return self::SUCCESS;
    }
}

final class CommandWithoutAttribute extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::SUCCESS;
    }
}
