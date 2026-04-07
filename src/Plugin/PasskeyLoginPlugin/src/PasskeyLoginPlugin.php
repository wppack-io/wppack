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

namespace WpPack\Plugin\PasskeyLoginPlugin;

use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\Database\SchemaManager;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\Component\Kernel\Attribute\TextDomain;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Security\Bridge\Passkey\Controller\AuthenticationController;
use WpPack\Plugin\PasskeyLoginPlugin\Activation\PasskeyActivationController;
use WpPack\Plugin\PasskeyLoginPlugin\Activation\PasskeyActivationPrompt;
use WpPack\Component\Security\Bridge\Passkey\Controller\CredentialController;
use WpPack\Component\Security\Bridge\Passkey\Controller\RegistrationController;
use WpPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsController;
use WpPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsPage;
use WpPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;
use WpPack\Plugin\PasskeyLoginPlugin\DependencyInjection\PasskeyLoginPluginServiceProvider;
use WpPack\Plugin\PasskeyLoginPlugin\LoginForm\PasskeyLoginForm;
use WpPack\Plugin\PasskeyLoginPlugin\Profile\PasskeyProfileSection;

#[TextDomain(domain: 'wppack-passkey-login')]
final class PasskeyLoginPlugin extends AbstractPlugin
{
    private const SCHEMA_VERSION = 1;
    private const SCHEMA_VERSION_OPTION = 'wppack_passkey_login_schema_version';

    private readonly PasskeyLoginPluginServiceProvider $serviceProvider;
    private ?SchemaManager $schemaManager = null;

    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
        $this->serviceProvider = new PasskeyLoginPluginServiceProvider();
    }

    public function register(ContainerBuilder $builder): void
    {
        // Always register admin/settings services
        $this->serviceProvider->registerAdmin($builder);

        $this->serviceProvider->register($builder);
    }

    public function boot(Container $container): void
    {
        /** @var SchemaManager $schemaManager */
        $schemaManager = $container->get(SchemaManager::class);
        $this->schemaManager = $schemaManager;

        // Auto-migrate schema if version mismatch (handles deployment without re-activation)
        $currentVersion = (int) get_site_option(self::SCHEMA_VERSION_OPTION, 0);
        if ($currentVersion < self::SCHEMA_VERSION) {
            $schemaManager->updateSchema();
            update_site_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION);
        }

        /** @var AdminPageRegistry $pageRegistry */
        $pageRegistry = $container->get(AdminPageRegistry::class);
        /** @var PasskeyLoginSettingsPage $settingsPage */
        $settingsPage = $container->get(PasskeyLoginSettingsPage::class);
        $settingsPage->setPluginFile($this->getFile());
        $pageRegistry->register($settingsPage, $this->isNetworkActivated());

        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(PasskeyLoginSettingsController::class));

        // Profile passkey management section
        /** @var PasskeyProfileSection $profileSection */
        $profileSection = $container->get(PasskeyProfileSection::class);
        $profileSection->setPluginFile($this->getFile());
        $profileSection->register();

        /** @var PasskeyLoginConfiguration $config */
        $config = $container->get(PasskeyLoginConfiguration::class);

        if (!$config->enabled) {
            return;
        }

        // REST API controllers (passkey ceremony endpoints)
        /** @var RestRegistry $restRegistry */
        $restRegistry = $container->get(RestRegistry::class);
        $restRegistry->register($container->get(AuthenticationController::class));
        $restRegistry->register($container->get(RegistrationController::class));
        $restRegistry->register($container->get(CredentialController::class));

        // Login form integration
        /** @var PasskeyLoginForm $loginForm */
        $loginForm = $container->get(PasskeyLoginForm::class);
        $loginForm->register();

        // Activation prompt (wp-activate.php passkey setup)
        if ($config->allowSignup) {
            /** @var PasskeyActivationPrompt $activationPrompt */
            $activationPrompt = $container->get(PasskeyActivationPrompt::class);
            $activationPrompt->register();

            $restRegistry->register($container->get(PasskeyActivationController::class));
        }
    }

    public function onActivate(): void
    {
        $this->schemaManager?->updateSchema();
    }
}
