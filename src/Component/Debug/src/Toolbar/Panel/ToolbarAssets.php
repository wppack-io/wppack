<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

final class ToolbarAssets
{
    public function renderCss(): string
    {
        return <<<'CSS'
        #wppack-debug *, #wppack-debug *::before, #wppack-debug *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        #wppack-debug {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #cdd6f4;
            direction: ltr;
            text-align: left;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 99999;
        }

        /* ---- Summary bar ---- */
        #wppack-debug .wpd-bar {
            display: flex;
            align-items: center;
            background: #1e1e2e;
            border-top: 1px solid #313244;
            height: 36px;
            width: 100%;
        }

        /* ---- Logo (fixed left, does not scroll) ---- */
        #wppack-debug .wpd-bar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #89b4fa, #cba6f7);
            flex-shrink: 0;
            cursor: default;
        }
        #wppack-debug .wpd-logo-text {
            font-size: 10px;
            font-weight: 800;
            color: #1e1e2e;
            letter-spacing: -0.5px;
        }

        /* ---- Badges container ---- */
        #wppack-debug .wpd-bar-badges {
            display: flex;
            align-items: center;
            height: 100%;
            flex: 1 1 auto;
            min-width: 0;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none;
        }
        #wppack-debug .wpd-bar-badges::-webkit-scrollbar {
            display: none;
        }

        /* ---- Badges ---- */
        #wppack-debug .wpd-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 0 10px;
            background: transparent;
            border: none;
            border-right: 1px solid #313244;
            color: #cdd6f4;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            white-space: nowrap;
            flex-shrink: 0;
            height: 100%;
            transition: background 0.15s ease;
        }
        #wppack-debug .wpd-badge:last-child {
            border-right: none;
        }
        #wppack-debug .wpd-badge:hover {
            background: #313244;
        }
        #wppack-debug .wpd-badge.wpd-active {
            background: #313244;
            box-shadow: inset 0 -2px 0 #89b4fa;
        }
        #wppack-debug .wpd-badge-icon {
            font-size: 14px;
            line-height: 1;
        }
        #wppack-debug .wpd-badge-value {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
        }

        /* ---- Bar meta ---- */
        #wppack-debug .wpd-bar-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            padding: 0 10px;
            height: 100%;
            border-left: 1px solid #313244;
        }
        #wppack-debug .wpd-meta-item {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #6c7086;
        }
        #wppack-debug .wpd-meta-sep {
            color: #45475a;
            font-size: 11px;
        }

        /* ---- Close button ---- */
        #wppack-debug .wpd-close-btn {
            background: transparent;
            border: none;
            border-left: 1px solid #313244;
            color: #6c7086;
            cursor: pointer;
            font-size: 16px;
            padding: 0 10px;
            height: 100%;
            flex-shrink: 0;
            line-height: 1;
            transition: color 0.15s ease, background 0.15s ease;
        }
        #wppack-debug .wpd-close-btn:hover {
            color: #f38ba8;
            background: #313244;
        }

        /* ---- Minimized state button ---- */
        #wppack-debug .wpd-mini {
            display: none;
            position: fixed;
            bottom: 6px;
            right: 6px;
            z-index: 99999;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #89b4fa, #cba6f7);
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        #wppack-debug .wpd-mini:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }
        #wppack-debug .wpd-mini-logo {
            font-size: 10px;
            font-weight: 800;
            color: #1e1e2e;
            letter-spacing: -0.5px;
        }

        /* ---- Minimized state ---- */
        #wppack-debug.wpd-minimized .wpd-bar {
            display: none;
        }
        #wppack-debug.wpd-minimized .wpd-panel {
            display: none !important;
        }
        #wppack-debug.wpd-minimized .wpd-mini {
            display: flex;
        }

        /* ---- Panels ---- */
        #wppack-debug .wpd-panel {
            position: absolute;
            bottom: 36px;
            left: 0;
            right: 0;
            background: #1e1e2e;
            border-top: 1px solid #313244;
            max-height: 55vh;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #45475a transparent;
        }
        #wppack-debug .wpd-panel::-webkit-scrollbar {
            width: 6px;
        }
        #wppack-debug .wpd-panel::-webkit-scrollbar-track {
            background: transparent;
        }
        #wppack-debug .wpd-panel::-webkit-scrollbar-thumb {
            background: #45475a;
            border-radius: 3px;
        }
        #wppack-debug .wpd-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            background: #181825;
            border-bottom: 1px solid #313244;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        #wppack-debug .wpd-panel-title {
            font-size: 14px;
            font-weight: 600;
            color: #cdd6f4;
        }
        #wppack-debug .wpd-panel-close {
            background: transparent;
            border: none;
            color: #6c7086;
            font-size: 20px;
            cursor: pointer;
            padding: 2px 8px;
            border-radius: 4px;
            line-height: 1;
            transition: color 0.15s ease, background 0.15s ease;
        }
        #wppack-debug .wpd-panel-close:hover {
            color: #f38ba8;
            background: #313244;
        }
        #wppack-debug .wpd-panel-body {
            padding: 12px 16px;
        }

        /* ---- Sections ---- */
        #wppack-debug .wpd-section {
            margin-bottom: 16px;
        }
        #wppack-debug .wpd-section:last-child {
            margin-bottom: 0;
        }
        #wppack-debug .wpd-section-title {
            font-size: 12px;
            font-weight: 600;
            color: #89b4fa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 6px;
            margin-bottom: 8px;
            border-bottom: 1px solid #313244;
        }

        /* ---- Tables ---- */
        #wppack-debug .wpd-table {
            width: 100%;
            border-collapse: collapse;
        }
        #wppack-debug .wpd-table th,
        #wppack-debug .wpd-table td {
            padding: 5px 10px;
            text-align: left;
            border-bottom: 1px solid #262637;
            vertical-align: top;
        }
        #wppack-debug .wpd-table thead th {
            font-size: 11px;
            font-weight: 600;
            color: #a6adc8;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #181825;
            position: sticky;
            top: 47px;
            z-index: 1;
        }
        #wppack-debug .wpd-table tbody tr:hover {
            background: #262637;
        }

        /* Key-value table */
        #wppack-debug .wpd-table-kv .wpd-kv-key {
            width: 200px;
            font-weight: 500;
            color: #a6adc8;
            white-space: nowrap;
        }
        #wppack-debug .wpd-table-kv .wpd-kv-val {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
        }

        /* Right-aligned numeric columns */
        #wppack-debug .wpd-table .wpd-col-right {
            text-align: right;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            white-space: nowrap;
        }

        /* Full-width table columns */
        #wppack-debug .wpd-col-reltime {
            width: 70px;
            white-space: nowrap;
            text-align: right;
            font-size: 12px;
        }
        #wppack-debug .wpd-col-num {
            width: 40px;
            text-align: center;
            color: #6c7086;
            font-size: 11px;
        }
        #wppack-debug .wpd-col-sql {
            max-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #wppack-debug .wpd-col-sql code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: #cdd6f4;
            white-space: pre-wrap;
            word-break: break-all;
        }
        #wppack-debug .wpd-col-time {
            width: 90px;
            white-space: nowrap;
            text-align: right;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
        }
        #wppack-debug .wpd-col-caller {
            width: 260px;
        }
        #wppack-debug .wpd-caller {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #a6adc8;
            word-break: break-all;
        }

        /* Query row highlighting */
        #wppack-debug .wpd-row-slow {
            background: rgba(243, 139, 168, 0.08);
        }
        #wppack-debug .wpd-row-slow:hover {
            background: rgba(243, 139, 168, 0.14);
        }
        #wppack-debug .wpd-row-duplicate {
            background: rgba(249, 226, 175, 0.06);
        }
        #wppack-debug .wpd-row-duplicate:hover {
            background: rgba(249, 226, 175, 0.12);
        }

        /* Query tags */
        #wppack-debug .wpd-query-tag {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1px 5px;
            border-radius: 3px;
            margin-left: 6px;
            vertical-align: middle;
        }
        #wppack-debug .wpd-tag-slow {
            background: rgba(243, 139, 168, 0.2);
            color: #f38ba8;
        }
        #wppack-debug .wpd-tag-dup {
            background: rgba(249, 226, 175, 0.2);
            color: #f9e2af;
        }

        /* ---- Timeline ---- */
        #wppack-debug .wpd-timeline {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        #wppack-debug .wpd-timeline-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        #wppack-debug .wpd-timeline-label {
            width: 150px;
            font-size: 12px;
            color: #a6adc8;
            text-align: right;
            flex-shrink: 0;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
        }
        #wppack-debug .wpd-timeline-bar-wrap {
            flex: 1;
            height: 14px;
            background: #262637;
            border-radius: 3px;
            overflow: hidden;
        }
        #wppack-debug .wpd-timeline-bar {
            height: 100%;
            background: #89b4fa;
            border-radius: 3px;
            min-width: 2px;
            transition: width 0.3s ease;
        }
        #wppack-debug .wpd-timeline-value {
            width: 160px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #6c7086;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ---- Memory bar ---- */
        #wppack-debug .wpd-memory-bar-wrap {
            height: 8px;
            background: #262637;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        #wppack-debug .wpd-memory-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* ---- Tags ---- */
        #wppack-debug .wpd-tag {
            display: inline-block;
            font-size: 11px;
            padding: 1px 7px;
            background: #313244;
            color: #a6adc8;
            border-radius: 3px;
            margin: 2px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
        }
        #wppack-debug .wpd-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
        }

        /* ---- Lists ---- */
        #wppack-debug .wpd-list {
            list-style: none;
            padding: 0;
        }
        #wppack-debug .wpd-list li {
            padding: 4px 0;
            border-bottom: 1px solid #262637;
            font-size: 12px;
        }
        #wppack-debug .wpd-list li:last-child {
            border-bottom: none;
        }
        #wppack-debug .wpd-list code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
        }

        /* ---- Suggestions ---- */
        #wppack-debug .wpd-suggestions {
            list-style: none;
            padding: 0;
        }
        #wppack-debug .wpd-suggestion-item {
            padding: 6px 10px;
            background: rgba(249, 226, 175, 0.08);
            border-left: 3px solid #f9e2af;
            border-radius: 0 4px 4px 0;
            margin-bottom: 4px;
            font-size: 12px;
            color: #f9e2af;
        }

        /* ---- Utility text colors ---- */
        #wppack-debug .wpd-text-green { color: #a6e3a1; }
        #wppack-debug .wpd-text-yellow { color: #f9e2af; }
        #wppack-debug .wpd-text-red { color: #f38ba8; }
        #wppack-debug .wpd-text-orange { color: #fab387; }
        #wppack-debug .wpd-text-dim { color: #6c7086; font-style: italic; }

        /* ---- Code blocks ---- */
        #wppack-debug code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
        }

        /* ---- Performance cards ---- */
        #wppack-debug .wpd-perf-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        #wppack-debug .wpd-perf-card {
            background: #181825;
            border: 1px solid #313244;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
        }
        #wppack-debug .wpd-perf-card-value {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 18px;
            font-weight: 700;
            color: #cdd6f4;
        }
        #wppack-debug .wpd-perf-card-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #a6adc8;
            letter-spacing: 0.3px;
            margin-top: 4px;
        }
        #wppack-debug .wpd-perf-card-sub {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #6c7086;
            margin-top: 2px;
        }

        /* ---- Time Distribution ---- */
        #wppack-debug .wpd-perf-dist-bar {
            display: flex;
            height: 20px;
            background: #262637;
            border-radius: 4px;
            overflow: hidden;
        }
        #wppack-debug .wpd-perf-dist-segment {
            min-width: 2px;
        }
        #wppack-debug .wpd-perf-dist-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 8px;
        }
        #wppack-debug .wpd-perf-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: #a6adc8;
        }
        #wppack-debug .wpd-perf-legend-color {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            flex-shrink: 0;
        }

        /* ---- Waterfall ---- */
        #wppack-debug .wpd-perf-waterfall {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        #wppack-debug .wpd-perf-wf-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #wppack-debug .wpd-perf-wf-label {
            width: 180px;
            flex-shrink: 0;
            text-align: right;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: #a6adc8;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #wppack-debug .wpd-perf-wf-track {
            flex: 1;
            height: 16px;
            position: relative;
            background: #262637;
            border-radius: 3px;
        }
        #wppack-debug .wpd-perf-wf-bar {
            position: absolute;
            top: 0;
            height: 100%;
            background: #89b4fa;
            border-radius: 3px;
            min-width: 2px;
        }
        #wppack-debug .wpd-perf-wf-bar[data-tooltip] {
            cursor: pointer;
        }
        #wppack-debug .wpd-tooltip {
            position: fixed;
            z-index: 100001;
            background: #1e1e2e;
            color: #cdd6f4;
            font-size: 11px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            line-height: 1.5;
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #45475a;
            white-space: pre;
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #wppack-debug .wpd-perf-wf-value {
            width: 80px;
            flex-shrink: 0;
            text-align: right;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 11px;
            color: #6c7086;
            white-space: nowrap;
        }

        /* ---- Timeline dividers ---- */
        #wppack-debug .wpd-perf-wf-divider {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 6px 0 3px;
            color: #6c7086;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        #wppack-debug .wpd-perf-wf-divider::before,
        #wppack-debug .wpd-perf-wf-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #313244;
        }

        /* ---- Log filter tabs ---- */
        #wppack-debug .wpd-log-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 8px;
            border-bottom: 1px solid #313244;
        }
        #wppack-debug .wpd-log-tab {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: #6c7086;
            padding: 6px 14px;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
        }
        #wppack-debug .wpd-log-tab:hover {
            color: #cdd6f4;
        }
        #wppack-debug .wpd-log-tab.wpd-active {
            color: #89b4fa;
            border-bottom-color: #89b4fa;
        }
        #wppack-debug .wpd-log-context pre {
            background: #181825;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 11px;
            color: #a6adc8;
            white-space: pre-wrap;
            word-break: break-all;
        }
        #wppack-debug .wpd-log-toggle {
            cursor: pointer;
        }
        #wppack-debug .wpd-log-toggle:hover {
            background: #313244;
        }

        /* ---- Plugin detail navigation ---- */
        #wppack-debug .wpd-plugin-detail-link {
            color: #89b4fa;
            cursor: pointer;
            font-weight: 600;
        }
        #wppack-debug .wpd-plugin-detail-link:hover {
            text-decoration: underline;
        }
        #wppack-debug .wpd-plugin-back {
            background: transparent;
            border: 1px solid #45475a;
            border-radius: 4px;
            color: #89b4fa;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            padding: 4px 12px;
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        #wppack-debug .wpd-plugin-back:hover {
            background: #313244;
            border-color: #89b4fa;
        }
        CSS;
    }

    public function renderJs(): string
    {
        return <<<'JS'
        (function() {
            var root = document.getElementById('wppack-debug');
            if (!root) return;

            var activePanel = null;

            function closeAllPanels() {
                var panels = root.querySelectorAll('.wpd-panel');
                for (var i = 0; i < panels.length; i++) {
                    panels[i].style.display = 'none';
                }
                var badges = root.querySelectorAll('.wpd-badge');
                for (var i = 0; i < badges.length; i++) {
                    badges[i].classList.remove('wpd-active');
                }
                activePanel = null;
                resetPluginDetailView();
            }

            function resetPluginDetailView() {
                var lists = root.querySelectorAll('.wpd-plugin-list');
                for (var i = 0; i < lists.length; i++) {
                    lists[i].style.display = '';
                }
                var details = root.querySelectorAll('.wpd-plugin-detail');
                for (var i = 0; i < details.length; i++) {
                    details[i].style.display = 'none';
                }
            }

            function openPanel(name) {
                closeAllPanels();
                var panel = root.querySelector('#wpd-panel-' + name);
                if (panel) {
                    panel.style.display = 'block';
                    activePanel = name;
                    var badge = root.querySelector('.wpd-badge[data-panel="' + name + '"]');
                    if (badge) badge.classList.add('wpd-active');
                }
            }

            root.addEventListener('click', function(e) {
                // Mini button — restore toolbar
                var miniBtn = e.target.closest('.wpd-mini');
                if (miniBtn) {
                    root.classList.remove('wpd-minimized');
                    return;
                }

                // Badge click — toggle panel
                var badge = e.target.closest('.wpd-badge');
                if (badge) {
                    var panel = badge.getAttribute('data-panel');
                    if (activePanel === panel) {
                        closeAllPanels();
                    } else {
                        openPanel(panel);
                    }
                    return;
                }

                // Close button in panel header
                var closeBtn = e.target.closest('[data-action="close-panel"]');
                if (closeBtn) {
                    closeAllPanels();
                    return;
                }

                // Plugin detail link
                var pluginLink = e.target.closest('.wpd-plugin-detail-link');
                if (pluginLink) {
                    var pluginSlug = pluginLink.getAttribute('data-plugin');
                    var panel = pluginLink.closest('.wpd-panel-body');
                    if (panel) {
                        var list = panel.querySelector('.wpd-plugin-list');
                        if (list) list.style.display = 'none';
                        var detail = panel.querySelector('.wpd-plugin-detail[data-plugin="' + pluginSlug + '"]');
                        if (detail) detail.style.display = '';
                    }
                    return;
                }

                // Plugin back button
                var backBtn = e.target.closest('[data-action="plugin-back"]');
                if (backBtn) {
                    var panel = backBtn.closest('.wpd-panel-body');
                    if (panel) {
                        var details = panel.querySelectorAll('.wpd-plugin-detail');
                        for (var i = 0; i < details.length; i++) {
                            details[i].style.display = 'none';
                        }
                        var list = panel.querySelector('.wpd-plugin-list');
                        if (list) list.style.display = '';
                    }
                    return;
                }

                // Close/minimize toolbar
                var minimizeBtn = e.target.closest('[data-action="minimize"]');
                if (minimizeBtn) {
                    closeAllPanels();
                    root.classList.add('wpd-minimized');
                }
            });

            // Log filter tabs
            root.addEventListener('click', function(e) {
                var tab = e.target.closest('.wpd-log-tab');
                if (tab) {
                    var tabs = tab.closest('.wpd-log-tabs');
                    tabs.querySelectorAll('.wpd-log-tab').forEach(function(t) { t.classList.remove('wpd-active'); });
                    tab.classList.add('wpd-active');
                    var filter = tab.getAttribute('data-log-filter');
                    var section = tabs.closest('.wpd-section');
                    section.querySelectorAll('tr[data-log-level]').forEach(function(row) {
                        var level = row.getAttribute('data-log-level');
                        var show = false;
                        if (filter === 'all') { show = true; }
                        else if (filter === 'error') { show = (['emergency','alert','critical','error'].indexOf(level) !== -1); }
                        else if (filter === 'deprecation') { show = level === 'deprecation'; }
                        else if (filter === 'warning') { show = (['warning','notice'].indexOf(level) !== -1); }
                        else if (filter === 'info') { show = level === 'info'; }
                        else if (filter === 'debug') { show = level === 'debug'; }
                        row.style.display = show ? '' : 'none';
                    });
                    return;
                }
                // Context toggle
                var toggle = e.target.closest('.wpd-log-toggle');
                if (toggle) {
                    var ctx = toggle.nextElementSibling;
                    if (ctx && ctx.classList.contains('wpd-log-context')) {
                        ctx.style.display = ctx.style.display === 'none' ? '' : 'none';
                    }
                }
            });

            // Escape key closes panel
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && activePanel !== null) {
                    closeAllPanels();
                }
            });

            // Timeline bar tooltips
            var tooltip = document.createElement('div');
            tooltip.className = 'wpd-tooltip';
            tooltip.style.display = 'none';
            root.appendChild(tooltip);

            root.addEventListener('mouseover', function(e) {
                var bar = e.target.closest('.wpd-perf-wf-bar[data-tooltip]');
                if (!bar) return;
                tooltip.textContent = bar.getAttribute('data-tooltip');
                tooltip.style.display = '';
                var rect = bar.getBoundingClientRect();
                var tipRect = tooltip.getBoundingClientRect();
                var left = rect.left + rect.width / 2 - tipRect.width / 2;
                if (left < 4) left = 4;
                if (left + tipRect.width > window.innerWidth - 4) left = window.innerWidth - 4 - tipRect.width;
                tooltip.style.left = left + 'px';
                tooltip.style.top = (rect.top - tipRect.height - 6) + 'px';
            });

            root.addEventListener('mouseout', function(e) {
                var bar = e.target.closest('.wpd-perf-wf-bar[data-tooltip]');
                if (bar) tooltip.style.display = 'none';
            });
        })();
        JS;
    }
}
