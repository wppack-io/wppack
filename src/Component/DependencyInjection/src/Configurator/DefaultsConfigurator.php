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

namespace WpPack\Component\DependencyInjection\Configurator;

final class DefaultsConfigurator
{
    private bool $autowire = false;
    private bool $public = false;

    public function autowire(bool $autowire = true): self
    {
        $this->autowire = $autowire;

        return $this;
    }

    public function public(bool $public = true): self
    {
        $this->public = $public;

        return $this;
    }

    public function isAutowire(): bool
    {
        return $this->autowire;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }
}
