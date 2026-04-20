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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WPPack\Component\Security\Bridge\Passkey\Controller\AuthenticationController;
use WPPack\Component\Security\Bridge\Passkey\Controller\CredentialController;
use WPPack\Component\Security\Bridge\Passkey\Controller\RegistrationController;
use WPPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WPPack\Component\Security\Bridge\Passkey\Storage\DatabaseCredentialRepository;
use WPPack\Plugin\PasskeyLoginPlugin\Activation\PasskeyActivationController;
use WPPack\Plugin\PasskeyLoginPlugin\Activation\PasskeyActivationPrompt;
use WPPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsController;
use WPPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsPage;
use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;
use WPPack\Plugin\PasskeyLoginPlugin\DependencyInjection\PasskeyLoginPluginServiceProvider;
use WPPack\Plugin\PasskeyLoginPlugin\LoginForm\PasskeyLoginForm;
use WPPack\Plugin\PasskeyLoginPlugin\Migration\PasskeyCredentialTable;
use WPPack\Plugin\PasskeyLoginPlugin\Profile\PasskeyProfileSection;

#[CoversClass(PasskeyLoginPluginServiceProvider::class)]
final class PasskeyLoginPluginServiceProviderTest extends TestCase
{
    #[Test]
    public function registerAdminRegistersSettingsAndProfileServices(): void
    {
        $builder = new ContainerBuilder();

        (new PasskeyLoginPluginServiceProvider())->registerAdmin($builder);

        self::assertTrue($builder->hasDefinition(AdminPageRegistry::class));
        self::assertTrue($builder->hasDefinition(PasskeyLoginSettingsPage::class));
        self::assertTrue($builder->hasDefinition(PasskeyLoginSettingsController::class));
        self::assertTrue($builder->hasDefinition(PasskeyProfileSection::class));
    }

    #[Test]
    public function registerProducesCompleteServiceGraph(): void
    {
        $builder = new ContainerBuilder();

        (new PasskeyLoginPluginServiceProvider())->register($builder);

        foreach ([
            DatabaseManager::class,
            PasskeyLoginConfiguration::class,
            PasskeyConfiguration::class,
            DatabaseCredentialRepository::class,
            CeremonyManager::class,
            AuthenticationController::class,
            RegistrationController::class,
            CredentialController::class,
            PasskeyLoginForm::class,
            PasskeyActivationPrompt::class,
            PasskeyActivationController::class,
            PasskeyCredentialTable::class,
        ] as $id) {
            self::assertTrue($builder->hasDefinition($id), "missing: {$id}");
        }
    }

    #[Test]
    public function credentialRepositoryInterfaceAliasPointsToConcreteRepo(): void
    {
        $builder = new ContainerBuilder();

        (new PasskeyLoginPluginServiceProvider())->register($builder);

        $symfony = $builder->getSymfonyBuilder();
        self::assertSame(
            DatabaseCredentialRepository::class,
            (string) $symfony->getAlias(CredentialRepositoryInterface::class),
        );
    }

    #[Test]
    public function preExistingServicesAreReused(): void
    {
        $builder = new ContainerBuilder();
        $existing = $builder->register(DatabaseManager::class);

        (new PasskeyLoginPluginServiceProvider())->register($builder);

        self::assertSame($existing, $builder->findDefinition(DatabaseManager::class));
    }

    #[Test]
    public function createPasskeyConfigurationCopiesFieldsFromPluginConfig(): void
    {
        $plugin = new PasskeyLoginConfiguration(
            rpName: 'My Site',
            rpId: 'example.test',
            requireUserVerification: 'required',
            algorithms: [-7, -257, -8],
            attestation: 'direct',
            authenticatorAttachment: 'platform',
            timeout: 45000,
            residentKey: 'preferred',
            maxCredentialsPerUser: 5,
        );

        $bridge = PasskeyLoginPluginServiceProvider::createPasskeyConfiguration($plugin);

        self::assertSame('My Site', $bridge->rpName);
        self::assertSame('example.test', $bridge->rpId);
        self::assertSame(45000, $bridge->timeout);
        self::assertSame('direct', $bridge->attestation);
        self::assertSame('required', $bridge->userVerification);
        self::assertSame('preferred', $bridge->residentKey);
        self::assertSame([-7, -257, -8], $bridge->algorithms);
        self::assertSame('platform', $bridge->authenticatorAttachment);
        self::assertSame(5, $bridge->maxCredentialsPerUser);
    }
}
