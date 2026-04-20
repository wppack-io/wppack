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

namespace WPPack\Plugin\PasskeyLoginPlugin\DependencyInjection;

use Psr\Log\LoggerInterface;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\SchemaManager;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WPPack\Component\Option\OptionManager;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WPPack\Component\Security\Bridge\Passkey\Controller\AuthenticationController;
use WPPack\Component\Security\Bridge\Passkey\Controller\CredentialController;
use WPPack\Component\Security\Bridge\Passkey\Controller\RegistrationController;
use WPPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WPPack\Component\Security\Bridge\Passkey\Storage\DatabaseCredentialRepository;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Component\Transient\TransientManager;
use WPPack\Plugin\PasskeyLoginPlugin\Activation\PasskeyActivationController;
use WPPack\Plugin\PasskeyLoginPlugin\Activation\PasskeyActivationPrompt;
use WPPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsController;
use WPPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsPage;
use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;
use WPPack\Plugin\PasskeyLoginPlugin\LoginForm\PasskeyLoginForm;
use WPPack\Plugin\PasskeyLoginPlugin\Migration\PasskeyCredentialTable;
use WPPack\Plugin\PasskeyLoginPlugin\Profile\PasskeyProfileSection;

final class PasskeyLoginPluginServiceProvider implements ServiceProviderInterface
{
    /**
     * Register admin/settings services (always, regardless of enabled state).
     */
    public function registerAdmin(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(AdminPageRegistry::class)) {
            $builder->register(AdminPageRegistry::class);
        }

        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        $builder->register(PasskeyLoginSettingsPage::class);

        if (!$builder->hasDefinition(BlogContextInterface::class)) {
            $builder->register(BlogContextInterface::class, BlogContext::class);
        }

        if (!$builder->hasDefinition(OptionManager::class)) {
            $builder->register(OptionManager::class);
        }

        $builder->register(PasskeyLoginSettingsController::class)
            ->addArgument(new Reference(BlogContextInterface::class))
            ->addArgument(new Reference(OptionManager::class));

        $builder->register(PasskeyProfileSection::class)
            ->addArgument(new Reference(PasskeyLoginConfiguration::class));
    }

    public function register(ContainerBuilder $builder): void
    {
        // Logger
        if (!$builder->hasDefinition(LoggerInterface::class)) {
            (new LoggerServiceProvider())->register($builder);
        }

        // Database Manager
        if (!$builder->hasDefinition(DatabaseManager::class)) {
            $builder->register(DatabaseManager::class);
        }

        // Transient Manager
        if (!$builder->hasDefinition(TransientManager::class)) {
            $builder->register(TransientManager::class);
        }

        // Authentication Session
        if (!$builder->hasDefinition(AuthenticationSession::class)) {
            $builder->register(AuthenticationSession::class);
        }

        // Blog Context
        if (!$builder->hasDefinition(BlogContextInterface::class)) {
            $builder->register(BlogContextInterface::class, BlogContext::class);
        }

        // REST Registry
        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        // Request
        if (!$builder->hasDefinition(Request::class)) {
            $builder->register(Request::class)
                ->setFactory([Request::class, 'createFromGlobals']);
        }

        // Configuration (constant > wp_options > env > default)
        $builder->register(PasskeyLoginConfiguration::class)
            ->setFactory([PasskeyLoginConfiguration::class, 'fromEnvironmentOrOptions']);

        // Passkey Configuration (bridge)
        $builder->register(PasskeyConfiguration::class)
            ->setFactory([self::class, 'createPasskeyConfiguration'])
            ->addArgument(new Reference(PasskeyLoginConfiguration::class));

        // Credential Repository
        $builder->register(DatabaseCredentialRepository::class)
            ->addArgument(new Reference(DatabaseManager::class));
        $builder->setAlias(CredentialRepositoryInterface::class, DatabaseCredentialRepository::class);

        // Ceremony Manager
        $builder->register(CeremonyManager::class)
            ->addArgument(new Reference(PasskeyConfiguration::class))
            ->addArgument(new Reference(CredentialRepositoryInterface::class))
            ->addArgument(new Reference(TransientManager::class))
            ->addArgument(new Reference(BlogContextInterface::class));

        // Authentication Controller
        $builder->register(AuthenticationController::class)
            ->addArgument(new Reference(CeremonyManager::class))
            ->addArgument(new Reference(CredentialRepositoryInterface::class))
            ->addArgument(new Reference(PasskeyConfiguration::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(LoggerInterface::class))
            ->addArgument(new Reference(BlogContextInterface::class));

        // Registration Controller
        $builder->register(RegistrationController::class)
            ->addArgument(new Reference(CeremonyManager::class))
            ->addArgument(new Reference(CredentialRepositoryInterface::class))
            ->addArgument(new Reference(PasskeyConfiguration::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(LoggerInterface::class))
            ->addArgument(new Reference(BlogContextInterface::class));

        // Credential Controller
        $builder->register(CredentialController::class)
            ->addArgument(new Reference(CredentialRepositoryInterface::class))
            ->addArgument(new Reference(AuthenticationSession::class));

        // Login Form
        $builder->register(PasskeyLoginForm::class)
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(Request::class))
            ->addArgument(new Reference(PasskeyLoginConfiguration::class));

        // Activation Prompt + Controller
        $builder->register(PasskeyActivationPrompt::class)
            ->addArgument(new Reference(TransientManager::class));

        $builder->register(PasskeyActivationController::class)
            ->addArgument(new Reference(CeremonyManager::class))
            ->addArgument(new Reference(CredentialRepositoryInterface::class))
            ->addArgument(new Reference(PasskeyConfiguration::class))
            ->addArgument(new Reference(PasskeyActivationPrompt::class))
            ->addArgument(new Reference(LoggerInterface::class))
            ->addArgument(new Reference(BlogContextInterface::class));

        // DB Migration Table
        $builder->register(PasskeyCredentialTable::class);

        // Schema Manager
        $builder->register(SchemaManager::class)
            ->addArgument(new Reference(DatabaseManager::class))
            ->addArgument([new Reference(PasskeyCredentialTable::class)]);
    }

    public static function createPasskeyConfiguration(PasskeyLoginConfiguration $config): PasskeyConfiguration
    {
        return new PasskeyConfiguration(
            rpName: $config->rpName,
            rpId: $config->rpId,
            timeout: $config->timeout,
            attestation: $config->attestation,
            userVerification: $config->requireUserVerification,
            residentKey: $config->residentKey,
            algorithms: $config->algorithms,
            authenticatorAttachment: $config->authenticatorAttachment,
            maxCredentialsPerUser: $config->maxCredentialsPerUser,
        );
    }
}
