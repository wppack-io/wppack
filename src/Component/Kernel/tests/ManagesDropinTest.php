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

namespace WPPack\Component\Kernel\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Kernel\ManagesDropin;

#[CoversClass(ManagesDropin::class)]
final class ManagesDropinTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function setUp(): void
    {
        if (!\defined('WP_CONTENT_DIR')) {
            self::markTestSkipped('WP_CONTENT_DIR must be defined.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            }
        }
        $this->cleanup = [];
    }

    /**
     * Build an anonymous class that uses the trait.
     */
    private function subject(string $filename, string $signature, ?string $source): object
    {
        return new class ($filename, $signature, $source) {
            use ManagesDropin {
                installDropin as public;
                uninstallDropin as public;
            }

            public function __construct(
                private readonly string $filename,
                private readonly string $signature,
                private readonly ?string $source,
            ) {}

            private function getDropinFilename(): string
            {
                return $this->filename;
            }

            private function getDropinSignature(): string
            {
                return $this->signature;
            }

            private function resolveDropinSource(): ?string
            {
                return $this->source;
            }
        };
    }

    /**
     * Unique filename so tests don't collide with each other or real dropins.
     */
    private function uniqueFilename(string $prefix): string
    {
        $filename = $prefix . '_' . uniqid() . '.php';
        $this->cleanup[] = WP_CONTENT_DIR . '/' . $filename;

        return $filename;
    }

    private function createTempSource(string $body): string
    {
        $path = sys_get_temp_dir() . '/wppack_dropin_source_' . uniqid() . '.php';
        file_put_contents($path, $body);
        $this->cleanup[] = $path;

        return $path;
    }

    #[Test]
    public function installDropinCopiesSourceToWpContentDir(): void
    {
        $source = $this->createTempSource("<?php // source marker\n");
        $filename = $this->uniqueFilename('copy_test');
        $destination = WP_CONTENT_DIR . '/' . $filename;

        self::assertFileDoesNotExist($destination);

        $this->subject($filename, 'SIG', $source)->installDropin();

        self::assertFileExists($destination);
        self::assertStringContainsString('source marker', file_get_contents($destination));
    }

    #[Test]
    public function installDropinSkipsWhenDestinationExists(): void
    {
        $source = $this->createTempSource("<?php // new\n");
        $filename = $this->uniqueFilename('skip_test');
        $destination = WP_CONTENT_DIR . '/' . $filename;

        file_put_contents($destination, 'pre-existing content');

        $this->subject($filename, 'SIG', $source)->installDropin();

        self::assertSame('pre-existing content', file_get_contents($destination));
    }

    #[Test]
    public function installDropinNoOpWhenSourceNull(): void
    {
        $filename = $this->uniqueFilename('nosource');
        $destination = WP_CONTENT_DIR . '/' . $filename;

        $this->subject($filename, 'SIG', null)->installDropin();

        self::assertFileDoesNotExist($destination);
    }

    #[Test]
    public function installDropinNoOpWhenSourceFileMissing(): void
    {
        $filename = $this->uniqueFilename('nofile');
        $destination = WP_CONTENT_DIR . '/' . $filename;

        $this->subject($filename, 'SIG', '/never/exists/anywhere.php')->installDropin();

        self::assertFileDoesNotExist($destination);
    }

    #[Test]
    public function uninstallDropinRemovesSymlinkPointingToManagedSource(): void
    {
        $source = $this->createTempSource("<?php // SIG_TOKEN managed\n");
        $filename = $this->uniqueFilename('symlink_test');
        $destination = WP_CONTENT_DIR . '/' . $filename;

        self::assertTrue(@symlink($source, $destination));

        $this->subject($filename, 'SIG_TOKEN', $source)->uninstallDropin();

        self::assertFalse(is_link($destination));
        self::assertFileDoesNotExist($destination);
    }

    #[Test]
    public function uninstallDropinRemovesCopiedFileWhenSignatureMatches(): void
    {
        $filename = $this->uniqueFilename('copy_sig_test');
        $destination = WP_CONTENT_DIR . '/' . $filename;

        file_put_contents($destination, "<?php // SIG_TOKEN managed-copy\n");

        $this->subject($filename, 'SIG_TOKEN', null)->uninstallDropin();

        self::assertFileDoesNotExist($destination);
    }

    #[Test]
    public function uninstallDropinLeavesForeignFileAlone(): void
    {
        $filename = $this->uniqueFilename('foreign_test');
        $destination = WP_CONTENT_DIR . '/' . $filename;

        file_put_contents($destination, "<?php // user's own unrelated content\n");

        $this->subject($filename, 'OUR_SIG_ABSENT', null)->uninstallDropin();

        self::assertFileExists($destination, 'foreign file without our signature must be preserved');
    }

    #[Test]
    public function uninstallDropinNoOpWhenDestinationMissing(): void
    {
        $filename = $this->uniqueFilename('missing_test');

        $this->subject($filename, 'SIG', null)->uninstallDropin();

        self::assertTrue(true, 'no exception thrown');
    }
}
