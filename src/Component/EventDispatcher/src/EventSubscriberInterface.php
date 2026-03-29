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

namespace WpPack\Component\EventDispatcher;

interface EventSubscriberInterface
{
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names (FQCN or WordPress hook names).
     * The values can be:
     *
     *  - A method name:                       'methodName'
     *  - An array of [method, priority]:      ['methodName', 20]
     *  - An array of [method, priority, acceptedArgs]:           ['methodName', 10, 3]  (default: PHP_INT_MAX, accepts all)
     *  - An array of [method, priority, acceptedArgs, eventClass]: ['methodName', 10, PHP_INT_MAX, EventClass::class]
     *  - An array of arrays for multiple listeners:
     *      [['methodName1', 10], ['methodName2', 20]]
     *
     * @return array<string, string|array{string, int}|array{string, int, int}|array{string, int, int, class-string<WordPressEvent>}|list<array{string, int}|array{string, int, int}|array{string, int, int, class-string<WordPressEvent>}>>
     */
    public static function getSubscribedEvents(): array;
}
