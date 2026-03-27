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

use WpPack\Component\Scim\Schema\ScimConstants;

final readonly class UserAttributeMapper implements UserAttributeMapperInterface
{
    public function toWordPress(array $scimAttributes): array
    {
        $data = [];
        $meta = [];

        if (isset($scimAttributes['userName'])) {
            $data['user_login'] = sanitize_user($scimAttributes['userName']);
        }

        if (isset($scimAttributes['name'])) {
            if (isset($scimAttributes['name']['givenName'])) {
                $data['first_name'] = sanitize_text_field($scimAttributes['name']['givenName']);
            }
            if (isset($scimAttributes['name']['familyName'])) {
                $data['last_name'] = sanitize_text_field($scimAttributes['name']['familyName']);
            }
        }

        if (isset($scimAttributes['displayName'])) {
            $data['display_name'] = sanitize_text_field($scimAttributes['displayName']);
        }

        if (isset($scimAttributes['nickName'])) {
            $data['nickname'] = sanitize_text_field($scimAttributes['nickName']);
        }

        if (isset($scimAttributes['profileUrl'])) {
            $data['user_url'] = esc_url_raw($scimAttributes['profileUrl']);
        }

        if (isset($scimAttributes['emails'])) {
            $primaryEmail = $this->extractPrimaryEmail($scimAttributes['emails']);
            if ($primaryEmail !== null) {
                $data['user_email'] = sanitize_email($primaryEmail);
            }
        }

        if (isset($scimAttributes['externalId'])) {
            $meta[ScimConstants::META_EXTERNAL_ID] = sanitize_text_field($scimAttributes['externalId']);
        }

        if (isset($scimAttributes['active'])) {
            $meta[ScimConstants::META_ACTIVE] = $scimAttributes['active'] ? '1' : '0';
        }

        if (isset($scimAttributes['locale'])) {
            $meta['locale'] = sanitize_text_field($scimAttributes['locale']);
        }

        if (isset($scimAttributes['timezone'])) {
            $meta[ScimConstants::META_TIMEZONE] = sanitize_text_field($scimAttributes['timezone']);
        }

        if (isset($scimAttributes['title'])) {
            $meta[ScimConstants::META_TITLE] = sanitize_text_field($scimAttributes['title']);
        }

        return ['data' => $data, 'meta' => $meta];
    }

    public function toScim(\WP_User $user): array
    {
        $active = get_user_meta($user->ID, ScimConstants::META_ACTIVE, true);
        $locale = get_user_meta($user->ID, 'locale', true);
        $timezone = get_user_meta($user->ID, ScimConstants::META_TIMEZONE, true);
        $title = get_user_meta($user->ID, ScimConstants::META_TITLE, true);
        $externalId = get_user_meta($user->ID, ScimConstants::META_EXTERNAL_ID, true);

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
