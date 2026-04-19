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

namespace WPPack\Component\Admin;

use WPPack\Component\Admin\Attribute\AdminScope;
use WPPack\Component\Admin\Attribute\AsAdminPage;
use WPPack\Component\Role\Authorization\IsGrantedChecker;
use WPPack\Component\Templating\TemplateRendererInterface;

abstract class AbstractAdminPage
{
    public readonly string $slug;
    public readonly string $label;
    public readonly string $menuLabel;
    public readonly string $capability;
    public readonly ?string $parent;
    public readonly ?string $icon;
    public readonly ?int $position;
    public readonly AdminScope $scope;

    private bool $network = false;
    private ?TemplateRendererInterface $renderer = null;
    private ?string $hookSuffix = null;
    private ?bool $hasEnqueueOverride = null;

    public function __construct()
    {
        /** @var \ReflectionClass<object> $reflection */
        $reflection = new \ReflectionClass($this);
        $attribute = $this->resolveAttribute($reflection);

        $this->slug = $attribute->slug;
        $textDomain = $attribute->textDomain;
        $this->label = $textDomain !== null ? __($attribute->label, $textDomain) : $attribute->label;
        $menuLabel = $attribute->menuLabel !== '' ? $attribute->menuLabel : $attribute->label;
        $this->menuLabel = $textDomain !== null ? __($menuLabel, $textDomain) : $menuLabel;
        $this->capability = IsGrantedChecker::extractCapability($reflection);
        $this->parent = $attribute->parent;
        $this->icon = $attribute->icon;
        $this->position = $attribute->position;
        $this->scope = $attribute->scope;

        if ($this->scope === AdminScope::Network) {
            $this->network = true;
        }
    }

    /**
     * Set network mode. Only applies when scope is Auto.
     */
    public function setNetwork(bool $network): void
    {
        if ($this->scope === AdminScope::Auto) {
            $this->network = $network;
        }
    }

    public function isNetwork(): bool
    {
        return $this->network;
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

    protected function enqueue(): void {}

    public function addMenuPage(): void
    {
        $parent = $this->parent;

        // Network admin uses settings.php instead of options-general.php
        if ($this->network && $parent === 'options-general.php') {
            $parent = 'settings.php';
        }

        if ($parent !== null) {
            $this->hookSuffix = (string) add_submenu_page(
                $parent,
                $this->label,
                $this->menuLabel,
                $this->capability,
                $this->slug,
                $this->handleRender(...),
                $this->position,
            );
        } else {
            $this->hookSuffix = add_menu_page(
                $this->label,
                $this->menuLabel,
                $this->capability,
                $this->slug,
                $this->handleRender(...),
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

        $this->enqueue();
    }

    /** @internal */
    public function hasEnqueueOverride(): bool
    {
        if ($this->hasEnqueueOverride === null) {
            $method = new \ReflectionMethod($this, 'enqueue');
            $this->hasEnqueueOverride = $method->getDeclaringClass()->getName() !== self::class;
        }

        return $this->hasEnqueueOverride;
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
