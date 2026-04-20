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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\SchemaManager;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Kernel\Attribute\TextDomain;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WPPack\Component\Security\Bridge\Passkey\Controller\AuthenticationController;
use WPPack\Component\Security\Bridge\Passkey\Controller\CredentialController;
use WPPack\Component\Security\Bridge\Passkey\Controller\RegistrationController;
use WPPack\Component\Security\Bridge\Passkey\Storage\DatabaseCredentialRepository;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Transient\TransientManager;
use WPPack\Plugin\PasskeyLoginPlugin\Activation\PasskeyActivationController;
use WPPack\Plugin\PasskeyLoginPlugin\Activation\PasskeyActivationPrompt;
use WPPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsController;
use WPPack\Plugin\PasskeyLoginPlugin\Admin\PasskeyLoginSettingsPage;
use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;
use WPPack\Plugin\PasskeyLoginPlugin\LoginForm\PasskeyLoginForm;
use WPPack\Plugin\PasskeyLoginPlugin\PasskeyLoginPlugin;
use WPPack\Plugin\PasskeyLoginPlugin\Profile\PasskeyProfileSection;

#[CoversClass(PasskeyLoginPlugin::class)]
final class PasskeyLoginPluginTest extends TestCase
{
    #[Test]
    public function pluginDeclaresTextDomainAttribute(): void
    {
        $ref = new \ReflectionClass(PasskeyLoginPlugin::class);
        $attributes = $ref->getAttributes(TextDomain::class);

        self::assertNotEmpty($attributes);
        $textDomain = $attributes[0]->newInstance();
        self::assertSame('wppack-passkey-login', $textDomain->domain);
    }

    #[Test]
    public function onActivateDoesNothingWhenSchemaManagerIsNull(): void
    {
        $plugin = new PasskeyLoginPlugin(__FILE__);

        // boot() hasn't been called — SchemaManager is null, onActivate is a no-op
        $plugin->onActivate();

        self::assertTrue(true, 'no exception thrown');
    }

    #[Test]
    public function registerPopulatesBuilderWithPluginServices(): void
    {
        $plugin = new PasskeyLoginPlugin(__FILE__);
        $builder = new ContainerBuilder();

        // Pre-register Request so nested providers that depend on it work
        $builder->register(Request::class);

        $plugin->register($builder);

        // Plugin-level services
        self::assertTrue($builder->hasDefinition(PasskeyLoginConfiguration::class));
    }

    #[Test]
    public function getFileReturnsPluginFilePath(): void
    {
        $pluginFile = '/fake/wppack-passkey-login.php';
        $plugin = new PasskeyLoginPlugin($pluginFile);

        self::assertSame($pluginFile, $plugin->getFile());
    }

