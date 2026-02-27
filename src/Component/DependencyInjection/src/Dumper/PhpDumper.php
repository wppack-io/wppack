<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper as SymfonyPhpDumper;

final class PhpDumper
{
    private readonly SymfonyPhpDumper $dumper;

    public function __construct(SymfonyContainerBuilder $container)
    {
        $this->dumper = new SymfonyPhpDumper($container);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function dump(array $options = []): string
    {
        $result = $this->dumper->dump($options);
        \assert(\is_string($result));

        return $result;
    }
}
