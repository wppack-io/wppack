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

use LightSaml\Model\Context\SerializationContext;
use LightSaml\Model\Metadata\AssertionConsumerService;
use LightSaml\Model\Metadata\EntityDescriptor;
use LightSaml\Model\Metadata\SingleLogoutService;
use LightSaml\Model\Metadata\SpSsoDescriptor;
use LightSaml\SamlConstants;

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
        $sp = $this->configuration->getSpSettings();

        $spDescriptor = new SpSsoDescriptor();
        $spDescriptor->addNameIDFormat($sp->getNameIdFormat());
        $spDescriptor->setWantAssertionsSigned($this->configuration->wantAssertionsSigned());

        $acs = new AssertionConsumerService(
            $sp->getAcsUrl(),
            SamlConstants::BINDING_SAML2_HTTP_POST,
        );
        $acs->setIndex(0);
        $spDescriptor->addAssertionConsumerService($acs);

        if ($sp->getSloUrl() !== null) {
            $slo = new SingleLogoutService(
                $sp->getSloUrl(),
                SamlConstants::BINDING_SAML2_HTTP_REDIRECT,
            );
            $spDescriptor->addSingleLogoutService($slo);
        }

        $entityDescriptor = new EntityDescriptor($sp->getEntityId(), [$spDescriptor]);

        $context = new SerializationContext();
        $entityDescriptor->serialize($context->getDocument(), $context);

        $xml = $context->getDocument()->saveXML();

        if ($xml === false) {
            // @codeCoverageIgnoreStart
            throw new \RuntimeException('Failed to generate SP metadata XML.');
            // @codeCoverageIgnoreEnd
        }

        return $xml;
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
