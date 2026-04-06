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

namespace WpPack\Plugin\PasskeyLoginPlugin\DependencyInjection;

use Psr\Log\LoggerInterface;
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\SchemaManager;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WpPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WpPack\Component\Security\Bridge\Passkey\Controller\AuthenticationController;
use WpPack\Component\Security\Bridge\Passkey\Controller\CredentialController;
use WpPack\Component\Security\Bridge\Passkey\Controller\RegistrationController;
use WpPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WpPack\Component\Security\Bridge\Passkey\Storage\DatabaseCredentialRepository;
use WpPack\Component\Transient\TransientManager;
use WpPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;
use WpPack\Plugin\PasskeyLoginPlugin\LoginForm\PasskeyLoginForm;
use WpPack\Plugin\PasskeyLoginPlugin\Migration\PasskeyCredentialTable;

final class PasskeyLoginPluginServiceProvider implements ServiceProviderInterface
{
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
            ->addArgument(new Reference(TransientManager::class));

        // Authentication Controller
        $builder->register(AuthenticationController::class)
            ->addArgument(new Reference(CeremonyManager::class))
            ->addArgument(new Reference(CredentialRepositoryInterface::class))
            ->addArgument(new Reference(PasskeyConfiguration::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(LoggerInterface::class));

        // Registration Controller
        $builder->register(RegistrationController::class)
            ->addArgument(new Reference(CeremonyManager::class))
            ->addArgument(new Reference(CredentialRepositoryInterface::class))
            ->addArgument(new Reference(PasskeyConfiguration::class))
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(LoggerInterface::class));

        // Credential Controller
        $builder->register(CredentialController::class)
            ->addArgument(new Reference(CredentialRepositoryInterface::class))
            ->addArgument(new Reference(AuthenticationSession::class));

        // Login Form
        $builder->register(PasskeyLoginForm::class)
            ->addArgument(new Reference(AuthenticationSession::class))
            ->addArgument(new Reference(Request::class));

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
            userVerification: $config->requireUserVerification,
        );
    }
}
