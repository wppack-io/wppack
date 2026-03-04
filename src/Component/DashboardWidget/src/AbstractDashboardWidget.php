<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget;

use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;

abstract class AbstractDashboardWidget
{
    public readonly string $id;
    public readonly string $title;
    public readonly ?string $capability;
    public readonly string $context;
    public readonly string $priority;

    public function __construct()
    {
        $attribute = $this->resolveAttribute();

        $this->id = $attribute->id;
        $this->title = $attribute->title;
        $this->capability = $attribute->capability;
        $this->context = $attribute->context;
        $this->priority = $attribute->priority;
    }

    abstract public function render(): void;

    public function configure(): void {}

    public function register(): void
    {
        if ($this->capability !== null && !current_user_can($this->capability)) {
            return;
        }

        $configureCallback = $this->hasConfigureOverride() ? $this->configure(...) : null;

        wp_add_dashboard_widget(
            $this->id,
            $this->title,
            $this->render(...),
            $configureCallback,
            null,
            $this->context,
            $this->priority,
        );
    }

    private function resolveAttribute(): AsDashboardWidget
    {
        $reflection = new \ReflectionClass($this);
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

    private function hasConfigureOverride(): bool
    {
        $method = new \ReflectionMethod($this, 'configure');

        return $method->getDeclaringClass()->getName() !== self::class;
    }
}
