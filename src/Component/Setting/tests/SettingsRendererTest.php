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

namespace WpPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Setting\AbstractSettingsPage;
use WpPack\Component\Setting\Attribute\AsSettingsPage;
use WpPack\Component\Escaper\Escaper;
use WpPack\Component\Setting\SettingsConfigurator;
use WpPack\Component\Setting\SettingsRenderer;

final class SettingsRendererTest extends TestCase
{
    private SettingsRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new SettingsRenderer();
    }

    #[Test]
    public function textRendersCorrectHtml(): void
    {
        $context = [
            'id' => 'api_key',
            'name' => 'my_opts[api_key]',
            'value' => 'test-key',
            'class' => 'regular-text',
            'placeholder' => 'Enter key',
            'description' => '',
        ];

        ob_start();
        $this->renderer->text($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<input type="text"', $html);
        self::assertStringContainsString('id="api_key"', $html);
        self::assertStringContainsString('name="my_opts[api_key]"', $html);
        self::assertStringContainsString('value="test-key"', $html);
        self::assertStringContainsString('class="regular-text"', $html);
        self::assertStringContainsString('placeholder="Enter key"', $html);
    }

    #[Test]
    public function passwordRendersCorrectHtml(): void
    {
        $context = [
            'id' => 'secret',
            'name' => 'my_opts[secret]',
            'value' => 's3cret',
            'class' => 'regular-text',
            'description' => '',
        ];

        ob_start();
        $this->renderer->password($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<input type="password"', $html);
        self::assertStringContainsString('id="secret"', $html);
        self::assertStringContainsString('name="my_opts[secret]"', $html);
    }

    #[Test]
    public function urlRendersCorrectHtml(): void
    {
        $context = [
            'id' => 'webhook',
            'name' => 'my_opts[webhook]',
            'value' => 'https://example.com',
            'class' => 'regular-text code',
            'placeholder' => 'https://...',
            'description' => '',
        ];

        ob_start();
        $this->renderer->url($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<input type="url"', $html);
        self::assertStringContainsString('class="regular-text code"', $html);
        self::assertStringContainsString('value="https://example.com"', $html);
    }

    #[Test]
    public function emailRendersCorrectHtml(): void
    {
        $context = [
            'id' => 'notify_to',
            'name' => 'my_opts[notify_to]',
            'value' => 'test@example.com',
            'class' => 'regular-text',
            'placeholder' => '',
            'description' => '',
        ];

        ob_start();
        $this->renderer->email($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<input type="email"', $html);
        self::assertStringContainsString('value="test@example.com"', $html);
    }

    #[Test]
    public function numberRendersCorrectHtml(): void
    {
        $context = [
            'id' => 'cache_ttl',
            'name' => 'my_opts[cache_ttl]',
            'value' => '300',
            'class' => 'small-text',
            'min' => 0,
            'max' => 3600,
            'step' => 60,
            'description' => '',
        ];

        ob_start();
        $this->renderer->number($context);
        $html = ob_get_clean();

        self::assertStringContainsString('type="number"', $html);
        self::assertStringContainsString('value="300"', $html);
        self::assertStringContainsString('min="0"', $html);
        self::assertStringContainsString('max="3600"', $html);
        self::assertStringContainsString('step="60"', $html);
    }

    #[Test]
    public function numberOmitsMinMaxWhenNull(): void
    {
        $context = [
            'id' => 'count',
            'name' => 'my_opts[count]',
            'value' => '5',
            'class' => 'small-text',
            'min' => null,
            'max' => null,
            'step' => 1,
            'description' => '',
        ];

        ob_start();
        $this->renderer->number($context);
        $html = ob_get_clean();

        self::assertStringNotContainsString('min=', $html);
        self::assertStringNotContainsString('max=', $html);
    }

    #[Test]
    public function textareaRendersCorrectHtml(): void
    {
        $context = [
            'id' => 'bio',
            'name' => 'my_opts[bio]',
            'value' => 'Hello world',
            'class' => 'large-text',
            'rows' => 5,
            'cols' => 50,
            'description' => '',
        ];

        ob_start();
        $this->renderer->textarea($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<textarea', $html);
        self::assertStringContainsString('id="bio"', $html);
        self::assertStringContainsString('rows="5"', $html);
        self::assertStringContainsString('cols="50"', $html);
        self::assertStringContainsString('Hello world', $html);
    }

    #[Test]
    public function checkboxRendersCheckedHtml(): void
    {
        $context = [
            'id' => 'debug',
            'name' => 'my_opts[debug]',
            'checked' => true,
            'label' => 'Enable debug mode',
            'description' => '',
        ];

        ob_start();
        $this->renderer->checkbox($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<input type="hidden"', $html);
        self::assertStringContainsString('value="0"', $html);
        self::assertStringContainsString('<input type="checkbox"', $html);
        self::assertStringContainsString('checked="checked"', $html);
        self::assertStringContainsString('Enable debug mode', $html);
    }

    #[Test]
    public function checkboxRendersUncheckedHtml(): void
    {
        $context = [
            'id' => 'debug',
            'name' => 'my_opts[debug]',
            'checked' => false,
            'label' => 'Enable debug mode',
            'description' => '',
        ];

        ob_start();
        $this->renderer->checkbox($context);
        $html = ob_get_clean();

        self::assertStringNotContainsString('checked="checked"', $html);
    }

    #[Test]
    public function selectRendersWithSelectedOption(): void
    {
        $context = [
            'id' => 'log_level',
            'name' => 'my_opts[log_level]',
            'value' => 'info',
            'choices' => ['debug' => 'Debug', 'info' => 'Info', 'error' => 'Error'],
            'description' => '',
        ];

        ob_start();
        $this->renderer->select($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<select', $html);
        self::assertStringContainsString('<option value="debug">Debug</option>', $html);
        self::assertStringContainsString('<option value="info" selected="selected">Info</option>', $html);
        self::assertStringContainsString('<option value="error">Error</option>', $html);
    }

    #[Test]
    public function radioRendersWithCheckedOption(): void
    {
        $context = [
            'id' => 'method',
            'name' => 'my_opts[method]',
            'value' => 'post',
            'choices' => ['get' => 'GET', 'post' => 'POST'],
            'description' => '',
        ];

        ob_start();
        $this->renderer->radio($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<fieldset>', $html);
        self::assertStringContainsString('value="get" />', $html);
        self::assertStringContainsString('value="post" checked="checked"', $html);
        self::assertStringContainsString('GET</label>', $html);
        self::assertStringContainsString('POST</label>', $html);
    }

    #[Test]
    public function descriptionRendersWhenProvided(): void
    {
        $context = [
            'id' => 'api_key',
            'name' => 'my_opts[api_key]',
            'value' => '',
            'class' => 'regular-text',
            'placeholder' => '',
            'description' => 'Your API key',
        ];

        ob_start();
        $this->renderer->text($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<p class="description">Your API key</p>', $html);
    }

    #[Test]
    public function descriptionOmittedWhenEmpty(): void
    {
        $context = [
            'id' => 'api_key',
            'name' => 'my_opts[api_key]',
            'value' => '',
            'class' => 'regular-text',
            'placeholder' => '',
            'description' => '',
        ];

        ob_start();
        $this->renderer->text($context);
        $html = ob_get_clean();

        self::assertStringNotContainsString('<p class="description">', $html);
    }

    #[Test]
    public function customRendererOverridesDefault(): void
    {
        $renderer = new CustomTestRenderer();

        $context = [
            'id' => 'api_key',
            'name' => 'my_opts[api_key]',
            'value' => 'test',
            'class' => 'regular-text',
            'placeholder' => '',
            'description' => '',
        ];

        ob_start();
        $renderer->text($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<div class="custom-field">', $html);
        self::assertStringContainsString('value="test"', $html);
    }

    #[Test]
    public function renderPageOutputsFullPage(): void
    {
        global $title;
        $title = 'Test Page';

        $page = new CustomRendererTestSettingsPage();

        ob_start();
        $this->renderer->renderPage($page);
        $output = ob_get_clean();

        self::assertStringContainsString('<div class="wrap">', $output);
        self::assertStringContainsString('<h1>', $output);
        self::assertStringContainsString('<form', $output);
        self::assertStringContainsString('</form>', $output);
        self::assertStringContainsString('</div>', $output);
    }

    #[Test]
    public function renderHeaderOutputsWrapAndTitle(): void
    {
        global $title;
        $title = 'Test Page';

        $page = new CustomRendererTestSettingsPage();

        ob_start();
        $this->renderer->renderHeader($page);
        $output = ob_get_clean();

        self::assertStringContainsString('<div class="wrap">', $output);
        self::assertStringContainsString('<h1>', $output);
    }

    #[Test]
    public function renderFormOutputsFormTag(): void
    {
        $page = new CustomRendererTestSettingsPage();

        ob_start();
        $this->renderer->renderForm($page);
        $output = ob_get_clean();

        self::assertStringContainsString('<form', $output);
        self::assertStringContainsString('</form>', $output);
    }

    #[Test]
    public function renderFooterOutputsClosingDiv(): void
    {
        ob_start();
        $this->renderer->renderFooter();
        $output = ob_get_clean();

        self::assertStringContainsString('</div>', $output);
    }

    #[Test]
    public function textRendersCorrectlyWithEscaper(): void
    {
        $renderer = new SettingsRenderer(new Escaper());

        $context = [
            'id' => 'api_key',
            'name' => 'my_opts[api_key]',
            'value' => '<script>alert("xss")</script>',
            'class' => 'regular-text',
            'placeholder' => '',
            'description' => '',
        ];

        ob_start();
        $renderer->text($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<input type="text"', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    #[Test]
    public function textareaRendersCorrectlyWithEscaper(): void
    {
        $renderer = new SettingsRenderer(new Escaper());

        $context = [
            'id' => 'bio',
            'name' => 'my_opts[bio]',
            'value' => '<b>bold</b>',
            'class' => 'large-text',
            'rows' => 5,
            'cols' => 50,
            'description' => '',
        ];

        ob_start();
        $renderer->textarea($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<textarea', $html);
        self::assertStringNotContainsString('<b>bold</b>', $html);
    }

    #[Test]
    public function selectRendersCorrectlyWithEscaper(): void
    {
        $renderer = new SettingsRenderer(new Escaper());

        $context = [
            'id' => 'log_level',
            'name' => 'my_opts[log_level]',
            'value' => 'info',
            'choices' => ['debug' => 'Debug', 'info' => 'Info'],
            'description' => 'Choose level',
        ];

        ob_start();
        $renderer->select($context);
        $html = ob_get_clean();

        self::assertStringContainsString('<select', $html);
        self::assertStringContainsString('Choose level', $html);
    }

    #[Test]
    public function customRendererMethodCalledViaFieldString(): void
    {
        $page = new CustomRendererTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->field('content', 'Content', 'wysiwyg');

        $fields = $section->getFields();

        self::assertCount(1, $fields);
        self::assertSame('content', $fields[0]->id);
    }
}

class CustomTestRenderer extends SettingsRenderer
{
    public function text(array $context): void
    {
        printf(
            '<div class="custom-field"><input type="text" id="%s" name="%s" value="%s" /></div>',
            esc_attr($context['id']),
            esc_attr($context['name']),
            esc_attr($context['value']),
        );
    }

    public function wysiwyg(array $context): void
    {
        printf('<div class="wysiwyg-editor" id="%s"></div>', esc_attr($context['id']));
    }
}

#[AsSettingsPage(slug: 'custom-renderer-test', label: 'Custom Renderer Test')]
class CustomRendererTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}

    protected function createRenderer(): SettingsRenderer
    {
        return new CustomTestRenderer();
    }
}
