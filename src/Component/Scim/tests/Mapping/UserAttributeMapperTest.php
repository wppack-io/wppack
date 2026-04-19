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
use WPPack\Component\EventDispatcher\EventDispatcher;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Scim\Event\ScimUserAttributesMappedEvent;
use WPPack\Component\Scim\Event\ScimUserSerializedEvent;
use WPPack\Component\Scim\Mapping\ScimAttributeMapping;
use WPPack\Component\Scim\Mapping\UserAttributeMapper;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\User\UserRepository;

#[CoversClass(UserAttributeMapper::class)]
final class UserAttributeMapperTest extends TestCase
{
    private UserRepository $userRepository;
    private Sanitizer $sanitizer;
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->userRepository = new UserRepository();
        $this->sanitizer = new Sanitizer();
        $this->dispatcher = new EventDispatcher();
    }

    #[Test]
    public function toWordPressWithCustomMappingsStoresScimAttributesAsUserMeta(): void
    {
        $mapper = new UserAttributeMapper(
            $this->userRepository,
            $this->sanitizer,
            $this->dispatcher,
            [
                new ScimAttributeMapping('department', 'wp_department'),
                new ScimAttributeMapping('costCenter', 'wp_cost_center'),
            ],
        );

        $result = $mapper->toWordPress([
            'userName' => 'jdoe',
            'displayName' => 'John Doe',
            'department' => 'Engineering',
            'costCenter' => 'CC-1234',
        ]);

        self::assertSame('Engineering', $result['meta']['wp_department']);
        self::assertSame('CC-1234', $result['meta']['wp_cost_center']);
        self::assertSame('jdoe', $result['data']['user_login']);
        self::assertSame('John Doe', $result['data']['display_name']);
    }

    #[Test]
    public function toScimWithCustomMappingsIncludesUserMetaInScimRepresentation(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'scim_custom_map_user',
            'user_email' => 'custom_map@example.com',
            'user_pass' => wp_generate_password(),
            'display_name' => 'Custom Map User',
            'first_name' => 'Custom',
            'last_name' => 'Map',
        ]);
        self::assertIsInt($userId);

        update_user_meta($userId, 'wp_department', 'Engineering');
        update_user_meta($userId, 'wp_cost_center', 'CC-5678');

        $mapper = new UserAttributeMapper(
            $this->userRepository,
            $this->sanitizer,
            $this->dispatcher,
            [
                new ScimAttributeMapping('department', 'wp_department'),
                new ScimAttributeMapping('costCenter', 'wp_cost_center'),
            ],
        );

        $user = get_userdata($userId);
        self::assertInstanceOf(\WP_User::class, $user);

        $scim = $mapper->toScim($user);

        self::assertSame('Engineering', $scim['department']);
        self::assertSame('CC-5678', $scim['costCenter']);
        self::assertSame('scim_custom_map_user', $scim['userName']);
        self::assertSame('Custom Map User', $scim['displayName']);

        require_once \ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($userId);
    }

    #[Test]
    public function toWordPressDispatchesEventAndListenerCanModifyDataAndMeta(): void
    {
        $mapper = new UserAttributeMapper(
            $this->userRepository,
            $this->sanitizer,
            $this->dispatcher,
        );

        $listener = static function (ScimUserAttributesMappedEvent $event): void {
            $data = $event->getData();
            $data['role'] = 'editor';
            $event->setData($data);

            $meta = $event->getMeta();
            $meta['custom_flag'] = 'yes';
            $event->setMeta($meta);
        };

        add_action(ScimUserAttributesMappedEvent::class, $listener);

        try {
            $result = $mapper->toWordPress([
                'userName' => 'eventuser',
                'emails' => [
                    ['value' => 'eventuser@example.com', 'primary' => true, 'type' => 'work'],
                ],
            ]);

            self::assertSame('editor', $result['data']['role']);
            self::assertSame('yes', $result['meta']['custom_flag']);
            self::assertSame('eventuser', $result['data']['user_login']);
            self::assertSame('eventuser@example.com', $result['data']['user_email']);
        } finally {
            remove_action(ScimUserAttributesMappedEvent::class, $listener);
        }
    }

    #[Test]
    public function toWordPressEventReceivesOriginalScimAttributes(): void
    {
        $mapper = new UserAttributeMapper(
            $this->userRepository,
            $this->sanitizer,
            $this->dispatcher,
        );

        $capturedEvent = null;
        $listener = static function (ScimUserAttributesMappedEvent $event) use (&$capturedEvent): void {
            $capturedEvent = $event;
        };

        add_action(ScimUserAttributesMappedEvent::class, $listener);

        try {
            $scimInput = [
                'userName' => 'attrcheck',
                'externalId' => 'ext-999',
                'active' => true,
                'title' => 'Manager',
            ];

            $mapper->toWordPress($scimInput);

            self::assertNotNull($capturedEvent);
            self::assertSame($scimInput, $capturedEvent->getScimAttributes());
            self::assertSame('attrcheck', $capturedEvent->getData()['user_login']);
            self::assertSame('ext-999', $capturedEvent->getMeta()[ScimConstants::META_EXTERNAL_ID]);
            self::assertSame('1', $capturedEvent->getMeta()[ScimConstants::META_ACTIVE]);
            self::assertSame('Manager', $capturedEvent->getMeta()[ScimConstants::META_TITLE]);
        } finally {
            remove_action(ScimUserAttributesMappedEvent::class, $listener);
        }
    }

    #[Test]
    public function toScimDispatchesEventAndListenerCanModifyScimAttributes(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'scim_event_user',
            'user_email' => 'scimevent@example.com',
            'user_pass' => wp_generate_password(),
            'display_name' => 'SCIM Event User',
        ]);
        self::assertIsInt($userId);

        update_user_meta($userId, ScimConstants::META_ACTIVE, '1');
        update_user_meta($userId, ScimConstants::META_EXTERNAL_ID, 'ext-abc');

        $mapper = new UserAttributeMapper(
            $this->userRepository,
            $this->sanitizer,
            $this->dispatcher,
        );

        $listener = static function (ScimUserSerializedEvent $event): void {
            $attrs = $event->getScimAttributes();
            $attrs['customExtension'] = 'injected-value';
            unset($attrs['profileUrl']);
            $event->setScimAttributes($attrs);
        };

        add_action(ScimUserSerializedEvent::class, $listener);

        try {
            $user = get_userdata($userId);
            self::assertInstanceOf(\WP_User::class, $user);

            $scim = $mapper->toScim($user);

            self::assertSame('injected-value', $scim['customExtension']);
            self::assertArrayNotHasKey('profileUrl', $scim);
            self::assertSame('scim_event_user', $scim['userName']);
            self::assertSame('ext-abc', $scim['externalId']);
            self::assertTrue($scim['active']);
        } finally {
            remove_action(ScimUserSerializedEvent::class, $listener);

            require_once \ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function toScimEventReceivesCorrectUser(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'scim_user_check',
            'user_email' => 'usercheck@example.com',
            'user_pass' => wp_generate_password(),
        ]);
        self::assertIsInt($userId);

        $mapper = new UserAttributeMapper(
            $this->userRepository,
            $this->sanitizer,
            $this->dispatcher,
        );

        $capturedEvent = null;
        $listener = static function (ScimUserSerializedEvent $event) use (&$capturedEvent): void {
            $capturedEvent = $event;
        };

        add_action(ScimUserSerializedEvent::class, $listener);

        try {
            $user = get_userdata($userId);
            self::assertInstanceOf(\WP_User::class, $user);

            $mapper->toScim($user);

            self::assertNotNull($capturedEvent);
            self::assertSame($userId, $capturedEvent->getUser()->ID);
            self::assertSame('scim_user_check', $capturedEvent->getScimAttributes()['userName']);
            self::assertSame('usercheck@example.com', $capturedEvent->getScimAttributes()['emails'][0]['value']);
        } finally {
            remove_action(ScimUserSerializedEvent::class, $listener);

            require_once \ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($userId);
        }
    }
}
