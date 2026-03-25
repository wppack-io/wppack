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

final class SectionDefinition
{
    /** @var list<FieldDefinition> */
    private array $fields = [];

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?\Closure $renderCallback = null,
        private readonly ?AbstractSettingsPage $page = null,
    ) {}

    /**
     * @param array<string, mixed> $args
     */
    public function field(string $id, string $title, \Closure|string $render, array $args = []): self
    {
        if (\is_string($render)) {
            $page = $this->requirePage();
            $renderer = $page->getRenderer();
            $type = $render;

            if (!method_exists($renderer, $type)) {
                throw new \LogicException(sprintf(
                    'Renderer "%s" does not have a "%s" method.',
                    $renderer::class,
                    $type,
                ));
            }

            $renderCallback = static function (array $wpArgs) use ($page, $type): void {
                $context = $wpArgs['context'] ?? [];
                $context['value'] = $page->getOption($context['id'] ?? '', $context['default'] ?? '');
                $page->getRenderer()->{$type}($context);
            };

            $this->fields[] = new FieldDefinition($id, $title, $renderCallback, array_merge($args, [
                'context' => $args,
            ]));
        } else {
            $this->fields[] = new FieldDefinition($id, $title, $render, $args);
        }

        return $this;
    }

    public function text(
        string $id,
        string $title,
        string $default = '',
        string $class = 'regular-text',
        string $placeholder = '',
        string $description = '',
    ): self {
        $page = $this->requirePage();
        $context = [
            'id' => $id,
            'name' => $page->optionName . '[' . $id . ']',
            'class' => $class,
            'placeholder' => $placeholder,
            'description' => $description,
            'default' => $default,
        ];

        return $this->addRendererField($id, $title, 'text', $context, labelFor: true);
    }

    public function password(
        string $id,
        string $title,
        string $class = 'regular-text',
        string $description = '',
    ): self {
        $page = $this->requirePage();
        $context = [
            'id' => $id,
            'name' => $page->optionName . '[' . $id . ']',
            'class' => $class,
            'description' => $description,
            'default' => '',
        ];

        return $this->addRendererField($id, $title, 'password', $context, labelFor: true);
    }

    public function url(
        string $id,
        string $title,
        string $default = '',
        string $class = 'regular-text code',
        string $placeholder = '',
        string $description = '',
    ): self {
        $page = $this->requirePage();
        $context = [
            'id' => $id,
            'name' => $page->optionName . '[' . $id . ']',
            'class' => $class,
            'placeholder' => $placeholder,
            'description' => $description,
            'default' => $default,
        ];

        return $this->addRendererField($id, $title, 'url', $context, labelFor: true);
    }

    public function email(
        string $id,
        string $title,
        string $default = '',
        string $class = 'regular-text',
        string $placeholder = '',
        string $description = '',
    ): self {
        $page = $this->requirePage();
        $context = [
            'id' => $id,
            'name' => $page->optionName . '[' . $id . ']',
            'class' => $class,
            'placeholder' => $placeholder,
            'description' => $description,
            'default' => $default,
        ];

        return $this->addRendererField($id, $title, 'email', $context, labelFor: true);
    }

    public function number(
        string $id,
        string $title,
        int|float $default = 0,
        int|float|null $min = null,
        int|float|null $max = null,
        int|float $step = 1,
        string $class = 'small-text',
        string $description = '',
    ): self {
        $page = $this->requirePage();
        $context = [
            'id' => $id,
            'name' => $page->optionName . '[' . $id . ']',
            'class' => $class,
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'description' => $description,
            'default' => $default,
        ];

        return $this->addRendererField($id, $title, 'number', $context, labelFor: true);
    }

    public function textarea(
        string $id,
        string $title,
        string $default = '',
        int $rows = 5,
        int $cols = 50,
        string $class = 'large-text',
        string $description = '',
    ): self {
        $page = $this->requirePage();
        $context = [
            'id' => $id,
            'name' => $page->optionName . '[' . $id . ']',
            'class' => $class,
            'rows' => $rows,
            'cols' => $cols,
            'description' => $description,
            'default' => $default,
        ];

        return $this->addRendererField($id, $title, 'textarea', $context, labelFor: true);
    }

    public function checkbox(
        string $id,
        string $title,
        string $label = '',
        string $description = '',
    ): self {
        $page = $this->requirePage();
        $context = [
            'id' => $id,
            'name' => $page->optionName . '[' . $id . ']',
            'label' => $label,
            'description' => $description,
            'default' => false,
        ];

        return $this->addRendererField($id, $title, 'checkbox', $context, labelFor: false);
    }

    /**
     * @param array<string, string> $choices
     */
    public function select(
        string $id,
        string $title,
        array $choices,
        string $default = '',
        string $description = '',
    ): self {
        $page = $this->requirePage();
        $context = [
            'id' => $id,
            'name' => $page->optionName . '[' . $id . ']',
            'choices' => $choices,
            'description' => $description,
            'default' => $default,
        ];

        return $this->addRendererField($id, $title, 'select', $context, labelFor: true);
    }

    /**
     * @param array<string, string> $choices
     */
    public function radio(
        string $id,
        string $title,
        array $choices,
        string $default = '',
        string $description = '',
    ): self {
        $page = $this->requirePage();
        $context = [
            'id' => $id,
            'name' => $page->optionName . '[' . $id . ']',
            'choices' => $choices,
            'description' => $description,
            'default' => $default,
        ];

        return $this->addRendererField($id, $title, 'radio', $context, labelFor: false);
    }

    /**
     * @return list<FieldDefinition>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function addRendererField(string $id, string $title, string $type, array $context, bool $labelFor): self
    {
        /** @var AbstractSettingsPage $page */
        $page = $this->page;

        $renderCallback = static function (array $wpArgs) use ($page, $type): void {
            $context = $wpArgs['context'] ?? [];
            $default = $context['default'] ?? '';

            if ($type === 'checkbox') {
                $context['checked'] = (bool) $page->getOption($context['id'], $default);
            } else {
                $context['value'] = $page->getOption($context['id'], $default);
            }

            $page->getRenderer()->{$type}($context);
        };

        $args = ['context' => $context];
        if ($labelFor) {
            $args['label_for'] = $id;
        }

        $this->fields[] = new FieldDefinition($id, $title, $renderCallback, $args);

        return $this;
    }

    private function requirePage(): AbstractSettingsPage
    {
        if ($this->page === null) {
            throw new \LogicException(
                'Field type methods require a page reference. Use SettingsConfigurator with a page instance.',
            );
        }

        return $this->page;
    }
}
