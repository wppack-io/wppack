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

namespace WpPack\Plugin\S3StoragePlugin\Tests\PreSignedUrl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;

final class UploadPolicyTest extends TestCase
{
    #[Test]
    public function isAllowedTypeWithExplicitMimeTypes(): void
    {
        $policy = new UploadPolicy(
            allowedMimeTypes: ['image/jpeg', 'image/png', 'application/pdf'],
        );

        self::assertTrue($policy->isAllowedType('image/jpeg'));
        self::assertTrue($policy->isAllowedType('image/png'));
        self::assertTrue($policy->isAllowedType('application/pdf'));
        self::assertFalse($policy->isAllowedType('text/html'));
        self::assertFalse($policy->isAllowedType('application/javascript'));
    }

    #[Test]
    public function isAllowedTypeWithEmptyListAllowsAll(): void
    {
        $policy = new UploadPolicy(
            allowedMimeTypes: [],
        );

        self::assertTrue($policy->isAllowedType('image/jpeg'));
        self::assertTrue($policy->isAllowedType('text/html'));
        self::assertTrue($policy->isAllowedType('application/javascript'));
    }

    #[Test]
    #[DataProvider('validSizeProvider')]
    public function isAllowedSizeWithValidSizes(int $size): void
    {
        $policy = new UploadPolicy(maxFileSize: 10 * 1024 * 1024);

        self::assertTrue($policy->isAllowedSize($size));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function validSizeProvider(): iterable
    {
        yield 'small file' => [1024];
        yield 'exactly max' => [10 * 1024 * 1024];
        yield 'one byte' => [1];
    }

    #[Test]
    #[DataProvider('invalidSizeProvider')]
    public function isAllowedSizeWithInvalidSizes(int $size): void
    {
        $policy = new UploadPolicy(maxFileSize: 10 * 1024 * 1024);

        self::assertFalse($policy->isAllowedSize($size));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidSizeProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'exceeds max' => [10 * 1024 * 1024 + 1];
        yield 'way too large' => [1_000_000_000];
    }

    #[Test]
    public function getMaxFileSizeReturnsDefaultWhenNotSpecified(): void
    {
        $policy = new UploadPolicy();

        self::assertSame(100 * 1024 * 1024, $policy->getMaxFileSize());
    }

    #[Test]
    public function getMaxFileSizeReturnsCustomValue(): void
    {
        $policy = new UploadPolicy(maxFileSize: 50 * 1024 * 1024);

        self::assertSame(50 * 1024 * 1024, $policy->getMaxFileSize());
    }

    #[Test]
    public function getAllowedMimeTypesReturnsExplicitList(): void
    {
        $mimeTypes = ['image/jpeg', 'image/png'];
        $policy = new UploadPolicy(allowedMimeTypes: $mimeTypes);

        self::assertSame($mimeTypes, $policy->getAllowedMimeTypes());
    }

    #[Test]
    public function getAllowedMimeTypesReturnsWordPressDefaults(): void
    {
        $policy = new UploadPolicy();

        $expected = array_values(array_unique(get_allowed_mime_types()));
        self::assertSame($expected, $policy->getAllowedMimeTypes());
    }
}
