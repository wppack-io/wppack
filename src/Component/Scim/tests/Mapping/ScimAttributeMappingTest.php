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

namespace WPPack\Component\Scim\Tests\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Mapping\ScimAttributeMapping;

#[CoversClass(ScimAttributeMapping::class)]
final class ScimAttributeMappingTest extends TestCase
{
    #[Test]
    public function bindsScimPathToUserMetaKey(): void
    {
        $mapping = new ScimAttributeMapping(
            scimPath: 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User:employeeNumber',
            metaKey: '_wppack_scim_employee_number',
        );

        self::assertSame(
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User:employeeNumber',
            $mapping->scimPath,
        );
        self::assertSame('_wppack_scim_employee_number', $mapping->metaKey);
    }
}
