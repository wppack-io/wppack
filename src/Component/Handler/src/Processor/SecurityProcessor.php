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

namespace WpPack\Component\Handler\Processor;

use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Exception\SecurityException;
use WpPack\Component\Handler\Security\PathValidator;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;

class SecurityProcessor implements ProcessorInterface
{
    private ?PathValidator $pathValidator = null;

    public function process(Request $request, Configuration $config): Request|Response|null
    {
        $path = $request->getPathInfo();
        $webRoot = $config->get('web_root');

        if ($this->pathValidator === null) {
            $checkSymlinks = $config->get('security.check_symlinks', true);
            $this->pathValidator = new PathValidator($webRoot, $checkSymlinks);
        }

        $this->pathValidator->validate($path);

        $blockedPatterns = $config->get('security.blocked_patterns', []);
        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                throw new SecurityException('Access denied');
            }
        }

        $fullPath = $webRoot . $path;
        if (file_exists($fullPath)) {
            $this->pathValidator->validateFilePath($fullPath);
        }

        return null;
    }
}
