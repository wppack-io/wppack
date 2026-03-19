<?php

declare(strict_types=1);

namespace WpPack\Component\Admin;

use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\Security\Authorization\IsGrantedChecker;

abstract class AbstractAdminPage
{
    public readonly string $slug;
    public readonly string $title;
    public readonly string $menuTitle;
    public readonly string $capability;
    public readonly ?string $parent;
    public readonly ?string $icon;
    public readonly ?int $position;

    private ?string $hookSuffix = null;
    private ?bool $hasEnqueueScriptsOverride = null;
    private ?bool $hasEnqueueStylesOverride = null;

    public function __construct()
    {
        /** @var \ReflectionClass<object> $reflection */
        $reflection = new \ReflectionClass($this);
        $attribute = $this->resolveAttribute($reflection);

        $this->slug = $attribute->slug;
        $this->title = $attribute->title;
        $this->menuTitle = $attribute->menuTitle !== '' ? $attribute->menuTitle : $attribute->title;
        $this->capability = IsGrantedChecker::extractCapability($reflection);
        $this->parent = $attribute->parent;
        $this->icon = $attribute->icon;
        $this->position = $attribute->position;
    }

    abstract public function render(): void;

    protected function enqueueScripts(string $hookSuffix): void {}

    protected function enqueueStyles(string $hookSuffix): void {}

    public function addMenuPage(): void
    {
        if ($this->parent !== null) {
            $this->hookSuffix = (string) add_submenu_page(
                $this->parent,
                $this->title,
                $this->menuTitle,
                $this->capability,
                $this->slug,
                $this->render(...),
            );
        } else {
            $this->hookSuffix = add_menu_page(
                $this->title,
                $this->menuTitle,
                $this->capability,
                $this->slug,
                $this->render(...),
                $this->icon ?? '',
                $this->position,
            );
        }
    }

    public function handleEnqueue(string $hookSuffix): void
    {
        if ($hookSuffix !== $this->hookSuffix) {
            return;
        }

        if ($this->hasEnqueueScriptsOverride()) {
            $this->enqueueScripts($hookSuffix);
        }

        if ($this->hasEnqueueStylesOverride()) {
            $this->enqueueStyles($hookSuffix);
        }
    }

    /** @internal */
    public function hasEnqueueScriptsOverride(): bool
    {
        if ($this->hasEnqueueScriptsOverride === null) {
            $method = new \ReflectionMethod($this, 'enqueueScripts');
            $this->hasEnqueueScriptsOverride = $method->getDeclaringClass()->getName() !== self::class;
        }

        return $this->hasEnqueueScriptsOverride;
    }

    /** @internal */
    public function hasEnqueueStylesOverride(): bool
    {
        if ($this->hasEnqueueStylesOverride === null) {
            $method = new \ReflectionMethod($this, 'enqueueStyles');
            $this->hasEnqueueStylesOverride = $method->getDeclaringClass()->getName() !== self::class;
        }

        return $this->hasEnqueueStylesOverride;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function resolveAttribute(\ReflectionClass $reflection): AsAdminPage
    {
        /** @var list<\ReflectionAttribute<AsAdminPage>> $attributes */
        $attributes = $reflection->getAttributes(AsAdminPage::class);

        if ($attributes === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have the #[AsAdminPage] attribute.',
                static::class,
            ));
        }

        return $attributes[0]->newInstance();
    }
}
