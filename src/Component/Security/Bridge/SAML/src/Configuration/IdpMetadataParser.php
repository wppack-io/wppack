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

namespace WPPack\Component\Security\Bridge\SAML\Configuration;

use LightSaml\Model\Metadata\EntityDescriptor;
use LightSaml\Model\Metadata\IdpSsoDescriptor;
use LightSaml\Model\Metadata\KeyDescriptor;
use LightSaml\SamlConstants;

final class IdpMetadataParser
{
    /**
     * Parse IdP metadata from an XML string.
     */
    public function parseXml(string $xml): IdpSettings
    {
        $prev = libxml_use_internal_errors(true);

        try {
            $entityDescriptor = EntityDescriptor::loadXml($xml);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(\sprintf(
                'Failed to parse IdP metadata XML: %s',
                $e->getMessage(),
            ), previous: $e);
        } finally {
            libxml_use_internal_errors($prev);
        }

        return $this->buildIdpSettings($entityDescriptor);
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
        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            throw new \InvalidArgumentException(\sprintf(
                'Failed to fetch IdP metadata from URL "%s": %s',
                $url,
                $response->get_error_message(),
            ));
        }

        $body = wp_remote_retrieve_body($response);

        if ($body === '') {
            throw new \InvalidArgumentException(\sprintf(
                'Empty response body from IdP metadata URL "%s".',
                $url,
            ));
        }

        return $this->parseXml($body);
    }

    private function buildIdpSettings(EntityDescriptor $entityDescriptor): IdpSettings
    {
        $entityId = $entityDescriptor->getEntityID();

        if ($entityId === '') {
            throw new \InvalidArgumentException('IdP metadata does not contain a valid entityId.');
        }

        $idpDescriptor = $entityDescriptor->getFirstIdpSsoDescriptor();

        if (!$idpDescriptor instanceof IdpSsoDescriptor) {
            throw new \InvalidArgumentException('Parsed metadata does not contain IdP settings.');
        }

        $ssoService = $idpDescriptor->getFirstSingleSignOnService(SamlConstants::BINDING_SAML2_HTTP_REDIRECT)
            ?? $idpDescriptor->getFirstSingleSignOnService();
        if ($ssoService === null) {
            throw new \InvalidArgumentException('IdP metadata does not contain a valid SSO URL.');
        }
        $ssoUrl = $ssoService->getLocation();

        $sloService = $idpDescriptor->getFirstSingleLogoutService(SamlConstants::BINDING_SAML2_HTTP_REDIRECT)
            ?? $idpDescriptor->getFirstSingleLogoutService();
        $sloUrl = $sloService !== null ? $sloService->getLocation() : null;

        $x509Cert = $this->extractCertificate($idpDescriptor);

        return new IdpSettings(
            entityId: $entityId,
            ssoUrl: $ssoUrl,
            sloUrl: $sloUrl,
            x509Cert: $x509Cert,
        );
    }

    private function extractCertificate(IdpSsoDescriptor $idpDescriptor): string
    {
        // Prefer signing key descriptor
        $signingKeys = $idpDescriptor->getAllKeyDescriptorsByUse(KeyDescriptor::USE_SIGNING);

        if ($signingKeys !== []) {
            return $signingKeys[0]->getCertificate()->getData();
        }

        // Fall back to any key descriptor
        $firstKey = $idpDescriptor->getFirstKeyDescriptor();

        if ($firstKey !== null) {
            return $firstKey->getCertificate()->getData();
        }

        throw new \InvalidArgumentException('IdP metadata does not contain a valid x509 certificate.');
    }
}
