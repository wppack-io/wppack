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

namespace WPPack\Component\Scim\Mapping;

use Psr\EventDispatcher\EventDispatcherInterface;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Scim\Event\ScimUserAttributesMappedEvent;
use WPPack\Component\Scim\Event\ScimUserSerializedEvent;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\User\UserRepositoryInterface;

readonly class UserAttributeMapper implements UserAttributeMapperInterface
{
    /**
     * @param list<ScimAttributeMapping> $customMappings
     */
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private Sanitizer $sanitizer,
        private EventDispatcherInterface $dispatcher,
        private array $customMappings = [],
    ) {}

    public function toWordPress(array $scimAttributes): array
    {
        $data = [];
        $meta = [];

        if (isset($scimAttributes['userName'])) {
            $data['user_login'] = $this->sanitizer->user($scimAttributes['userName']);
        }

        if (isset($scimAttributes['name'])) {
            if (isset($scimAttributes['name']['givenName'])) {
                $data['first_name'] = $this->sanitizer->text($scimAttributes['name']['givenName']);
            }
            if (isset($scimAttributes['name']['familyName'])) {
                $data['last_name'] = $this->sanitizer->text($scimAttributes['name']['familyName']);
            }
        }

        if (isset($scimAttributes['displayName'])) {
            $data['display_name'] = $this->sanitizer->text($scimAttributes['displayName']);
        }

        if (isset($scimAttributes['nickName'])) {
            $data['nickname'] = $this->sanitizer->text($scimAttributes['nickName']);
        }

        if (isset($scimAttributes['profileUrl'])) {
            $data['user_url'] = $this->sanitizer->url($scimAttributes['profileUrl']);
        }

        if (isset($scimAttributes['emails'])) {
            $primaryEmail = $this->extractPrimaryEmail($scimAttributes['emails']);
            if ($primaryEmail !== null) {
                $data['user_email'] = $this->sanitizer->email($primaryEmail);
            }
        }

        if (isset($scimAttributes['externalId'])) {
            $meta[ScimConstants::META_EXTERNAL_ID] = $this->sanitizer->text($scimAttributes['externalId']);
        }

        if (isset($scimAttributes['active'])) {
            $meta[ScimConstants::META_ACTIVE] = $scimAttributes['active'] ? '1' : '0';
        }

        if (isset($scimAttributes['locale'])) {
            $meta['locale'] = $this->sanitizer->text($scimAttributes['locale']);
        }

        if (isset($scimAttributes['timezone'])) {
            $meta[ScimConstants::META_TIMEZONE] = $this->sanitizer->text($scimAttributes['timezone']);
        }

        if (isset($scimAttributes['title'])) {
            $meta[ScimConstants::META_TITLE] = $this->sanitizer->text($scimAttributes['title']);
        }

        // Apply custom mappings
        foreach ($this->customMappings as $mapping) {
            $value = $scimAttributes[$mapping->scimPath] ?? null;

            if ($value !== null && \is_string($value)) {
                $meta[$mapping->metaKey] = $this->sanitizer->text($value);
            }
        }

        $event = $this->dispatcher->dispatch(
            new ScimUserAttributesMappedEvent($data, $meta, $scimAttributes),
        );

        return ['data' => $event->getData(), 'meta' => $event->getMeta()];
    }

    public function toScim(\WP_User $user): array
    {
        $active = $this->userRepository->getMeta($user->ID, ScimConstants::META_ACTIVE, true);
        $locale = $this->userRepository->getMeta($user->ID, 'locale', true);
        $timezone = $this->userRepository->getMeta($user->ID, ScimConstants::META_TIMEZONE, true);
        $title = $this->userRepository->getMeta($user->ID, ScimConstants::META_TITLE, true);
        $externalId = $this->userRepository->getMeta($user->ID, ScimConstants::META_EXTERNAL_ID, true);
        $lastModified = $this->userRepository->getMeta($user->ID, ScimConstants::META_LAST_MODIFIED, true);

        $scimAttributes = [
            'userName' => $user->user_login,
            'name' => [
                'givenName' => $user->first_name,
                'familyName' => $user->last_name,
            ],
            'displayName' => $user->display_name,
            'nickName' => $user->nickname,
            'profileUrl' => $user->user_url,
            'emails' => [
                [
                    'value' => $user->user_email,
                    'primary' => true,
                    'type' => 'work',
                ],
            ],
            'active' => $active !== '0',
            'locale' => $locale !== '' ? $locale : null,
            'timezone' => $timezone !== '' ? $timezone : null,
            'title' => $title !== '' ? $title : null,
            'externalId' => $externalId !== '' ? $externalId : null,
            'lastModified' => $lastModified !== '' ? $lastModified : null,
        ];

        // Apply custom mappings (reverse direction: meta → SCIM)
        foreach ($this->customMappings as $mapping) {
            $value = $this->userRepository->getMeta($user->ID, $mapping->metaKey, true);

            if (\is_string($value) && $value !== '') {
                $scimAttributes[$mapping->scimPath] = $value;
            }
        }

        $event = $this->dispatcher->dispatch(
            new ScimUserSerializedEvent($scimAttributes, $user),
        );

        return $event->getScimAttributes();
    }

    /**
     * @param list<array<string, mixed>> $emails
     */
    private function extractPrimaryEmail(array $emails): ?string
    {
        foreach ($emails as $email) {
            if (isset($email['primary']) && $email['primary'] === true && isset($email['value'])) {
                return $email['value'];
            }
        }

        // Fallback to the first email
        return $emails[0]['value'] ?? null;
    }
}
