<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'container')]
final class ContainerPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'container';
    }

    public function render(array $data): string
    {
        $serviceCount = (int) ($data['service_count'] ?? 0);
        $publicCount = (int) ($data['public_count'] ?? 0);
        $privateCount = (int) ($data['private_count'] ?? 0);
        $autowiredCount = (int) ($data['autowired_count'] ?? 0);
        $lazyCount = (int) ($data['lazy_count'] ?? 0);
        /** @var array<string, array<string, mixed>> $services */
        $services = $data['services'] ?? [];
        /** @var list<string> $compilerPasses */
        $compilerPasses = $data['compiler_passes'] ?? [];
        /** @var array<string, list<string>> $taggedServices */
        $taggedServices = $data['tagged_services'] ?? [];
        /** @var array<string, mixed> $parameters */
        $parameters = $data['parameters'] ?? [];

        // Summary
        $html = '<div class="wpd-section">';
        $html .= '<h4 class="wpd-section-title">Summary</h4>';
        $html .= '<div class="wpd-perf-cards">';
        $html .= $this->renderPerfCard('Services', (string) $serviceCount, '', '');
        $html .= $this->renderPerfCard('Public', (string) $publicCount, '', '');
        $html .= $this->renderPerfCard('Private', (string) $privateCount, '', '');
        $html .= $this->renderPerfCard('Autowired', (string) $autowiredCount, '', '');
        $html .= $this->renderPerfCard('Lazy', (string) $lazyCount, '', '');
        $html .= '</div>';
        $html .= '</div>';

        // Compiler Passes
        if ($compilerPasses !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Compiler Passes (' . count($compilerPasses) . ')</h4>';
            $html .= '<div class="wpd-tag-list">';
            foreach ($compilerPasses as $pass) {
                $shortName = substr(strrchr($pass, '\\') ?: $pass, 1) ?: $pass;
                $html .= '<span class="wpd-tag">' . $this->esc($shortName) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        // Tagged Services
        if ($taggedServices !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Tagged Services</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Tag</th>';
            $html .= '<th>Services</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($taggedServices as $tag => $serviceIds) {
                $tags = '';
                foreach ($serviceIds as $id) {
                    $tags .= '<span class="wpd-tag">' . $this->esc($id) . '</span>';
                }

                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($tag) . '</code></td>';
                $html .= '<td><div class="wpd-tag-list">' . $tags . '</div></td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Services
        if ($services !== []) {
            $html .= '<div class="wpd-section">';
            $html .= '<h4 class="wpd-section-title">Services (' . $serviceCount . ')</h4>';
            $html .= '<table class="wpd-table wpd-table-full">';
            $html .= '<thead><tr>';
            $html .= '<th>Service ID</th>';
            $html .= '<th>Class</th>';
            $html .= '<th>Scope</th>';
            $html .= '<th>Flags</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            foreach ($services as $id => $info) {
                $class = (string) ($info['class'] ?? $id);
                $isPublic = (bool) ($info['public'] ?? false);
                $isAutowired = (bool) ($info['autowired'] ?? false);
                $isLazy = (bool) ($info['lazy'] ?? false);

                $scope = $isPublic
                    ? '<span class="wpd-text-green">public</span>'
                    : '<span class="wpd-text-dim">private</span>';

                $flags = '';
                if ($isAutowired) {
                    $flags .= '<span class="wpd-query-tag" style="background:rgba(56,88,233,0.08);color:#3858e9">autowired</span> ';
                }
                if ($isLazy) {
                    $flags .= '<span class="wpd-query-tag" style="background:rgba(153,104,0,0.08);color:#996800">lazy</span> ';
                }

                $html .= '<tr>';
                $html .= '<td><code>' . $this->esc($id) . '</code></td>';
                $html .= '<td class="wpd-text-dim">' . $this->esc($class) . '</td>';
                $html .= '<td>' . $scope . '</td>';
                $html .= '<td>' . ($flags ?: '-') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // Parameters
        if ($parameters !== []) {
            $html .= $this->renderKeyValueSection('Parameters', $parameters);
        }

        return $html;
    }
}
