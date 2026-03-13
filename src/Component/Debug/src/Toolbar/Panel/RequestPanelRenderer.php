<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;

#[AsPanelRenderer(name: 'request')]
final class RequestPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'request';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->getName());
        /** @var array<string, mixed> $serverVars */
        $serverVars = $data['server_vars'] ?? [];

        // Request section — enriched with Script, Remote Address, Time
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Request</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $method = (string) ($data['method'] ?? '');
        $methodColor = match ($method) {
            'GET' => 'background:rgba(0,138,32,0.08);color:#008a20',
            'POST' => 'background:rgba(56,88,233,0.08);color:#3858e9',
            'PUT', 'PATCH' => 'background:rgba(153,104,0,0.08);color:#996800',
            'DELETE' => 'background:rgba(204,24,24,0.08);color:#cc1818',
            default => 'background:rgba(80,87,94,0.08);color:#50575e',
        };
        $methodTag = '<span class="wpd-query-tag" style="' . $methodColor . '">' . $this->esc($method) . '</span>';
        $html .= $this->renderTableRow('Method', $methodTag);
        $html .= $this->renderTableRow('URL', (string) ($data['url'] ?? ''));

        $script = (string) ($serverVars['SCRIPT_FILENAME'] ?? '');
        if ($script !== '') {
            $html .= $this->renderTableRow('Script', $script);
        }

        $remoteAddr = (string) ($serverVars['REMOTE_ADDR'] ?? '');
        if ($remoteAddr !== '') {
            $html .= $this->renderTableRow('Remote Address', $remoteAddr);
        }

        $requestTimeFloat = $serverVars['REQUEST_TIME_FLOAT'] ?? null;
        if ($requestTimeFloat !== null) {
            $html .= $this->renderTableRow('Time', date('Y-m-d H:i:s', (int) $requestTimeFloat));
        }

        $html .= '</table>';
        $html .= '</div>';

        // Response section — Status Code (colored) and Content-Type
        $statusCode = (int) ($data['status_code'] ?? 200);
        $contentType = (string) ($data['content_type'] ?? '');

        $statusColorClass = match (true) {
            $statusCode >= 200 && $statusCode < 300 => 'wpd-text-green',
            $statusCode >= 300 && $statusCode < 400 => 'wpd-text-yellow',
            default => 'wpd-text-red',
        };

        $html .= '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Response</h4>';
        $html .= '<table class="wpd-table wpd-table-kv">';
        $html .= $this->renderTableRow('Status Code', (string) $statusCode, $statusColorClass);
        if ($contentType !== '') {
            $html .= $this->renderTableRow('Content-Type', $contentType);
        }
        $html .= '</table>';
        $html .= '</div>';

        /** @var array<string, string> $requestHeaders */
        $requestHeaders = $data['request_headers'] ?? [];
        if ($requestHeaders !== []) {
            $html .= $this->renderKeyValueSection('Request Headers', $requestHeaders);
        }

        /** @var array<string, string> $responseHeaders */
        $responseHeaders = $data['response_headers'] ?? [];
        if ($responseHeaders !== []) {
            $html .= $this->renderKeyValueSection('Response Headers', $responseHeaders);
        }

        /** @var array<string, mixed> $getParams */
        $getParams = $data['get_params'] ?? [];
        if ($getParams !== []) {
            $html .= $this->renderKeyValueSection('GET Parameters', $getParams);
        }

        /** @var array<string, mixed> $postParams */
        $postParams = $data['post_params'] ?? [];
        if ($postParams !== []) {
            $html .= $this->renderKeyValueSection('POST Parameters', $postParams);
        }

        /** @var array<string, mixed> $cookies */
        $cookies = $data['cookies'] ?? [];
        if ($cookies !== []) {
            $html .= $this->renderKeyValueSection('Cookies', $cookies);
        }

        // Server Variables — exclude keys already shown in Request section
        $excludeFromServerVars = ['SCRIPT_FILENAME', 'REMOTE_ADDR', 'REQUEST_TIME_FLOAT'];
        $filteredServerVars = array_diff_key($serverVars, array_flip($excludeFromServerVars));
        if ($filteredServerVars !== []) {
            $html .= $this->renderKeyValueSection('Server Variables', $filteredServerVars);
        }

        /** @var list<array{url: string, args: array<string, mixed>, response: mixed}> $httpApiCalls */
        $httpApiCalls = $data['http_api_calls'] ?? [];
        if ($httpApiCalls !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">HTTP API Calls (' . $this->esc((string) count($httpApiCalls)) . ')</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr><th>#</th><th>URL</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($httpApiCalls as $index => $call) {
                $html .= '<tr>';
                $html .= '<td>' . $this->esc((string) ($index + 1)) . '</td>';
                $html .= '<td><code>' . $this->esc($call['url']) . '</code></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        return $html;
    }
}
