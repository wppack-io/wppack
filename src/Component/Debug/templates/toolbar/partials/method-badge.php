<?php

declare(strict_types=1);
/**
 * HTTP method badge partial.
 *
 * @var string                                                       $method HTTP method (GET, POST, etc.)
 * @var \WpPack\Component\Debug\Toolbar\Panel\TemplateFormatters     $fmt    Template formatters
 */
echo $view->include('toolbar/partials/badge', ['label' => $method, 'color' => $fmt->methodColor($method)]);
