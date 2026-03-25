<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException as SymfonyServiceNotFoundException;
use WpPack\Component\DependencyInjection\Exception\ServiceNotFoundException;

final class Container implements ContainerInterface
{
    public function __construct(
        private readonly ContainerInterface $symfonyContainer,
    ) {}

    public function get(string $id): mixed
    {
        try {
            return $this->symfonyContainer->get($id);
        } catch (SymfonyServiceNotFoundException $e) {
            throw new ServiceNotFoundException($id);
        }
    }

    public function has(string $id): bool
    {
        return $this->symfonyContainer->has($id);
    }
}
