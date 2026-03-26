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

use OneLogin\Saml2\IdPMetadataParser as OneLoginIdPMetadataParser;

final class IdpMetadataParser
{
    /**
     * Parse IdP metadata from an XML string.
     */
    public function parseXml(string $xml): IdpSettings
    {
        try {
            $parsed = OneLoginIdPMetadataParser::parseXML($xml);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(\sprintf(
                'Failed to parse IdP metadata XML: %s',
                $e->getMessage(),
            ), previous: $e);
        }

        return $this->buildIdpSettings($parsed);
    }

    /**
     * Parse IdP metadata from a file path.
     */
    public function parseFile(string $filepath): IdpSettings
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            throw new \InvalidArgumentException(\sprintf(
                'IdP metadata file not found or not readable: %s',
                $filepath,
            ));
        }

        $xml = file_get_contents($filepath);

        if ($xml === false) {
            throw new \InvalidArgumentException(\sprintf(
                'Failed to read IdP metadata file: %s',
                $filepath,
            ));
        }

        return $this->parseXml($xml);
    }

    /**
     * Parse IdP metadata from a remote URL.
     */
    public function parseRemoteUrl(string $url): IdpSettings
    {
        try {
            $parsed = OneLoginIdPMetadataParser::parseRemoteXML($url);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(\sprintf(
                'Failed to fetch IdP metadata from URL "%s": %s',
                $url,
                $e->getMessage(),
            ), previous: $e);
        }

        return $this->buildIdpSettings($parsed);
    }

    /**
     * @param array<string, mixed> $parsed
     */
    private function buildIdpSettings(array $parsed): IdpSettings
    {
        if (!isset($parsed['idp'])) {
            throw new \InvalidArgumentException('Parsed metadata does not contain IdP settings.');
        }

        $idp = $parsed['idp'];

        $entityId = $idp['entityId'] ?? null;
        if (!\is_string($entityId) || $entityId === '') {
            throw new \InvalidArgumentException('IdP metadata does not contain a valid entityId.');
        }

        $ssoUrl = $idp['singleSignOnService']['url'] ?? null;
        if (!\is_string($ssoUrl) || $ssoUrl === '') {
            throw new \InvalidArgumentException('IdP metadata does not contain a valid SSO URL.');
        }

        $sloUrl = $idp['singleLogoutService']['url'] ?? null;
        if (!\is_string($sloUrl) || $sloUrl === '') {
            $sloUrl = null;
        }

        $x509Cert = $this->extractCertificate($idp);

        return new IdpSettings(
            entityId: $entityId,
            ssoUrl: $ssoUrl,
            sloUrl: $sloUrl,
            x509Cert: $x509Cert,
        );
    }

    /**
     * @param array<string, mixed> $idp
     */
    private function extractCertificate(array $idp): string
    {
        // Prefer multi-cert signing[0] if available
        if (isset($idp['x509certMulti']['signing'][0]) && \is_string($idp['x509certMulti']['signing'][0]) && $idp['x509certMulti']['signing'][0] !== '') {
            return $idp['x509certMulti']['signing'][0];
        }

        if (isset($idp['x509cert']) && \is_string($idp['x509cert']) && $idp['x509cert'] !== '') {
            return $idp['x509cert'];
        }

        throw new \InvalidArgumentException('IdP metadata does not contain a valid x509 certificate.');
    }
}
