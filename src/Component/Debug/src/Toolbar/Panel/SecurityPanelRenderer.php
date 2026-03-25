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

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'security')]
final class SecurityPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'security';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/security', [
            'isLoggedIn' => (bool) ($data['is_logged_in'] ?? false),
            'username' => (string) ($data['username'] ?? ''),
            'displayName' => (string) ($data['display_name'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'roles' => $data['roles'] ?? [],
            'isSuperAdmin' => (bool) ($data['is_super_admin'] ?? false),
            'auth' => (string) ($data['authentication'] ?? 'none'),
            'capabilities' => $data['capabilities'] ?? [],
            'nonceOps' => $data['nonce_operations'] ?? [],
            'nonceVerifyCount' => (int) ($data['nonce_verify_count'] ?? 0),
            'nonceFailures' => (int) ($data['nonce_verify_failures'] ?? 0),
            'fmt' => $this->getFormatters(),
            'requestTimeFloat' => $this->requestTimeFloat,
        ]);
    }
}
