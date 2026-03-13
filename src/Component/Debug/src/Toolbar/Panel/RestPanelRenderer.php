<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'rest')]
final class RestPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'rest';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        $isRestRequest = (bool) ($data['is_rest_request'] ?? false);
        /** @var array<string, mixed>|null $currentRequest */
        $currentRequest = $data['current_request'] ?? null;
        $totalRoutes = (int) ($data['total_routes'] ?? 0);
        $totalNamespaces = (int) ($data['total_namespaces'] ?? 0);
        /** @var array<string, list<array{route: string, methods: list<string>, callback: string}>> $routes */
        $routes = $data['routes'] ?? [];

        $html = '';

        // Current Request section (shown first when this is a REST API request)
        if ($isRestRequest && is_array($currentRequest)) {
            $html .= $this->renderCurrentRequest($currentRequest);
        }

        // Summary
        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('REST Request', $this->formatValue($isRestRequest));
        $html .= $this->renderTableRow('Total Routes', (string) $totalRoutes);
        $html .= $this->renderTableRow('Namespaces', (string) $totalNamespaces);
        $html .= '</table>';
        $html .= '</div>';

        // Routes per namespace
        foreach ($routes as $namespace => $nsRoutes) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">' . $this->esc($namespace) . ' (' . count($nsRoutes) . ')</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Route</th>';
            $html .= '<th>Methods</th>';
            $html .= '<th>Callback</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($nsRoutes as $route) {
                $methodTags = '';
                foreach ($route['methods'] as $method) {
                    $color = match ($method) {
                        'GET' => 'background:var(--wpd-green-a8);color:var(--wpd-green)',
                        'POST' => 'background:var(--wpd-primary-a8);color:var(--wpd-primary)',
                        'PUT', 'PATCH' => 'background:var(--wpd-yellow-a8);color:var(--wpd-yellow)',
                        'DELETE' => 'background:var(--wpd-red-a8);color:var(--wpd-red)',
                        default => 'background:var(--wpd-gray-800-a8);color:var(--wpd-gray-800)',
                    };
                    $methodTags .= '<span class="wpd-query-tag" style="' . $color . '">' . $this->esc($method) . '</span> ';
                }

                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($route['route']) . '</code></td>';
                $html .= '<td>' . $methodTags . '</td>';
                $html .= '<td class="wpd-text-dim">' . $this->esc($route['callback']) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $request
     */
    private function renderCurrentRequest(array $request): string
    {
        $method = (string) ($request['method'] ?? '');
        $route = (string) ($request['route'] ?? '');
        $path = (string) ($request['path'] ?? '');
        $namespace = (string) ($request['namespace'] ?? '');
        $callback = (string) ($request['callback'] ?? '');
        $status = (int) ($request['status'] ?? 200);
        $authentication = (string) ($request['authentication'] ?? 'none');
        /** @var array<string, mixed> $params */
        $params = $request['params'] ?? [];

        $methodColor = match ($method) {
            'GET' => 'background:var(--wpd-green-a8);color:var(--wpd-green)',
            'POST' => 'background:var(--wpd-primary-a8);color:var(--wpd-primary)',
            'PUT', 'PATCH' => 'background:var(--wpd-yellow-a8);color:var(--wpd-yellow)',
            'DELETE' => 'background:var(--wpd-red-a8);color:var(--wpd-red)',
            default => 'background:var(--wpd-gray-800-a8);color:var(--wpd-gray-800)',
        };
        $methodTag = '<span class="wpd-query-tag" style="' . $methodColor . '">' . $this->esc($method) . '</span>';

        $statusColorClass = match (true) {
            $status >= 200 && $status < 300 => 'wpd-text-green',
            $status >= 300 && $status < 400 => 'wpd-text-yellow',
            default => 'wpd-text-red',
        };

        $authTag = match ($authentication) {
            'bearer' => '<span class="wpd-query-tag" style="background:rgba(130,50,150,0.12);color:#7b2d8e">Bearer</span>',
            'basic' => '<span class="wpd-query-tag" style="background:var(--wpd-yellow-a8);color:var(--wpd-yellow)">Basic</span>',
            'nonce' => '<span class="wpd-query-tag" style="background:var(--wpd-primary-a8);color:var(--wpd-primary)">Nonce</span>',
            'cookie' => '<span class="wpd-query-tag" style="background:var(--wpd-green-a8);color:var(--wpd-green)">Cookie</span>',
            default => '<span class="wpd-text-dim">' . $this->esc($authentication) . '</span>',
        };

        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Current Request</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Method', $methodTag);
        $html .= $this->renderTableRow('Route', $route !== '' ? '<code>' . $this->esc($route) . '</code>' : '-');
        if ($path !== '' && $path !== $route) {
            $html .= $this->renderTableRow('Path', '<code>' . $this->esc($path) . '</code>');
        }
        $html .= $this->renderTableRow('Namespace', $namespace !== '' ? $this->esc($namespace) : '-');
        $html .= $this->renderTableRow('Callback', $callback !== '' ? '<code>' . $this->esc($callback) . '</code>' : '-');
        $html .= $this->renderTableRow('Status', (string) $status, $statusColorClass);
        $html .= $this->renderTableRow('Authentication', $authTag);
        $html .= '</table>';
        $html .= '</div>';

        // Request Parameters
        if ($params !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Request Parameters</h4>';
            $html .= '<table class="wpd-table wpd-table-kv">';
            foreach ($params as $key => $value) {
                $html .= $this->renderTableRow($key, $this->formatValue($value));
            }
            $html .= '</table>';
            $html .= '</div>';
        }

        return $html;
    }
}
