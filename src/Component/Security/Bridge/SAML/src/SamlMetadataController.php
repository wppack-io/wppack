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

use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Security\Bridge\SAML\Configuration\SpMetadataExporter;

final class SamlMetadataController
{
    public function __construct(
        private readonly SpMetadataExporter $exporter,
    ) {}

    public function __invoke(): Response
    {
        return new Response(
            $this->exporter->toXml(),
            200,
            ['Content-Type' => 'application/xml'],
        );
    }
}
