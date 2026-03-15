<?php

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
