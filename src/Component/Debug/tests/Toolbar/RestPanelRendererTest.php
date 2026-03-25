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

namespace WpPack\Component\Debug\Tests\Toolbar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\RestPanelRenderer;

final class RestPanelRendererTest extends TestCase
{
    private RestPanelRenderer $renderer;
    private Profile $profile;

    protected function setUp(): void
    {
        $this->profile = new Profile();
        $this->renderer = new RestPanelRenderer($this->profile);
    }

    #[Test]
    public function getNameReturnsRest(): void
    {
        self::assertSame('rest', $this->renderer->getName());
    }

    #[Test]
    public function renderWithRoutesGroupedByNamespace(): void
    {
        $this->setRestData([
            'is_rest_request' => false,
            'current_request' => null,
            'total_routes' => 5,
            'total_namespaces' => 2,
            'routes' => [
                'wp/v2' => [
                    ['route' => '/wp/v2/posts', 'methods' => ['GET', 'POST'], 'callback' => 'WP_REST_Posts_Controller'],
                    ['route' => '/wp/v2/pages', 'methods' => ['GET'], 'callback' => 'WP_REST_Posts_Controller'],
                ],
                'my-plugin/v1' => [
                    ['route' => '/my-plugin/v1/data', 'methods' => ['GET'], 'callback' => 'MyPlugin\\RestController::getData'],
                ],
            ],
        ]);
        $html = $this->renderer->renderPanel();

        // Namespace section headers with route counts
        self::assertStringContainsString('wp/v2 (2)', $html);
        self::assertStringContainsString('my-plugin/v1 (1)', $html);
        // Routes
        self::assertStringContainsString('/wp/v2/posts', $html);
        self::assertStringContainsString('/wp/v2/pages', $html);
        self::assertStringContainsString('/my-plugin/v1/data', $html);
        // Callbacks
        self::assertStringContainsString('WP_REST_Posts_Controller', $html);
        self::assertStringContainsString('MyPlugin\RestController::getData', $html);
        // Summary
        self::assertStringContainsString('Total Routes', $html);
        self::assertStringContainsString('5', $html);
        self::assertStringContainsString('Namespaces', $html);
        self::assertStringContainsString('2', $html);
    }

    #[Test]
    public function renderWithCurrentRequest(): void
    {
        $this->setRestData([
            'is_rest_request' => true,
            'current_request' => [
                'method' => 'POST',
                'route' => '/wp/v2/posts',
                'path' => '/wp-json/wp/v2/posts',
                'namespace' => 'wp/v2',
                'callback' => 'WP_REST_Posts_Controller::create_item',
                'status' => 201,
                'authentication' => 'nonce',
                'params' => ['title' => 'New Post', 'status' => 'draft'],
            ],
            'total_routes' => 10,
            'total_namespaces' => 2,
            'routes' => [],
        ]);
        $html = $this->renderer->renderPanel();

        // Current Request section appears
        self::assertStringContainsString('Current Request', $html);
        // Method
        self::assertStringContainsString('POST', $html);
        // Route
        self::assertStringContainsString('/wp/v2/posts', $html);
        // Path differs from route, so it is shown
        self::assertStringContainsString('/wp-json/wp/v2/posts', $html);
        // Namespace
        self::assertStringContainsString('wp/v2', $html);
        // Callback
        self::assertStringContainsString('WP_REST_Posts_Controller::create_item', $html);
        // Status code 201 (green)
        self::assertStringContainsString('201', $html);
        self::assertStringContainsString('wpd-text-green', $html);
        // Nonce authentication tag
        self::assertStringContainsString('Nonce', $html);
        // Request Parameters
        self::assertStringContainsString('Request Parameters', $html);
        self::assertStringContainsString('title', $html);
        self::assertStringContainsString('New Post', $html);
    }

    #[Test]
    public function renderMethodColorCoding(): void
    {
        $this->setRestData([
            'is_rest_request' => false,
            'current_request' => null,
            'total_routes' => 4,
            'total_namespaces' => 1,
            'routes' => [
                'test/v1' => [
                    ['route' => '/test/v1/get-resource', 'methods' => ['GET'], 'callback' => 'TestController::get'],
                    ['route' => '/test/v1/create-resource', 'methods' => ['POST'], 'callback' => 'TestController::create'],
                    ['route' => '/test/v1/update-resource', 'methods' => ['PUT'], 'callback' => 'TestController::update'],
                    ['route' => '/test/v1/delete-resource', 'methods' => ['DELETE'], 'callback' => 'TestController::delete'],
                ],
            ],
        ]);
        $html = $this->renderer->renderPanel();

        // GET - green
        self::assertStringContainsString('wpd-badge-green', $html);
        // POST - primary
        self::assertStringContainsString('wpd-badge-primary', $html);
        // PUT - yellow
        self::assertStringContainsString('wpd-badge-yellow', $html);
        // DELETE - red
        self::assertStringContainsString('wpd-badge-red', $html);
    }

