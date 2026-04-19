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

namespace WPPack\Component\DashboardWidget;

use WPPack\Component\DashboardWidget\Attribute\AsDashboardWidget;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Role\Authorization\IsGrantedChecker;
use WPPack\Component\Templating\TemplateRendererInterface;

abstract class AbstractDashboardWidget
{
    public readonly string $id;
    public readonly string $label;
    public readonly string $context;
    public readonly string $priority;

    /** @var list<IsGranted> */
    private readonly array $isGrantedAttributes;

    private readonly IsGrantedChecker $isGrantedChecker;

    private ?TemplateRendererInterface $renderer = null;

    public function __construct(?IsGrantedChecker $isGrantedChecker = null)
    {
        /** @var \ReflectionClass<object> $reflection */
        $reflection = new \ReflectionClass($this);
        $attribute = $this->resolveAttribute($reflection);

        $this->id = $attribute->id;
        $this->label = $attribute->label;
        $this->context = $attribute->context;
        $this->priority = $attribute->priority;
        $this->isGrantedAttributes = IsGrantedChecker::resolve($reflection);
        $this->isGrantedChecker = $isGrantedChecker ?? new IsGrantedChecker();
    }

    /** @internal */
    public function setTemplateRenderer(TemplateRendererInterface $renderer): void
    {
        $this->renderer = $renderer;
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function render(string $template, array $context = []): string
    {
        if ($this->renderer === null) {
            throw new \LogicException(sprintf(
                'A TemplateRendererInterface is not available. Call setTemplateRenderer() to use render() in "%s".',
                static::class,
            ));
        }

        return $this->renderer->render($template, $context);
    }

    /** @internal */
    public function handleRender(): void
    {
        echo $this();
    }

    /** @internal */
    public function handleConfigure(): void
    {
        if (!method_exists($this, 'configure')) {
            return;
        }

        echo $this->configure();
    }

    public function register(): void
    {
        if (!$this->isGrantedChecker->isAllGranted($this->isGrantedAttributes)) {
            return;
        }

        $configureCallback = method_exists($this, 'configure')
            ? $this->handleConfigure(...)
            : null;

        wp_add_dashboard_widget(
            $this->id,
            $this->label,
            $this->handleRender(...),
            $configureCallback,
            null,
            $this->context,
            $this->priority,
        );
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function resolveAttribute(\ReflectionClass $reflection): AsDashboardWidget
    {
        /** @var list<\ReflectionAttribute<AsDashboardWidget>> $attributes */
        $attributes = $reflection->getAttributes(AsDashboardWidget::class);

        if ($attributes === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have the #[AsDashboardWidget] attribute.',
                static::class,
            ));
        }

        return $attributes[0]->newInstance();
    }
}
