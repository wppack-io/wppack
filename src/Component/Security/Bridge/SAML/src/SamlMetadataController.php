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

namespace WpPack\Component\Security\Bridge\SAML;

use OneLogin\Saml2\Settings;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Bridge\SAML\Configuration\SamlConfiguration;

final class SamlMetadataController
{
    public function __construct(
        private readonly SamlConfiguration $configuration,
    ) {}

    public function __invoke(): Response
    {
        return new Response(
            $this->getMetadataXml(),
            200,
            ['Content-Type' => 'application/xml'],
        );
    }

    public function getMetadataXml(): string
    {
        $settings = new Settings($this->configuration->toOneLoginArray(), true);
        $metadata = $settings->getSPMetadata();
        $errors = $settings->validateMetadata($metadata);

        // @codeCoverageIgnoreStart
        if ($errors !== []) {
            throw new \RuntimeException(\sprintf(
                'Invalid SP metadata: %s',
                implode(', ', $errors),
            ));
        }
        // @codeCoverageIgnoreEnd

        return $metadata;
    }
}
