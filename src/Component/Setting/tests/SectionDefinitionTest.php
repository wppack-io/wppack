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

namespace WPPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Option\OptionManager;
use WPPack\Component\Setting\AbstractSettingsPage;
use WPPack\Component\Setting\Attribute\AsSettingsPage;
use WPPack\Component\Setting\SectionDefinition;
use WPPack\Component\Setting\SettingsConfigurator;
use WPPack\Component\Setting\SettingsRenderer;

final class SectionDefinitionTest extends TestCase
{
    #[Test]
    public function textAddsFieldDefinition(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->text('api_key', 'API Key');

        $fields = $section->getFields();

        self::assertCount(1, $fields);
        self::assertSame('api_key', $fields[0]->id);
        self::assertSame('API Key', $fields[0]->title);
    }

    #[Test]
    public function textSetsLabelForInArgs(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->text('api_key', 'API Key');

        $fields = $section->getFields();

        self::assertSame('api_key', $fields[0]->args['label_for']);
    }

    #[Test]
    public function checkboxDoesNotSetLabelFor(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->checkbox('debug', 'Debug Mode');

        $fields = $section->getFields();

        self::assertArrayNotHasKey('label_for', $fields[0]->args);
    }

    #[Test]
    public function radioDoesNotSetLabelFor(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->radio('method', 'HTTP Method', ['get' => 'GET', 'post' => 'POST']);

        $fields = $section->getFields();

        self::assertArrayNotHasKey('label_for', $fields[0]->args);
    }

    #[Test]
    public function selectSetsLabelFor(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->select('log_level', 'Log Level', ['debug' => 'Debug', 'info' => 'Info']);

        $fields = $section->getFields();

        self::assertSame('log_level', $fields[0]->args['label_for']);
    }

    #[Test]
    public function fieldTypeMethodsRequirePage(): void
    {
        $section = new SectionDefinition('general', 'General');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Field type methods require a page reference');

        $section->text('api_key', 'API Key');
    }

    #[Test]
    public function fieldTypeMethodsAreChainable(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');

        $result = $section
            ->text('api_key', 'API Key')
            ->password('secret', 'Secret')
            ->url('webhook', 'Webhook URL')
            ->email('notify', 'Notify Email')
            ->number('cache_ttl', 'Cache TTL')
            ->textarea('bio', 'Bio')
            ->checkbox('debug', 'Debug Mode')
            ->select('log_level', 'Log Level', ['debug' => 'Debug'])
            ->radio('method', 'Method', ['get' => 'GET']);

        self::assertSame($section, $result);
        self::assertCount(9, $section->getFields());
    }

    #[Test]
    public function fieldWithStringDelegatesToRenderer(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->field('api_key', 'API Key', 'text', ['id' => 'api_key', 'name' => 'opts[api_key]']);

        $fields = $section->getFields();

        self::assertCount(1, $fields);
        self::assertSame('api_key', $fields[0]->id);
    }

    #[Test]
    public function fieldWithStringThrowsForMissingRendererMethod(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not have a "nonexistent" method');

        $section->field('api_key', 'API Key', 'nonexistent');
    }

    #[Test]
    public function fieldWithStringRequiresPage(): void
    {
        $section = new SectionDefinition('general', 'General');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Field type methods require a page reference');

        $section->field('api_key', 'API Key', 'text');
    }

    #[Test]
    public function fieldWithClosureStillWorks(): void
    {
        $callback = static fn(array $args) => null;
        $section = new SectionDefinition('general', 'General');

        $section->field('api_key', 'API Key', $callback);

        $fields = $section->getFields();

        self::assertCount(1, $fields);
        self::assertSame($callback, $fields[0]->renderCallback);
    }

    #[Test]
    public function textContextContainsCorrectName(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->text('api_key', 'API Key', placeholder: 'Enter key', description: 'Your API key');

        $fields = $section->getFields();
        $context = $fields[0]->args['context'];

        self::assertSame('api_key', $context['id']);
        self::assertSame('field_type_test[api_key]', $context['name']);
        self::assertSame('regular-text', $context['class']);
        self::assertSame('Enter key', $context['placeholder']);
        self::assertSame('Your API key', $context['description']);
    }

    #[Test]
    public function numberContextContainsMinMaxStep(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->number('cache_ttl', 'Cache TTL', min: 0, max: 3600, step: 60);

        $fields = $section->getFields();
        $context = $fields[0]->args['context'];

        self::assertSame(0, $context['min']);
        self::assertSame(3600, $context['max']);
        self::assertSame(60, $context['step']);
    }

