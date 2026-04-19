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

namespace WPPack\Component\Setting;

use WPPack\Component\Escaper\Escaper;

class SettingsRenderer
{
    public function __construct(
        private readonly ?Escaper $escaper = null,
    ) {}

    // ─── Page Templates ───

    public function renderPage(AbstractSettingsPage $page): void
    {
        $this->renderHeader($page);
        $this->renderForm($page);
        $this->renderFooter();
    }

    public function renderHeader(AbstractSettingsPage $page): void
    {
        global $plugin_page;
        $plugin_page ??= '';

        echo '<div class="wrap">';
        echo '<h1>' . $this->escHtml(get_admin_page_title()) . '</h1>';
    }

    public function renderForm(AbstractSettingsPage $page): void
    {
        echo '<form method="post" action="options.php">';
        settings_fields($page->optionGroup);
        do_settings_sections($page->slug);
        submit_button();
        echo '</form>';
    }

    public function renderFooter(): void
    {
        echo '</div>';
    }

    // ─── Field Templates ───

    /**
     * @param array{id: string, name: string, value: string, class: string, placeholder: string, description: string} $context
     */
    public function text(array $context): void
    {
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="%s" placeholder="%s" />',
            $this->escAttr($context['id']),
            $this->escAttr($context['name']),
            $this->escAttr($context['value']),
            $this->escAttr($context['class']),
            $this->escAttr($context['placeholder']),
        );
        $this->renderDescription($context['description']);
    }

    /**
     * @param array{id: string, name: string, value: string, class: string, description: string} $context
     */
    public function password(array $context): void
    {
        printf(
            '<input type="password" id="%s" name="%s" value="%s" class="%s" />',
            $this->escAttr($context['id']),
            $this->escAttr($context['name']),
            $this->escAttr($context['value']),
            $this->escAttr($context['class']),
        );
        $this->renderDescription($context['description']);
    }

    /**
     * @param array{id: string, name: string, value: string, class: string, placeholder: string, description: string} $context
     */
    public function url(array $context): void
    {
        printf(
            '<input type="url" id="%s" name="%s" value="%s" class="%s" placeholder="%s" />',
            $this->escAttr($context['id']),
            $this->escAttr($context['name']),
            $this->escAttr($context['value']),
            $this->escAttr($context['class']),
            $this->escAttr($context['placeholder']),
        );
        $this->renderDescription($context['description']);
    }

    /**
     * @param array{id: string, name: string, value: string, class: string, placeholder: string, description: string} $context
     */
    public function email(array $context): void
    {
        printf(
            '<input type="email" id="%s" name="%s" value="%s" class="%s" placeholder="%s" />',
            $this->escAttr($context['id']),
            $this->escAttr($context['name']),
            $this->escAttr($context['value']),
            $this->escAttr($context['class']),
            $this->escAttr($context['placeholder']),
        );
        $this->renderDescription($context['description']);
    }

    /**
     * @param array{id: string, name: string, value: string, class: string, min: int|float|null, max: int|float|null, step: int|float, description: string} $context
     */
    public function number(array $context): void
    {
        $attrs = sprintf(
            'type="number" id="%s" name="%s" value="%s" class="%s" step="%s"',
            $this->escAttr($context['id']),
            $this->escAttr($context['name']),
            $this->escAttr((string) $context['value']),
            $this->escAttr($context['class']),
            $this->escAttr((string) $context['step']),
        );

        if ($context['min'] !== null) {
            $attrs .= sprintf(' min="%s"', $this->escAttr((string) $context['min']));
        }
        if ($context['max'] !== null) {
            $attrs .= sprintf(' max="%s"', $this->escAttr((string) $context['max']));
        }

        echo '<input ' . $attrs . ' />';
        $this->renderDescription($context['description']);
    }

    /**
     * @param array{id: string, name: string, value: string, class: string, rows: int, cols: int, description: string} $context
     */
    public function textarea(array $context): void
    {
        printf(
            '<textarea id="%s" name="%s" class="%s" rows="%d" cols="%d">%s</textarea>',
            $this->escAttr($context['id']),
            $this->escAttr($context['name']),
            $this->escAttr($context['class']),
            $context['rows'],
            $context['cols'],
            $this->escTextarea($context['value']),
        );
        $this->renderDescription($context['description']);
    }

    /**
     * @param array{id: string, name: string, checked: bool, label: string, description: string} $context
     */
    public function checkbox(array $context): void
    {
        printf(
            '<input type="hidden" name="%s" value="0" />',
            $this->escAttr($context['name']),
        );
        printf(
            '<label for="%s"><input type="checkbox" id="%s" name="%s" value="1"%s /> %s</label>',
            $this->escAttr($context['id']),
            $this->escAttr($context['id']),
            $this->escAttr($context['name']),
            $context['checked'] ? ' checked="checked"' : '',
            $this->escHtml($context['label']),
        );
        $this->renderDescription($context['description']);
    }

    /**
     * @param array{id: string, name: string, value: string, choices: array<string, string>, description: string} $context
     */
    public function select(array $context): void
    {
        printf(
            '<select id="%s" name="%s">',
            $this->escAttr($context['id']),
            $this->escAttr($context['name']),
        );
        foreach ($context['choices'] as $optionValue => $optionLabel) {
            printf(
                '<option value="%s"%s>%s</option>',
                $this->escAttr((string) $optionValue),
                $context['value'] === (string) $optionValue ? ' selected="selected"' : '',
                $this->escHtml($optionLabel),
            );
        }
        echo '</select>';
        $this->renderDescription($context['description']);
    }

    /**
     * @param array{id: string, name: string, value: string, choices: array<string, string>, description: string} $context
     */
    public function radio(array $context): void
    {
        echo '<fieldset>';
        $first = true;
        foreach ($context['choices'] as $optionValue => $optionLabel) {
            if (!$first) {
                echo '<br />';
            }
            printf(
                '<label><input type="radio" name="%s" value="%s"%s /> %s</label>',
                $this->escAttr($context['name']),
                $this->escAttr((string) $optionValue),
                $context['value'] === (string) $optionValue ? ' checked="checked"' : '',
                $this->escHtml($optionLabel),
            );
            $first = false;
        }
        echo '</fieldset>';
        $this->renderDescription($context['description']);
    }

    protected function renderDescription(string $description): void
    {
        if ($description !== '') {
            printf('<p class="description">%s</p>', $this->escHtml($description));
        }
    }

    // ─── Escape Helpers ───

    private function escHtml(string $value): string
    {
        if ($this->escaper !== null) {
            return $this->escaper->html($value);
        }

        return esc_html($value);
    }

    private function escAttr(string $value): string
    {
        if ($this->escaper !== null) {
            return $this->escaper->attr($value);
        }

        return esc_attr($value);
    }

    private function escTextarea(string $value): string
    {
        if ($this->escaper !== null) {
            return $this->escaper->textarea($value);
        }

        return esc_textarea($value);
    }
}
