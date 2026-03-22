<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Kernel\PluginInterface;
use WpPack\Component\Mailer\DependencyInjection\RegisterTransportFactoriesPass;
use WpPack\Component\Mailer\Mailer;
use WpPack\Plugin\AmazonMailerPlugin\AmazonMailerPlugin;

#[CoversClass(AmazonMailerPlugin::class)]
final class AmazonMailerPluginTest extends TestCase
{
    private AmazonMailerPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new AmazonMailerPlugin();
    }

    #[Test]
    public function implementsPluginInterface(): void
    {
        self::assertInstanceOf(PluginInterface::class, $this->plugin);
    }

    #[Test]
    public function getCompilerPassesReturnsHookAndTransportPasses(): void
    {
        $passes = $this->plugin->getCompilerPasses();

        self::assertCount(2, $passes);

        $classes = array_map(static fn(object $pass): string => $pass::class, $passes);

        self::assertContains(RegisterHookSubscribersPass::class, $classes);
        self::assertContains(RegisterTransportFactoriesPass::class, $classes);
    }

    #[Test]
    public function registerDelegatesToServiceProvider(): void
    {
        $builder = new ContainerBuilder();

        $this->plugin->register($builder);

        self::assertTrue($builder->hasDefinition(Mailer::class));
        self::assertTrue($builder->hasDefinition(\WpPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration::class));
        self::assertTrue($builder->hasDefinition(\WpPack\Plugin\AmazonMailerPlugin\Handler\BounceHandler::class));
        self::assertTrue($builder->hasDefinition(\WpPack\Plugin\AmazonMailerPlugin\Handler\ComplaintHandler::class));
    }

    #[Test]
    public function bootCallsMailerBoot(): void
    {
        Mailer::reset();

        $mailer = new Mailer('null://default');

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(Mailer::class, $mailer);
        $container = new \WpPack\Component\DependencyInjection\Container($symfonyContainer);

        $this->plugin->boot($container);

        // Verify that the wp_mail filter was registered by boot()
        self::assertNotFalse(has_filter('wp_mail', [$mailer, 'onWpMail']));

        Mailer::reset();
    }

    #[Test]
    public function onActivateDoesNotThrow(): void
    {
        $this->plugin->onActivate();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function onDeactivateDoesNotThrow(): void
    {
        $this->plugin->onDeactivate();

        $this->addToAssertionCount(1);
    }
}