    #[Test]
    public function renderCurrentRequestWithDifferentAuthenticationTypes(): void
    {
        // Bearer authentication
        $this->setRestData([
            'is_rest_request' => true,
            'current_request' => [
                'method' => 'GET',
                'route' => '/wp/v2/users/me',
                'path' => '/wp-json/wp/v2/users/me',
                'namespace' => 'wp/v2',
                'callback' => 'WP_REST_Users_Controller::get_current_item',
                'status' => 200,
                'authentication' => 'bearer',
                'params' => [],
            ],
            'total_routes' => 1,
            'total_namespaces' => 1,
            'routes' => [],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('Bearer', $html);
        self::assertStringContainsString('wpd-badge-purple', $html);
    }

    #[Test]
    public function renderCurrentRequestWith4xxStatus(): void
    {
        $this->setRestData([
            'is_rest_request' => true,
            'current_request' => [
                'method' => 'GET',
                'route' => '/wp/v2/posts/999',
                'path' => '',
                'namespace' => 'wp/v2',
                'callback' => '',
                'status' => 404,
                'authentication' => 'none',
                'params' => [],
            ],
            'total_routes' => 1,
            'total_namespaces' => 1,
            'routes' => [],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('404', $html);
        self::assertStringContainsString('wpd-text-red', $html);
    }

    #[Test]
    public function renderCurrentRequestWith3xxStatus(): void
    {
        $this->setRestData([
            'is_rest_request' => true,
            'current_request' => [
                'method' => 'GET',
                'route' => '/old/endpoint',
                'path' => '',
                'namespace' => 'old',
                'callback' => '',
                'status' => 301,
                'authentication' => 'none',
                'params' => [],
            ],
            'total_routes' => 1,
            'total_namespaces' => 1,
            'routes' => [],
        ]);
        $html = $this->renderer->renderPanel();

        self::assertStringContainsString('301', $html);
        self::assertStringContainsString('wpd-text-yellow', $html);
    }

    #[Test]
    public function renderRouteWithUnknownMethodShowsDefaultColor(): void
    {
        $this->setRestData([
            'is_rest_request' => false,
            'current_request' => null,
            'total_routes' => 1,
            'total_namespaces' => 1,
            'routes' => [
                'test/v1' => [
                    ['route' => '/test/v1/options', 'methods' => ['OPTIONS'], 'callback' => 'callback'],
                ],
            ],
        ]);
        $html = $this->renderer->renderPanel();

        // OPTIONS method uses default (gray) color
        self::assertStringContainsString('wpd-badge-gray', $html);
    }

    #[Test]
    public function renderCurrentRequestWithDeleteMethod(): void
    {
        $this->setRestData([
            'is_rest_request' => true,
            'current_request' => [
                'method' => 'DELETE',
                'route' => '/wp/v2/posts/1',
                'path' => '/wp-json/wp/v2/posts/1',
                'namespace' => 'wp/v2',
                'callback' => 'WP_REST_Posts_Controller::delete_item',
                'status' => 200,
                'authentication' => 'cookie',
                'params' => [],
            ],
            'total_routes' => 1,
            'total_namespaces' => 1,
            'routes' => [],
        ]);
        $html = $this->renderer->renderPanel();

        // DELETE method in current request section
        self::assertStringContainsString('DELETE', $html);
        self::assertStringContainsString('wpd-badge-red', $html);
        // Cookie auth tag
        self::assertStringContainsString('Cookie', $html);
    }

    #[Test]
    public function renderCurrentRequestWithPutMethodAndBasicAuth(): void
    {
        $this->setRestData([
            'is_rest_request' => true,
            'current_request' => [
                'method' => 'PUT',
                'route' => '/wp/v2/posts/1',
                'path' => '/wp-json/wp/v2/posts/1',
                'namespace' => 'wp/v2',
                'callback' => 'WP_REST_Posts_Controller::update_item',
                'status' => 200,
                'authentication' => 'basic',
                'params' => [],
            ],
            'total_routes' => 1,
            'total_namespaces' => 1,
            'routes' => [],
        ]);
        $html = $this->renderer->renderPanel();

        // PUT method in current request
        self::assertStringContainsString('PUT', $html);
        self::assertStringContainsString('wpd-badge-yellow', $html);
        // Basic auth tag
        self::assertStringContainsString('Basic', $html);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setRestData(array $data): void
    {
        $collector = new class ($data) implements DataCollectorInterface {
            public function __construct(private readonly array $data) {}
            public function getName(): string
            {
                return 'rest';
            }
            public function collect(): void {}
            public function getData(): array
            {
                return $this->data;
            }
            public function getLabel(): string
            {
                return 'REST API';
            }
            public function getIndicatorValue(): string
            {
                return '';
            }
            public function getIndicatorColor(): string
            {
                return 'default';
            }
            public function reset(): void {}
        };
        $this->profile->addCollector($collector);
    }
}
