<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\AdminDataCollector;

final class AdminDataCollectorTest extends TestCase
{
    private AdminDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new AdminDataCollector();
    }

    #[Test]
    public function getNameReturnsAdmin(): void
    {
        self::assertSame('admin', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsAdmin(): void
    {
        self::assertSame('Admin', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        if (function_exists('is_admin')) {
            self::markTestSkipped('WordPress functions are available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertFalse($data['is_admin']);
        self::assertSame('', $data['page_hook']);
        self::assertSame([], $data['screen']);
        self::assertSame([], $data['admin_menus']);
        self::assertSame([], $data['admin_bar_nodes']);
        self::assertSame(0, $data['total_menus']);
        self::assertSame(0, $data['total_submenus']);
    }

    #[Test]
    public function getBadgeValueReturnsScreenIdWhenAdmin(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_admin' => true,
            'screen' => ['id' => 'edit-post'],
            'page_hook' => 'edit.php',
        ]);

        self::assertSame('edit-post', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenNotAdmin(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_admin' => false,
        ]);

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['is_admin' => true]);
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }
}
