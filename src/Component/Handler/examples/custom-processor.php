<?php

declare(strict_types=1);

/**
 * Custom processor — add maintenance mode to the processor chain.
 */

use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Handler;
use WpPack\Component\Handler\Processor\ProcessorInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;

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

$request = Request::createFromGlobals();
$handler->handle($request);
