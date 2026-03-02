<?php

declare(strict_types=1);

namespace WpPack\Component\Taxonomy\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Taxonomy\Attribute\Action\CreateTermAction;
use WpPack\Component\Taxonomy\Attribute\Action\DeleteTermAction;
use WpPack\Component\Taxonomy\Attribute\Action\EditTermAction;
use WpPack\Component\Taxonomy\Attribute\Action\PreGetTermsAction;
use WpPack\Component\Taxonomy\Attribute\Action\RegisteredTaxonomyAction;
use WpPack\Component\Taxonomy\Attribute\Filter\GetTermsFilter;
use WpPack\Component\Taxonomy\Attribute\Filter\TermLinkFilter;
use WpPack\Component\Taxonomy\Attribute\Filter\TermsClausesFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function createTermActionHasCorrectHookName(): void
    {
        $action = new CreateTermAction(taxonomy: 'category');

        self::assertSame('create_category', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function createTermActionAcceptsCustomPriority(): void
    {
        $action = new CreateTermAction(taxonomy: 'category', priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function createTermActionPropertyIsAccessible(): void
    {
        $action = new CreateTermAction(taxonomy: 'category');

        self::assertSame('category', $action->taxonomy);
    }

    #[Test]
    public function deleteTermActionHasCorrectHookName(): void
    {
        $action = new DeleteTermAction(taxonomy: 'category');

        self::assertSame('delete_category', $action->hook);
    }

    #[Test]
    public function editTermActionHasCorrectHookName(): void
    {
        $action = new EditTermAction(taxonomy: 'category');

        self::assertSame('edit_category', $action->hook);
    }

    #[Test]
    public function preGetTermsActionHasCorrectHookName(): void
    {
        $action = new PreGetTermsAction();

        self::assertSame('pre_get_terms', $action->hook);
    }

    #[Test]
    public function registeredTaxonomyActionHasCorrectHookName(): void
    {
        $action = new RegisteredTaxonomyAction();

        self::assertSame('registered_taxonomy', $action->hook);
    }

    #[Test]
    public function getTermsFilterHasCorrectHookName(): void
    {
        $filter = new GetTermsFilter();

        self::assertSame('get_terms', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function termLinkFilterHasCorrectHookName(): void
    {
        $filter = new TermLinkFilter();

        self::assertSame('term_link', $filter->hook);
    }

    #[Test]
    public function termsClausesFilterHasCorrectHookName(): void
    {
        $filter = new TermsClausesFilter();

        self::assertSame('terms_clauses', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new CreateTermAction(taxonomy: 'test'));
        self::assertInstanceOf(Action::class, new DeleteTermAction(taxonomy: 'test'));
        self::assertInstanceOf(Action::class, new EditTermAction(taxonomy: 'test'));
        self::assertInstanceOf(Action::class, new PreGetTermsAction());
        self::assertInstanceOf(Action::class, new RegisteredTaxonomyAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new GetTermsFilter());
        self::assertInstanceOf(Filter::class, new TermLinkFilter());
        self::assertInstanceOf(Filter::class, new TermsClausesFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[CreateTermAction(taxonomy: 'post_tag')]
            public function onCreateTag(): void {}

            #[GetTermsFilter(priority: 5)]
            public function onGetTerms(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onCreateTag');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('create_post_tag', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onGetTerms');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('get_terms', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
