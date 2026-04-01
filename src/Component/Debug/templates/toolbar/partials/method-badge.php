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
/**
 * HTTP method badge partial.
 *
 * @var string                                                       $method HTTP method (GET, POST, etc.)
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt    Template formatters
 */
echo $view->include('toolbar/partials/badge', ['label' => $method, 'color' => $fmt->methodColor($method)]);
