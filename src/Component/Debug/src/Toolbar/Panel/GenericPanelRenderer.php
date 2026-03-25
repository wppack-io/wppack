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

use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Templating\PhpRenderer;

final class GenericPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function __construct(
        Profile $profile,
        private readonly string $collectorName = '',
        ?PhpRenderer $phpRenderer = null,
        ?TemplateFormatters $templateFormatters = null,
    ) {
        parent::__construct($profile, $phpRenderer, $templateFormatters);
    }

    public function getName(): string
    {
        return 'generic';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData($this->collectorName);

        return $this->getPhpRenderer()->render('toolbar/panels/generic', [
            'data' => $data,
            'fmt' => $this->getFormatters(),
        ]);
    }
}
