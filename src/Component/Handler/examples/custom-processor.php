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

/**
 * Custom processor — add maintenance mode to the processor chain.
 */

use WPPack\Component\Handler\Configuration;
use WPPack\Component\Handler\Handler;
use WPPack\Component\Handler\Processor\ProcessorInterface;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

class MaintenanceProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        $maintenanceFile = $config->get('web_root') . '/.maintenance';

        if (file_exists($maintenanceFile)) {
            return new Response('Site is under maintenance. Please try again later.', 503);
        }

        return null;
    }
}

$config = new Configuration(['web_root' => __DIR__]);
$handler = new Handler($config);
$handler->addProcessor(new MaintenanceProcessor(), priority: 1);

$result = $handler->run();
if ($result !== null) {
    require $result;
}
