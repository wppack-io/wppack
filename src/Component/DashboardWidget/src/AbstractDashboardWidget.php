<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget;

use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\Authorization\IsGrantedChecker;

abstract class AbstractDashboardWidget
{
    public readonly string $id;
    public readonly string $label;
    public readonly string $context;
    public readonly string $priority;

    /** @var list<IsGranted> */
    private readonly array $isGrantedAttributes;

    private readonly IsGrantedChecker $isGrantedChecker;

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

    abstract public function render(): void;

    public function configure(): void {}

    public function register(): void
    {
        if (!$this->isGrantedChecker->isAllGranted($this->isGrantedAttributes)) {
            return;
        }

        $configureCallback = $this->hasConfigureOverride() ? $this->configure(...) : null;

        wp_add_dashboard_widget(
            $this->id,
            $this->label,
            $this->render(...),
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

    private function hasConfigureOverride(): bool
    {
        $method = new \ReflectionMethod($this, 'configure');

        return $method->getDeclaringClass()->getName() !== self::class;
    }
}
