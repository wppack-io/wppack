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

namespace WpPack\Component\Scim\Mapping;

use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Component\Scim\Schema\ScimConstants;
use WpPack\Component\User\UserRepositoryInterface;

final readonly class UserAttributeMapper implements UserAttributeMapperInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private Sanitizer $sanitizer,
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

        return ['data' => $data, 'meta' => $meta];
    }

    public function toScim(\WP_User $user): array
    {
        $active = $this->userRepository->getMeta($user->ID, ScimConstants::META_ACTIVE, true);
        $locale = $this->userRepository->getMeta($user->ID, 'locale', true);
        $timezone = $this->userRepository->getMeta($user->ID, ScimConstants::META_TIMEZONE, true);
        $title = $this->userRepository->getMeta($user->ID, ScimConstants::META_TITLE, true);
        $externalId = $this->userRepository->getMeta($user->ID, ScimConstants::META_EXTERNAL_ID, true);
        $lastModified = $this->userRepository->getMeta($user->ID, ScimConstants::META_LAST_MODIFIED, true);

        return [
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
