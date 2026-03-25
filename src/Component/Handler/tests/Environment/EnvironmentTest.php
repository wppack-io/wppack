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

namespace WpPack\Component\Handler\Tests\Environment;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Environment\Environment;

final class EnvironmentTest extends TestCase
{
    #[Test]
    public function standardPlatformByDefault(): void
    {
        $env = new Environment(new Configuration());

        self::assertFalse($env->isLambda());
        self::assertSame('standard', $env->getInfo()['platform']);
    }

    #[Test]
    public function forceLambdaEnabled(): void
    {
        $env = new Environment(new Configuration(['lambda' => true]));

        self::assertTrue($env->isLambda());
        self::assertSame('lambda', $env->getInfo()['platform']);
    }

    #[Test]
    public function forceLambdaDisabled(): void
    {
        $env = new Environment(new Configuration(['lambda' => false]));

        self::assertFalse($env->isLambda());
    }

    #[Test]
    public function setupDoesNotThrowOnStandard(): void
    {
        $env = new Environment(new Configuration());
        $env->setup();

        $this->addToAssertionCount(1);
    }
}
