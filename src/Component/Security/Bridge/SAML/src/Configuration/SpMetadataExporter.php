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

namespace WpPack\Component\Security\Bridge\SAML\Configuration;

use OneLogin\Saml2\Settings;

final class SpMetadataExporter
{
    public function __construct(
        private readonly SamlConfiguration $configuration,
    ) {}

    /**
     * Generate SP metadata XML string.
     */
    public function toXml(): string
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

    /**
     * Export SP metadata XML to a file.
     */
    public function exportToFile(string $filepath): void
    {
        $result = @file_put_contents($filepath, $this->toXml());

        if ($result === false) {
            throw new \RuntimeException(\sprintf(
                'Failed to write SP metadata to file: %s',
                $filepath,
            ));
        }
    }
}