    #[Test]
    public function checkboxContextContainsLabel(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->checkbox('debug', 'Debug Mode', label: 'Enable debug mode');

        $fields = $section->getFields();
        $context = $fields[0]->args['context'];

        self::assertSame('Enable debug mode', $context['label']);
    }

    #[Test]
    public function selectContextContainsChoices(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);
        $choices = ['debug' => 'Debug', 'info' => 'Info', 'error' => 'Error'];

        $section = $configurator->section('general', 'General');
        $section->select('log_level', 'Log Level', $choices);

        $fields = $section->getFields();
        $context = $fields[0]->args['context'];

        self::assertSame($choices, $context['choices']);
    }

    #[Test]
    public function passwordContextContainsCorrectName(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->password('secret', 'Secret Key');

        $fields = $section->getFields();
        $context = $fields[0]->args['context'];

        self::assertSame('secret', $context['id']);
        self::assertSame('field_type_test[secret]', $context['name']);
        self::assertSame('regular-text', $context['class']);
    }

    #[Test]
    public function urlContextContainsCorrectDefaults(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->url('webhook', 'Webhook URL');

        $fields = $section->getFields();
        $context = $fields[0]->args['context'];

        self::assertSame('regular-text code', $context['class']);
    }

    #[Test]
    public function emailContextContainsCorrectName(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->email('notify', 'Notify Email');

        $fields = $section->getFields();
        $context = $fields[0]->args['context'];

        self::assertSame('notify', $context['id']);
        self::assertSame('field_type_test[notify]', $context['name']);
    }

    #[Test]
    public function textareaContextContainsRowsAndCols(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);

        $section = $configurator->section('general', 'General');
        $section->textarea('bio', 'Biography');

        $fields = $section->getFields();
        $context = $fields[0]->args['context'];

        self::assertSame(5, $context['rows']);
        self::assertSame(50, $context['cols']);
    }

    #[Test]
    public function radioContextContainsChoices(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $configurator = new SettingsConfigurator($page);
        $choices = ['get' => 'GET', 'post' => 'POST', 'put' => 'PUT'];

        $section = $configurator->section('general', 'General');
        $section->radio('method', 'HTTP Method', $choices);

        $fields = $section->getFields();
        $context = $fields[0]->args['context'];

        self::assertSame($choices, $context['choices']);
    }

    #[Test]
    public function rendererFieldCallbackRendersNonCheckboxField(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $page->setOptionManager(new OptionManager());
        $configurator = new SettingsConfigurator($page);

        update_option($page->optionName, ['api_key' => 'stored-value']);

        $section = $configurator->section('general', 'General');
        $section->text('api_key', 'API Key');

        $fields = $section->getFields();
        $callback = $fields[0]->renderCallback;

        ob_start();
        $callback($fields[0]->args);
        $output = ob_get_clean();

        self::assertStringContainsString('type="text"', $output);
        self::assertStringContainsString('value="stored-value"', $output);
        self::assertStringContainsString('id="api_key"', $output);

        delete_option($page->optionName);
    }

    #[Test]
    public function rendererFieldCallbackRendersCheckboxField(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $page->setOptionManager(new OptionManager());
        $configurator = new SettingsConfigurator($page);

        update_option($page->optionName, ['debug' => true]);

        $section = $configurator->section('general', 'General');
        $section->checkbox('debug', 'Debug Mode', label: 'Enable');

        $fields = $section->getFields();
        $callback = $fields[0]->renderCallback;

        ob_start();
        $callback($fields[0]->args);
        $output = ob_get_clean();

        self::assertStringContainsString('type="checkbox"', $output);
        self::assertStringContainsString('checked="checked"', $output);
        self::assertStringContainsString('Enable', $output);

        delete_option($page->optionName);
    }

    #[Test]
    public function fieldWithStringTypeCallbackInvokesRenderer(): void
    {
        $page = new FieldTypeTestSettingsPage();
        $page->setOptionManager(new OptionManager());
        $configurator = new SettingsConfigurator($page);

        update_option($page->optionName, ['api_key' => 'my-key']);

        $section = $configurator->section('general', 'General');
        $section->field('api_key', 'API Key', 'text', [
            'id' => 'api_key',
            'name' => 'field_type_test[api_key]',
            'default' => '',
            'class' => 'regular-text',
            'placeholder' => '',
            'description' => '',
        ]);

        $fields = $section->getFields();
        $callback = $fields[0]->renderCallback;

        ob_start();
        $callback($fields[0]->args);
        $output = ob_get_clean();

        self::assertStringContainsString('type="text"', $output);
        self::assertStringContainsString('value="my-key"', $output);

        delete_option($page->optionName);
    }
}

#[AsSettingsPage(slug: 'field-type-test', label: 'Field Type Test')]
class FieldTypeTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void {}
}
