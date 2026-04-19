<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\Dumper\PhpDumper as SymfonyPhpDumper;
use WPPack\Component\DependencyInjection\ContainerBuilder;

class PhpDumper
{
    private readonly SymfonyPhpDumper $dumper;

    public function __construct(ContainerBuilder $builder)
    {
        $this->dumper = new SymfonyPhpDumper($builder->getSymfonyBuilder());
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
