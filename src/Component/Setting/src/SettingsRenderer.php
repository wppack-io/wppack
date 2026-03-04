<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

class SettingsRenderer
{
    // ─── Page Templates ───

    public function renderPage(AbstractSettingsPage $page): void
    {
        $this->renderHeader($page);
        $this->renderForm($page);
        $this->renderFooter();
    }

    public function renderHeader(AbstractSettingsPage $page): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
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
            esc_attr($context['id']),
            esc_attr($context['name']),
            esc_attr($context['value']),
            esc_attr($context['class']),
            esc_attr($context['placeholder']),
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
            esc_attr($context['id']),
            esc_attr($context['name']),
            esc_attr($context['value']),
            esc_attr($context['class']),
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
            esc_attr($context['id']),
            esc_attr($context['name']),
            esc_attr($context['value']),
            esc_attr($context['class']),
            esc_attr($context['placeholder']),
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
            esc_attr($context['id']),
            esc_attr($context['name']),
            esc_attr($context['value']),
            esc_attr($context['class']),
            esc_attr($context['placeholder']),
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
            esc_attr($context['id']),
            esc_attr($context['name']),
            esc_attr((string) $context['value']),
            esc_attr($context['class']),
            esc_attr((string) $context['step']),
        );

        if ($context['min'] !== null) {
            $attrs .= sprintf(' min="%s"', esc_attr((string) $context['min']));
        }
        if ($context['max'] !== null) {
            $attrs .= sprintf(' max="%s"', esc_attr((string) $context['max']));
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
            esc_attr($context['id']),
            esc_attr($context['name']),
            esc_attr($context['class']),
            $context['rows'],
            $context['cols'],
            esc_textarea($context['value']),
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
            esc_attr($context['name']),
        );
        printf(
            '<label for="%s"><input type="checkbox" id="%s" name="%s" value="1"%s /> %s</label>',
            esc_attr($context['id']),
            esc_attr($context['id']),
            esc_attr($context['name']),
            $context['checked'] ? ' checked="checked"' : '',
            esc_html($context['label']),
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
            esc_attr($context['id']),
            esc_attr($context['name']),
        );
        foreach ($context['choices'] as $optionValue => $optionLabel) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr((string) $optionValue),
                $context['value'] === (string) $optionValue ? ' selected="selected"' : '',
                esc_html($optionLabel),
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
                esc_attr($context['name']),
                esc_attr((string) $optionValue),
                $context['value'] === (string) $optionValue ? ' checked="checked"' : '',
                esc_html($optionLabel),
            );
            $first = false;
        }
        echo '</fieldset>';
        $this->renderDescription($context['description']);
    }

    protected function renderDescription(string $description): void
    {
        if ($description !== '') {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }
}
