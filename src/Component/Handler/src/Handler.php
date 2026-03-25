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

namespace WpPack\Component\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Handler\Environment\Environment;
use WpPack\Component\Handler\Exception\FileNotFoundException;
use WpPack\Component\Handler\Exception\SecurityException;
use WpPack\Component\Handler\Processor\DirectoryProcessor;
use WpPack\Component\Handler\Processor\MultisiteProcessor;
use WpPack\Component\Handler\Processor\PhpFileProcessor;
use WpPack\Component\Handler\Processor\ProcessorInterface;
use WpPack\Component\Handler\Processor\SecurityProcessor;
use WpPack\Component\Handler\Processor\StaticFileProcessor;
use WpPack\Component\Handler\Processor\TrailingSlashProcessor;
use WpPack\Component\Handler\Processor\WordPressProcessor;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Kernel\Kernel;

class Handler implements HandlerInterface
{
    private readonly Configuration $config;
    private readonly Environment $environment;

    /** @var list<ProcessorInterface> */
    private array $processors = [];

    public function __construct(
        ?Configuration $config = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->config = $config ?? new Configuration();
        $this->environment = new Environment($this->config);
        $this->initializeProcessors();
    }

    public function handle(Request $request): void
    {
        $this->environment->setup();

        $request = $this->prepareRequest($request);

        try {
            $request = $this->processRequest($request);
            if ($request === null) {
                return;
            }

            $filePath = $this->preparePhpEnvironment($request);
            if ($filePath === null) {
                $this->sendNotFoundResponse();

                return;
            }

            if (class_exists(Kernel::class)) {
                Kernel::create($request);
            }

            require $filePath;
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Adds a custom processor to the chain.
     *
     * @param int $priority Position in the chain (lower = earlier)
     */
    public function addProcessor(ProcessorInterface $processor, int $priority = 100): void
    {
        array_splice($this->processors, min($priority, \count($this->processors)), 0, [$processor]);
    }

    private function prepareRequest(Request $request): Request
    {
        $request->server->remove('SCRIPT_FILENAME');
        $request->server->remove('SCRIPT_NAME');

        $requestUri = $request->server->getString('REQUEST_URI', '');
        $phpSelf = parse_url($requestUri, \PHP_URL_PATH) ?: '/';
        $request->server->set('PHP_SELF', $phpSelf);

        return $request;
    }

    /**
     * @return Request|null The final request, or null if a response was sent
     */
    private function processRequest(Request $request): ?Request
    {
        foreach ($this->processors as $processor) {
            $result = $processor->process($request, $this->config);

            if ($result instanceof Response) {
                $result->send();

                return null;
            }

            if ($result instanceof Request) {
                $request = $result;
            }
        }

        return $request;
    }

    private function sendNotFoundResponse(): void
    {
        (new Response('Not Found', 404))->send();
    }

    private function handleException(\Exception $e): void
    {
        if ($e instanceof SecurityException) {
            (new Response($e->getMessage(), 403))->send();

            return;
        }

        if ($e instanceof FileNotFoundException) {
            (new Response($e->getMessage(), 404))->send();

            return;
        }

        $this->logger?->error('Handler error: {message} in {file}:{line}', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'exception' => $e,
        ]);

        (new Response('Internal Server Error', 500))->send();
    }

    private function initializeProcessors(): void
    {
        $this->processors = [
            new SecurityProcessor(),
            new MultisiteProcessor(),
            new TrailingSlashProcessor(),
            new DirectoryProcessor(),
            new StaticFileProcessor(),
            new PhpFileProcessor(),
            new WordPressProcessor(),
        ];
    }

    private function preparePhpEnvironment(Request $request): ?string
    {
        $filePath = $request->server->get('SCRIPT_FILENAME');
        if (!$filePath || !is_file($filePath)) {
            return null;
        }

        $_SERVER['PATH_INFO'] = null;
        $_SERVER['PHP_SELF'] = $request->server->get('PHP_SELF');
        $_SERVER['SCRIPT_NAME'] = $request->server->get('SCRIPT_NAME');
        $_SERVER['SCRIPT_FILENAME'] = $request->server->get('SCRIPT_FILENAME');
        $_SERVER['REQUEST_URI'] = $request->server->get('REQUEST_URI');

        $workingDirectory = \dirname($filePath);
        chdir($workingDirectory);

        return $filePath;
    }
}
