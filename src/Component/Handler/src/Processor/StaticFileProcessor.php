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

namespace WPPack\Component\Handler\Processor;

use WPPack\Component\Handler\Configuration;
use WPPack\Component\HttpFoundation\BinaryFileResponse;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Mime\MimeTypes;

class StaticFileProcessor implements ProcessorInterface
{
    private readonly MimeTypes $mimeTypes;

    public function __construct()
    {
        $this->mimeTypes = new MimeTypes();
    }

    public function process(Request $request, Configuration $config): Request|Response|false|null
    {
        $path = $request->server->get('PHP_SELF') ?: $request->getPathInfo();
        $webRoot = $config->get('web_root');
        $fullPath = $webRoot . $path;

        if (is_file($fullPath) && !str_ends_with(strtolower($path), '.php')) {
            if (\PHP_SAPI === 'cli-server') {
                return false;
            }

            $mimeType = $this->getMimeType($fullPath);

            return new BinaryFileResponse($fullPath, headers: ['Content-Type' => $mimeType]);
        }

        return null;
    }

    private function getMimeType(string $path): string
    {
        $guessed = $this->mimeTypes->guessMimeType($path);

        if ($guessed !== null && $guessed !== 'application/octet-stream') {
            return $guessed;
        }

        return 'application/octet-stream';
    }
}
