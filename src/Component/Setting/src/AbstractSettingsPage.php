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

namespace WpPack\Component\Setting;

use WpPack\Component\Role\Authorization\IsGrantedChecker;
use WpPack\Component\Setting\Attribute\AsSettingsPage;
use WpPack\Component\Templating\TemplateRendererInterface;

abstract class AbstractSettingsPage
{
    public readonly string $slug;
    public readonly string $label;
    public readonly string $menuLabel;
    public readonly string $capability;
    public readonly string $optionName;
    public readonly string $optionGroup;
    public readonly ?string $parent;
    public readonly ?string $icon;
    public readonly ?int $position;

    private ?TemplateRendererInterface $templateRenderer = null;
    private ?SettingsRenderer $renderer = null;
    private ?bool $hasValidateOverride = null;
    private ?bool $hasSanitizeOverride = null;

    public function __construct()
    {
        /** @var \ReflectionClass<object> $reflection */
        $reflection = new \ReflectionClass($this);
        $attribute = $this->resolveAttribute($reflection);

        $this->slug = $attribute->slug;
        $this->label = $attribute->label;
        $this->menuLabel = $attribute->menuLabel !== '' ? $attribute->menuLabel : $attribute->label;
        $this->capability = IsGrantedChecker::extractCapability($reflection);
        $this->optionName = $attribute->optionName !== '' ? $attribute->optionName : str_replace('-', '_', $attribute->slug);
        $this->optionGroup = $attribute->optionGroup !== '' ? $attribute->optionGroup : $this->optionName;
        $this->parent = $attribute->parent;
        $this->icon = $attribute->icon;
        $this->position = $attribute->position;
    }

    abstract protected function configure(SettingsConfigurator $settings): void;

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function sanitize(array $input): array
    {
        return $input;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    protected function validate(array $input, ValidationContext $context): array
    {
        return $input;
    }

    /** @internal */
    public function setTemplateRenderer(TemplateRendererInterface $templateRenderer): void
    {
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function render(string $template, array $context = []): string
    {
        if ($this->templateRenderer === null) {
            throw new \LogicException(sprintf(
                'A TemplateRendererInterface is not available. Call setTemplateRenderer() to use render() in "%s".',
                static::class,
            ));
        }

        return $this->templateRenderer->render($template, $context);
    }

    public function getRenderer(): SettingsRenderer
    {
        return $this->renderer ??= $this->createRenderer();
    }

    protected function createRenderer(): SettingsRenderer
    {
        return new SettingsRenderer();
    }

    /** @internal */
    public function handleRender(): void
    {
        if (method_exists($this, '__invoke')) {
            echo $this();
        } else {
            $this->getRenderer()->renderPage($this);
        }
    }

    /**
     * @param mixed $default
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        $options = get_option($this->optionName, []);

        if (!\is_array($options)) {
            return $default;
        }

        return $options[$key] ?? $default;
    }

    public function addMenuPage(): void
    {
        if ($this->parent !== null) {
            add_submenu_page(
                $this->parent,
                $this->label,
                $this->menuLabel,
                $this->capability,
                $this->slug,
                $this->handleRender(...),
            );
        } else {
            add_menu_page(
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

    public function initSettings(): void
    {
        $configurator = new SettingsConfigurator($this);
        $this->configure($configurator);

        $args = [];

        if ($this->hasSanitizeOverride() || $this->hasValidateOverride()) {
            $args['sanitize_callback'] = $this->sanitizeCallback(...);
        }

        register_setting($this->optionGroup, $this->optionName, $args);

        foreach ($configurator->getSections() as $section) {
            add_settings_section(
                $section->id,
                $section->title,
                $section->renderCallback,
                $this->slug,
            );

            foreach ($section->getFields() as $field) {
                add_settings_field(
                    $field->id,
                    $field->title,
                    $field->renderCallback,
                    $this->slug,
                    $section->id,
                    $field->args,
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function sanitizeCallback(array $input): array
    {
        if ($this->hasSanitizeOverride()) {
            $input = $this->sanitize($input);
        }

        if ($this->hasValidateOverride()) {
            $context = new ValidationContext($this->optionGroup, $this->optionName);
            $input = $this->validate($input, $context);
        }

        return $input;
    }

    /** @internal */
    public function hasValidateOverride(): bool
    {
        if ($this->hasValidateOverride === null) {
            $method = new \ReflectionMethod($this, 'validate');
            $this->hasValidateOverride = $method->getDeclaringClass()->getName() !== self::class;
        }

        return $this->hasValidateOverride;
    }

    /** @internal */
    public function hasSanitizeOverride(): bool
    {
        if ($this->hasSanitizeOverride === null) {
            $method = new \ReflectionMethod($this, 'sanitize');
            $this->hasSanitizeOverride = $method->getDeclaringClass()->getName() !== self::class;
        }

        return $this->hasSanitizeOverride;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function resolveAttribute(\ReflectionClass $reflection): AsSettingsPage
    {
        /** @var list<\ReflectionAttribute<AsSettingsPage>> $attributes */
        $attributes = $reflection->getAttributes(AsSettingsPage::class);

        if ($attributes === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have the #[AsSettingsPage] attribute.',
                static::class,
            ));
        }

        return $attributes[0]->newInstance();
    }
}
