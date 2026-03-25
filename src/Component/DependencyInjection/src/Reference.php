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

use Symfony\Component\DependencyInjection\Reference as SymfonyReference;

final class Reference
{
    public function __construct(
        private readonly string $id,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function toSymfony(): SymfonyReference
    {
        return new SymfonyReference($this->id);
    }

    public static function fromSymfony(SymfonyReference $reference): self
    {
        return new self((string) $reference);
    }
}
