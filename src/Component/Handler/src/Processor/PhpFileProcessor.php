<?php

declare(strict_types=1);

namespace WpPack\Component\Handler\Processor;

use WpPack\Component\Handler\Configuration;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;

class PhpFileProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        $path = $request->server->get('PHP_SELF');
        $webRoot = $config->get('web_root');
        $fullPath = $webRoot . $path;

        if (is_file($fullPath) && str_ends_with($path, '.php')) {
            $request->server->set('SCRIPT_NAME', $path);
            $request->server->set('SCRIPT_FILENAME', $fullPath);

            return $request;
        }

        return null;
    }
}
