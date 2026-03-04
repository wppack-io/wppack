<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

use WpPack\Component\Setting\Attribute\AsSettingsPage;

abstract class AbstractSettingsPage
{
    public readonly string $slug;
    public readonly string $title;
    public readonly string $menuTitle;
    public readonly string $capability;
    public readonly string $optionName;
    public readonly string $optionGroup;
    public readonly ?string $parent;
    public readonly ?string $icon;
    public readonly ?int $position;

    private ?bool $hasValidateOverride = null;
    private ?bool $hasSanitizeOverride = null;

    public function __construct()
    {
        $attribute = $this->resolveAttribute();

        $this->slug = $attribute->slug;
        $this->title = $attribute->title;
        $this->menuTitle = $attribute->menuTitle !== '' ? $attribute->menuTitle : $attribute->title;
        $this->capability = $attribute->capability;
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

    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields($this->optionGroup);
        do_settings_sections($this->slug);
        submit_button();
        echo '</form>';
        echo '</div>';
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
                $this->title,
                $this->menuTitle,
                $this->capability,
                $this->slug,
                $this->render(...),
            );
        } else {
            add_menu_page(
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

    public function initSettings(): void
    {
        $configurator = new SettingsConfigurator();
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

    private function resolveAttribute(): AsSettingsPage
    {
        $reflection = new \ReflectionClass($this);
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
