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

namespace WpPack\Component\Templating\Bridge\Twig;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use WpPack\Component\Templating\Exception\RenderingException;
use WpPack\Component\Templating\Exception\TemplateNotFoundException;
use WpPack\Component\Templating\TemplateRendererInterface;

final class TwigRenderer implements TemplateRendererInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function render(string $template, array $context = []): string
    {
        $name = $this->resolveName($template);

        try {
            return $this->twig->render($name, $context);
        } catch (LoaderError $e) {
            throw new TemplateNotFoundException($template, $e);
        } catch (RuntimeError | SyntaxError $e) {
            throw new RenderingException($e->getMessage(), 0, $e);
        }
    }

    public function exists(string $template): bool
    {
        $name = $this->resolveName($template);

        return $this->twig->getLoader()->exists($name);
    }

    public function supports(string $template): bool
    {
        return $this->exists($template);
    }

    public function getEnvironment(): Environment
    {
        return $this->twig;
    }

    private function resolveName(string $template): string
    {
        if (str_ends_with($template, '.twig')) {
            return $template;
        }

        return $template . '.html.twig';
    }
}