    #[Test]
    public function bootSkipsCeremonyEndpointsWhenFeatureDisabled(): void
    {
        // Minimal container covering just the admin + profile + schema
        // surfaces that boot() always walks. When PasskeyLoginConfiguration
        // returns enabled=false, ceremony controllers must not get
        // resolved, so the container never needs them.
        $settingsPage = new PasskeyLoginSettingsPage();
        $settingsController = new PasskeyLoginSettingsController();
        $adminRegistry = new AdminPageRegistry();
        $restRegistry = new RestRegistry(new Request());
        $profileSection = new PasskeyProfileSection(
            new PasskeyLoginConfiguration(enabled: false, rpName: '', rpId: ''),
        );

        // Make the schema manager see a version already up to date so the
        // updateSchema() branch is skipped without touching the database.
        update_site_option('wppack_passkey_login_schema_version', 1);

        $schemaManager = new SchemaManager(new DatabaseManager(), []);

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(SchemaManager::class, $schemaManager);
        $symfonyContainer->set(AdminPageRegistry::class, $adminRegistry);
        $symfonyContainer->set(PasskeyLoginSettingsPage::class, $settingsPage);
        $symfonyContainer->set(RestRegistry::class, $restRegistry);
        $symfonyContainer->set(PasskeyLoginSettingsController::class, $settingsController);
        $symfonyContainer->set(PasskeyProfileSection::class, $profileSection);
        $symfonyContainer->set(
            PasskeyLoginConfiguration::class,
            new PasskeyLoginConfiguration(enabled: false, rpName: '', rpId: ''),
        );

        $container = new Container($symfonyContainer);
        $plugin = new PasskeyLoginPlugin(__FILE__);

        $plugin->boot($container);

        // Admin + REST admin controller always registered
        self::assertNotFalse(has_action('admin_menu') ?: has_action('network_admin_menu'));
        self::assertNotFalse(has_action('rest_api_init'));

        remove_all_actions('admin_menu');
        remove_all_actions('network_admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
        remove_all_actions('show_user_profile');
        remove_all_actions('edit_user_profile');
        delete_site_option('wppack_passkey_login_schema_version');
    }

    #[Test]
    public function bootRegistersCeremonyEndpointsAndActivationWhenEnabledAndAllowSignup(): void
    {
        // Force the schema-version branch (current < target) to run
        // updateSchema() and write the option.
        delete_site_option('wppack_passkey_login_schema_version');

        $config = new PasskeyLoginConfiguration(enabled: true, rpName: 'Example', rpId: 'example.test', allowSignup: true);
        $passkeyConfig = new PasskeyConfiguration(rpId: 'example.test');
        $transients = new TransientManager();

        $databaseManager = new DatabaseManager();
        $schemaManager = new SchemaManager($databaseManager, []);
        $credentialRepo = new DatabaseCredentialRepository($databaseManager);
        $blogContext = new BlogContext();

        $ceremony = new CeremonyManager($passkeyConfig, $credentialRepo, $transients, $blogContext);
        $authenticationController = new AuthenticationController(
            $ceremony,
            $credentialRepo,
            $passkeyConfig,
            new AuthenticationSession(),
            new NullLogger(),
            $blogContext,
        );
        $registrationController = new RegistrationController(
            $ceremony,
            $credentialRepo,
            $passkeyConfig,
            new AuthenticationSession(),
            new NullLogger(),
            $blogContext,
        );
        $credentialController = new CredentialController($credentialRepo, new AuthenticationSession());

        $loginForm = new PasskeyLoginForm(new AuthenticationSession(), new Request(), $config);
        $activationPrompt = new PasskeyActivationPrompt($transients);
        $activationController = new PasskeyActivationController(
            $ceremony,
            $credentialRepo,
            $passkeyConfig,
            $activationPrompt,
            new NullLogger(),
        );

        $symfonyContainer = new \Symfony\Component\DependencyInjection\Container();
        $symfonyContainer->set(SchemaManager::class, $schemaManager);
        $symfonyContainer->set(AdminPageRegistry::class, new AdminPageRegistry());
        $symfonyContainer->set(PasskeyLoginSettingsPage::class, new PasskeyLoginSettingsPage());
        $symfonyContainer->set(RestRegistry::class, new RestRegistry(new Request()));
        $symfonyContainer->set(PasskeyLoginSettingsController::class, new PasskeyLoginSettingsController());
        $symfonyContainer->set(PasskeyProfileSection::class, new PasskeyProfileSection($config));
        $symfonyContainer->set(PasskeyLoginConfiguration::class, $config);
        $symfonyContainer->set(AuthenticationController::class, $authenticationController);
        $symfonyContainer->set(RegistrationController::class, $registrationController);
        $symfonyContainer->set(CredentialController::class, $credentialController);
        $symfonyContainer->set(PasskeyLoginForm::class, $loginForm);
        $symfonyContainer->set(PasskeyActivationPrompt::class, $activationPrompt);
        $symfonyContainer->set(PasskeyActivationController::class, $activationController);

        $container = new Container($symfonyContainer);
        $plugin = new PasskeyLoginPlugin(__FILE__);

        $plugin->boot($container);

        // Schema version was persisted by the migration branch.
        self::assertSame(1, (int) get_site_option('wppack_passkey_login_schema_version', 0));
        self::assertNotFalse(has_action('rest_api_init'));

        remove_all_actions('admin_menu');
        remove_all_actions('network_admin_menu');
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('rest_api_init');
        remove_all_actions('show_user_profile');
        remove_all_actions('edit_user_profile');
        remove_all_actions('login_form');
        remove_all_actions('signup_extra_fields');
        delete_site_option('wppack_passkey_login_schema_version');
    }
}
