<?php

declare(strict_types=1);

namespace WpPack\Component\Routing;

use WpPack\Component\HttpFoundation\BinaryFileResponse;
use WpPack\Component\HttpFoundation\Exception\HttpException;
use WpPack\Component\HttpFoundation\Exception\NotFoundException;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Routing\Response\BlockTemplateResponse;
use WpPack\Component\Routing\Response\TemplateResponse;

/** @internal */
final class RouteEntry
{
    /** @var list<string> */
    public readonly array $queryVars;

    private ?TemplateResponse $pendingTemplate = null;
    private ?string $pendingBlockTemplate = null;

    /**
     * @param list<array{string, string}> $rewriteTags
     */
    public function __construct(
        public readonly string $name,
        public readonly string $regex,
        public readonly string $query,
        public readonly RoutePosition $position,
        public readonly array $rewriteTags,
        private readonly \Closure $handler,
    ) {
        $this->queryVars = self::parseQueryVars($query);
    }

    public function registerRoute(): void
    {
        foreach ($this->rewriteTags as [$tag, $regex]) {
            add_rewrite_tag($tag, $regex);
        }
        add_rewrite_rule($this->regex, $this->query, $this->position->value);
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function filterQueryVars(array $vars): array
    {
        return array_values(array_unique(array_merge($vars, $this->queryVars)));
    }

    public function handleTemplateRedirect(): void
    {
        foreach ($this->queryVars as $var) {
            $value = get_query_var($var);
            if ($value !== '' && $value !== false) {
                $this->dispatch();

                return;
            }
        }
    }

    public function filterTemplateInclude(string $template): string
    {
        if ($this->pendingTemplate !== null) {
            return $this->pendingTemplate->template;
        }

        if ($this->pendingBlockTemplate !== null) {
            return $this->pendingBlockTemplate;
        }

        return $template;
    }

    private function dispatch(): void
    {
        try {
            $response = ($this->handler)();
        } catch (HttpException $e) {
            $this->handleException($e);

            return;
        }

        if ($response === null) {
            return;
        }

        $this->sendResponse($response);
    }

    private function sendResponse(Response $response): void
    {
        match (true) {
            $response instanceof TemplateResponse => $this->handleTemplate($response),
            $response instanceof BlockTemplateResponse => $this->handleBlockTemplate($response),
            $response instanceof JsonResponse => $this->handleJson($response),
            $response instanceof RedirectResponse => $this->handleRedirect($response),
            $response instanceof BinaryFileResponse => $this->handleFile($response),
            default => $this->handleHtml($response),
        };
    }

    private function handleException(HttpException $e): void
    {
        if ($e instanceof NotFoundException) {
            global $wp_query;
            $wp_query->set_404();
        }

        do_action('wppack_routing_exception', $e);

        $response = apply_filters('wppack_routing_exception_response', null, $e);
        if ($response instanceof Response) {
            $this->sendResponse($response);

            return;
        }

        nocache_headers();

        $blockTemplate = get_block_template(
            get_stylesheet() . '//' . $e->getStatusCode(),
        );
        if ($blockTemplate !== null) {
            $this->handleBlockTemplate(new BlockTemplateResponse(
                (string) $e->getStatusCode(),
                ['exception' => $e],
                $e->getStatusCode(),
            ));

            return;
        }

        $template = locate_template([sprintf('%d.php', $e->getStatusCode())]);
        if ($template !== '') {
            $this->handleTemplate(new TemplateResponse(
                $template,
                ['exception' => $e],
                $e->getStatusCode(),
            ));

            return;
        }

        wp_die(
            $e->getMessage(),
            $e->getErrorCode(),
            ['response' => $e->getStatusCode()],
        );
    }

    private function sendHeaders(Response $response): void
    {
        status_header($response->statusCode);
        foreach ($response->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }
    }

    private function handleTemplate(TemplateResponse $response): void
    {
        $this->sendHeaders($response);
        foreach ($response->context as $key => $value) {
            set_query_var($key, $value);
        }
        $this->pendingTemplate = $response;
    }

    private function handleBlockTemplate(BlockTemplateResponse $response): void
    {
        $this->sendHeaders($response);
        foreach ($response->context as $key => $value) {
            set_query_var($key, $value);
        }

        $blockTemplate = get_block_template(get_stylesheet() . '//' . $response->slug);
        if ($blockTemplate !== null) {
            global $_wp_current_template_content;
            $_wp_current_template_content = $blockTemplate->content;
            $this->pendingBlockTemplate = ABSPATH . WPINC . '/template-canvas.php'; // @phpstan-ignore constant.notFound
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleJson(JsonResponse $response): void
    {
        $this->sendHeaders($response);
        wp_send_json($response->data, $response->statusCode);
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleRedirect(RedirectResponse $response): void
    {
        $this->sendHeaders($response);
        if ($response->safe) {
            wp_safe_redirect($response->url, $response->statusCode);
        } else {
            wp_redirect($response->url, $response->statusCode);
        }
        exit;
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleFile(BinaryFileResponse $response): void
    {
        $filename = $response->filename ?? basename($response->path);
        $mimeType = mime_content_type($response->path) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . $response->disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($response->path));
        $this->sendHeaders($response);

        readfile($response->path);
        exit;
    }

    /**
     * @codeCoverageIgnore
     */
    private function handleHtml(Response $response): void
    {
        $this->sendHeaders($response);
        echo $response->content;
        exit;
    }

    /**
     * @return list<string>
     */
    private static function parseQueryVars(string $query): array
    {
        $queryString = preg_replace('/^index\.php\?/', '', $query);
        preg_match_all('/(?:^|&)([^=&]+)=\$matches\[/', (string) $queryString, $matches);

        return $matches[1];
    }
}
