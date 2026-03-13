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
            color: #1f2937;
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
            background: #ffffff;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.08);
            height: 40px;
            width: 100%;
            position: relative;
            z-index: 2;
        }

        /* ---- Logo + version (clickable, opens WordPress panel) ---- */
        #wppack-debug .wpd-bar-wp {
            display: flex;
            align-items: center;
            height: 100%;
            border: none;
            padding: 0;
            background: #3858e9;
            color: #ffffff;
            cursor: pointer;
            flex-shrink: 0;
            font-family: inherit;
            transition: background 0.15s ease;
        }
        #wppack-debug .wpd-bar-wp:hover {
            background: #2d4ad6;
        }
        #wppack-debug .wpd-bar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 100%;
            flex-shrink: 0;
        }
        #wppack-debug .wpd-bar-version {
            display: flex;
            align-items: center;
            padding-right: 10px;
            height: 100%;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            flex-shrink: 0;
            white-space: nowrap;
        }

        /* ---- Badges container ---- */
        #wppack-debug .wpd-bar-badges-wrap {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
            height: 100%;
        }
        #wppack-debug .wpd-bar-badges-wrap::before,
        #wppack-debug .wpd-bar-badges-wrap::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 48px;
            pointer-events: none;
            z-index: 1;
            opacity: 0;
            transition: opacity 0.15s;
        }
        #wppack-debug .wpd-bar-badges-wrap::before {
            left: 0;
            background: linear-gradient(to right, #ffffff 10%, transparent);
        }
        #wppack-debug .wpd-bar-badges-wrap::after {
            right: 0;
            background: linear-gradient(to left, #ffffff 10%, transparent);
        }
        #wppack-debug .wpd-bar-badges-wrap.wpd-fade-left::before {
            opacity: 1;
        }
        #wppack-debug .wpd-bar-badges-wrap.wpd-fade-right::after {
            opacity: 1;
        }
        #wppack-debug .wpd-bar-badges {
            display: flex;
            align-items: center;
            height: 100%;
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
            padding: 0 12px;
            background: transparent;
            border: none;
            border-right: 1px solid #e5e7eb;
            color: #1f2937;
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
            background: #f3f4f6;
        }
        #wppack-debug .wpd-badge.wpd-active {
            background: transparent;
            box-shadow: inset 0 -2px 0 var(--wpd-accent, #3858e9);
        }
        #wppack-debug .wpd-badge-icon {
            display: flex;
            align-items: center;
            line-height: 1;
            color: #50575e;
        }
        #wppack-debug .wpd-icon {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
        #wppack-debug .wpd-badge-value {
            font-size: 12px;
            font-weight: 400;
            color: #50575e;
        }

        /* ---- Environment info ---- */
        #wppack-debug .wpd-bar-env {
            position: relative;
            display: flex;
            align-items: center;
            padding: 0 12px;
            height: 100%;
            border-left: 1px solid #e5e7eb;
            flex-shrink: 0;
            cursor: pointer;
        }
        #wppack-debug .wpd-env-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #6b7280;
            white-space: nowrap;
        }
        #wppack-debug .wpd-env-sep {
            display: inline-block;
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: #d1d5db;
            flex-shrink: 0;
        }
        #wppack-debug .wpd-env-tooltip {
            display: none;
            position: absolute;
            bottom: 100%;
            right: 0;
            margin-bottom: 4px;
            background: #1f2937;
            color: #e5e7eb;
            font-size: 11px;
            font-family: inherit;
            line-height: 1.7;
            padding: 8px 12px;
            border-radius: 6px;
            white-space: nowrap;
            z-index: 100001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        #wppack-debug .wpd-bar-env:hover .wpd-env-tooltip {
            display: block;
        }

        /* ---- Close button ---- */
        #wppack-debug .wpd-close-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            border-left: 1px solid #e5e7eb;
            color: #9ca3af;
            cursor: pointer;
            padding: 0 12px;
            height: 100%;
            flex-shrink: 0;
            line-height: 1;
            transition: color 0.15s ease, background 0.15s ease;
        }
        #wppack-debug .wpd-close-btn:hover {
            color: #cc1818;
            background: transparent;
        }

        /* ---- Minimized state button ---- */
        #wppack-debug .wpd-mini {
            display: none;
            position: fixed;
            bottom: 6px;
            right: 6px;
            z-index: 99999;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            background: #3858e9;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        #wppack-debug .wpd-mini:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }
        #wppack-debug .wpd-mini {
            color: #ffffff;
        }

        /* ---- Minimized state ---- */
        #wppack-debug.wpd-panel-open .wpd-bar {
            box-shadow: none;
        }
        #wppack-debug.wpd-minimized .wpd-bar {
            display: none;
        }
        #wppack-debug.wpd-minimized .wpd-overlay {
            display: none !important;
        }
        #wppack-debug.wpd-minimized .wpd-mini {
            display: flex;
        }

        /* ================================================================
           L-Shape Overlay
           ================================================================ */
        #wppack-debug .wpd-overlay {
            position: absolute;
            bottom: 40px;
            left: 0;
            right: 0;
            height: min(75vh, calc(100vh - 40px));
            display: flex;
            z-index: 1;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 -6px 20px rgba(0,0,0,0.10);
        }

        /* ---- Sidebar ---- */
        #wppack-debug .wpd-sidebar {
            width: 180px;
            flex-shrink: 0;
            background: #fafafa;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
            display: flex;
            flex-direction: column;
            padding: 4px 0;
        }
        #wppack-debug .wpd-sidebar::-webkit-scrollbar {
            width: 4px;
        }
        #wppack-debug .wpd-sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        #wppack-debug .wpd-sidebar::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 2px;
        }
        #wppack-debug .wpd-sidebar-item {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 8px 12px 8px 16px;
            background: transparent;
            border: none;
            border-left: 3px solid transparent;
            color: #6b7280;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            text-align: left;
            transition: background 0.12s ease, color 0.12s ease, border-color 0.12s ease;
        }
        #wppack-debug .wpd-sidebar-item:hover {
            background: #ffffff;
            color: #1f2937;
        }
        #wppack-debug .wpd-sidebar-item.wpd-active {
            background: #ffffff;
            color: #1f2937;
            border-left-color: #3858e9;
            font-weight: 600;
        }
        #wppack-debug .wpd-sidebar-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            flex-shrink: 0;
        }
        #wppack-debug .wpd-sidebar-label {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #wppack-debug .wpd-sidebar-divider {
            height: 1px;
            flex-shrink: 0;
            background: #e5e7eb;
            margin: 4px 16px;
        }

        /* ---- Content area ---- */
        #wppack-debug .wpd-content {
            flex: 1;
            min-width: 0;
            background: #ffffff;
            display: flex;
            flex-direction: column;
        }
        #wppack-debug .wpd-content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;
        }
        #wppack-debug .wpd-panel-title {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
        }
        #wppack-debug .wpd-panel-close {
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 2px 8px;
            border-radius: 4px;
            line-height: 1;
            transition: color 0.15s ease, background 0.15s ease;
        }
        #wppack-debug .wpd-panel-close:hover {
            color: #cc1818;
            background: transparent;
        }
        #wppack-debug .wpd-content-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 16px;
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }
        #wppack-debug .wpd-content-body::-webkit-scrollbar {
            width: 6px;
        }
        #wppack-debug .wpd-content-body::-webkit-scrollbar-track {
            background: transparent;
        }
        #wppack-debug .wpd-content-body::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }

        /* ---- Sections ---- */
        #wppack-debug .wpd-section {
            margin-bottom: 20px;
        }
        #wppack-debug .wpd-section:last-child {
            margin-bottom: 0;
        }
        #wppack-debug .wpd-section + .wpd-section {
            border-top: 1px solid #e5e7eb;
            margin-left: -16px;
            margin-right: -16px;
            padding: 20px 16px 0;
        }
        #wppack-debug .wpd-section-title {
            font-size: 11px;
            font-weight: 500;
            color: #1f2937;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }

        #wppack-debug .wpd-table-label {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 4px;
        }

        /* ---- Tables ---- */
        #wppack-debug .wpd-table {
            width: 100%;
            border-collapse: collapse;
        }
        #wppack-debug .wpd-table th,
        #wppack-debug .wpd-table td {
            padding: 6px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        #wppack-debug .wpd-table th:first-child,
        #wppack-debug .wpd-table td:first-child {
            border-left: 1px solid #e5e7eb;
        }
        #wppack-debug .wpd-table th:last-child,
        #wppack-debug .wpd-table td:last-child {
            border-right: 1px solid #e5e7eb;
        }
        #wppack-debug .wpd-table thead tr:first-child th,
        #wppack-debug .wpd-table tbody:first-child tr:first-child td {
            border-top: 1px solid #e5e7eb;
        }
        #wppack-debug .wpd-table thead th {
            font-size: 11px;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #fafafa;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        #wppack-debug .wpd-table-kv thead th {
            background: #ffffff;
        }
        #wppack-debug .wpd-table tbody tr:hover {
            background: #fafafa;
        }

        /* Key-value table */
        #wppack-debug .wpd-table-kv .wpd-kv-key {
            width: 200px;
            font-weight: 400;
            color: #6b7280;
            white-space: nowrap;
        }
        #wppack-debug .wpd-table-kv .wpd-kv-val {
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            font-size: 12px;
            word-break: break-all;
        }

        /* Right-aligned numeric columns */
        #wppack-debug .wpd-table .wpd-col-right {
            text-align: right;
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            font-size: 12px;
            white-space: nowrap;
        }

        /* Full-width table columns */
        #wppack-debug .wpd-table .wpd-col-reltime {
            width: 70px;
            white-space: nowrap;
            text-align: right;
            font-size: 12px;
        }
        #wppack-debug .wpd-table .wpd-col-num {
            width: 40px;
            text-align: center;
            color: #9ca3af;
            font-size: 11px;
        }
        #wppack-debug .wpd-table .wpd-col-sql {
            max-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #wppack-debug .wpd-table .wpd-col-sql code {
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            font-size: 12px;
            color: #1f2937;
            white-space: pre-wrap;
            word-break: break-all;
        }
        #wppack-debug .wpd-table .wpd-col-time {
            width: 90px;
            white-space: nowrap;
            text-align: right;
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            font-size: 12px;
        }
        #wppack-debug .wpd-table .wpd-col-caller {
            width: 260px;
        }
        #wppack-debug .wpd-caller {
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            font-size: 11px;
            color: #6b7280;
            word-break: break-all;
        }

        /* Query row highlighting */
        #wppack-debug .wpd-row-slow {
            background: rgba(204, 24, 24, 0.04);
        }
        #wppack-debug .wpd-row-slow:hover {
            background: rgba(204, 24, 24, 0.08);
        }
        #wppack-debug .wpd-row-duplicate {
            background: rgba(153, 104, 0, 0.04);
        }
        #wppack-debug .wpd-row-duplicate:hover {
            background: rgba(153, 104, 0, 0.08);
        }

        /* Query tags */
        #wppack-debug .wpd-query-tag {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1px 5px;
            border-radius: 4px;
            vertical-align: middle;
        }
        #wppack-debug .wpd-tag-slow {
            background: rgba(204, 24, 24, 0.08);
            color: #cc1818;
        }
        #wppack-debug .wpd-tag-dup {
            background: rgba(153, 104, 0, 0.08);
            color: #996800;
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
            color: #6b7280;
            text-align: right;
            flex-shrink: 0;
        }
        #wppack-debug .wpd-timeline-bar-wrap {
            flex: 1;
            height: 14px;
            background: #f3f4f6;
            border-radius: 4px;
            overflow: hidden;
        }
        #wppack-debug .wpd-timeline-bar {
            height: 100%;
            background: #3858e9;
            border-radius: 4px;
            min-width: 2px;
            transition: width 0.3s ease;
        }
        #wppack-debug .wpd-timeline-value {
            width: 160px;
            font-size: 11px;
            color: #9ca3af;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ---- Inline bar (compact progress bar next to value) ---- */
        #wppack-debug .wpd-inline-bar {
            display: inline-block;
            vertical-align: middle;
            width: 160px;
            height: 6px;
            background: #f3f4f6;
            border-radius: 3px;
            overflow: hidden;
            margin-right: 6px;
        }
        #wppack-debug .wpd-inline-bar-fill {
            display: block;
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        /* ---- Tags ---- */
        #wppack-debug .wpd-tag {
            display: inline-block;
            font-size: 11px;
            padding: 1px 7px;
            background: #f3f4f6;
            color: #6b7280;
            border-radius: 4px;
        }
        #wppack-debug .wpd-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        #wppack-debug .wpd-log-critical {
            background: rgba(153,0,0,0.12);
            color: #990000;
        }
        #wppack-debug .wpd-log-error {
            background: rgba(204,24,24,0.10);
            color: #cc1818;
        }
        #wppack-debug .wpd-log-warning {
            background: rgba(153,104,0,0.10);
            color: #996800;
        }
        #wppack-debug .wpd-log-notice {
            background: rgba(37,99,235,0.10);
            color: #2563eb;
        }
        #wppack-debug .wpd-log-info {
            background: rgba(0,138,32,0.10);
            color: #008a20;
        }
        #wppack-debug .wpd-log-deprecation {
            background: rgba(161,98,7,0.10);
            color: #a16207;
        }
        #wppack-debug .wpd-log-debug {
            background: rgba(107,114,128,0.10);
            color: #4b5563;
        }

        /* ---- Lists ---- */
        #wppack-debug .wpd-list {
            list-style: none;
            padding: 0;
        }
        #wppack-debug .wpd-list li {
            padding: 6px 12px;
            border-bottom: 1px solid #e5e7eb;
            border-left: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            font-size: 12px;
        }
        #wppack-debug .wpd-list li:first-child {
            border-top: 1px solid #e5e7eb;
        }
        #wppack-debug .wpd-list li:hover {
            background: #fafafa;
        }
        #wppack-debug .wpd-list code {
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            font-size: 12px;
        }

        /* ---- Suggestions ---- */
        #wppack-debug .wpd-suggestions {
            list-style: none;
            padding: 0;
        }
        #wppack-debug .wpd-suggestion-item {
            padding: 8px 12px;
            background: rgba(153, 104, 0, 0.06);
            border-left: 3px solid #996800;
            border-radius: 0 4px 4px 0;
            margin-bottom: 4px;
            font-size: 12px;
            color: #996800;
        }

        /* ---- Utility text colors ---- */
        #wppack-debug .wpd-text-green { color: #008a20; }
        #wppack-debug .wpd-text-yellow { color: #996800; }
        #wppack-debug .wpd-text-red { color: #cc1818; }
        #wppack-debug .wpd-text-orange { color: #b32d2e; }
        #wppack-debug .wpd-text-dim { color: #9ca3af; font-style: italic; }

        /* ---- Code blocks ---- */
        #wppack-debug code {
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            font-size: 12px;
        }

        /* ---- Performance cards ---- */
        #wppack-debug .wpd-perf-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        #wppack-debug .wpd-perf-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
        }
        #wppack-debug .wpd-perf-card-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.3px;
            order: -1;
            margin-bottom: 4px;
        }
        #wppack-debug .wpd-perf-card-value {
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            font-size: 18px;
            font-weight: 500;
            color: #374151;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #wppack-debug .wpd-perf-card-unit {
            font-size: 12px;
            font-weight: 400;
            color: #6b7280;
            margin-left: 2px;
        }
        #wppack-debug .wpd-perf-card-sub {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* ---- Time Distribution ---- */
        #wppack-debug .wpd-perf-dist-bar {
            display: flex;
            height: 20px;
            background: #f3f4f6;
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
            margin-top: 12px;
        }
        #wppack-debug .wpd-perf-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #6b7280;
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
            font-size: 12px;
            color: #6b7280;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #wppack-debug .wpd-perf-wf-track {
            flex: 1;
            height: 16px;
            position: relative;
            background: #f3f4f6;
            border-radius: 4px;
        }
        #wppack-debug .wpd-perf-wf-bar {
            position: absolute;
            top: 0;
            height: 100%;
            background: #3858e9;
            border-radius: 4px;
            min-width: 2px;
        }
        #wppack-debug .wpd-perf-wf-bar[data-tooltip] {
            cursor: pointer;
        }
        #wppack-debug .wpd-tooltip {
            position: fixed;
            z-index: 100001;
            background: #1f2937;
            color: #e5e7eb;
            font-size: 11px;
            font-family: inherit;
            line-height: 1.5;
            padding: 5px 8px;
            border-radius: 4px;
            border: 1px solid #4b5563;
            white-space: nowrap;
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
            font-size: 11px;
            color: #9ca3af;
            white-space: nowrap;
        }

        /* ---- Timeline dividers ---- */
        #wppack-debug .wpd-perf-wf-divider {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 6px 0 3px;
            color: #9ca3af;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        #wppack-debug .wpd-perf-wf-divider::before,
        #wppack-debug .wpd-perf-wf-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        /* ---- Log filter tabs ---- */
        #wppack-debug .wpd-log-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        #wppack-debug .wpd-log-tab {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: #9ca3af;
            padding: 8px 16px;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
        }
        #wppack-debug .wpd-log-tab:hover {
            color: #1f2937;
        }
        #wppack-debug .wpd-log-tab.wpd-active {
            color: #3858e9;
            border-bottom-color: #3858e9;
        }
        #wppack-debug .wpd-log-context td {
            background: #fafafa;
        }
        #wppack-debug .wpd-log-context pre {
            font-size: 11px;
            color: #6b7280;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
        }
        #wppack-debug .wpd-log-toggle {
            cursor: pointer;
        }
        #wppack-debug .wpd-col-toggle {
            width: 24px;
            text-align: center;
            padding-left: 0;
            padding-right: 0;
        }
        #wppack-debug .wpd-log-indicator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
            font-size: 11px;
            font-weight: 600;
            color: #9ca3af;
            border: 1px solid #d1d5db;
            border-radius: 3px;
        }
        #wppack-debug .wpd-log-toggle:hover .wpd-log-indicator {
            color: #3858e9;
            border-color: #3858e9;
        }

        /* ---- Plugin detail navigation ---- */
        #wppack-debug .wpd-plugin-detail-link {
            color: #3858e9;
            cursor: pointer;
            font-weight: 600;
        }
        #wppack-debug .wpd-plugin-detail-link:hover {
            text-decoration: underline;
        }
        #wppack-debug .wpd-plugin-back {
            background: transparent;
            border: 1px solid #3858e9;
            border-radius: 4px;
            color: #3858e9;
            cursor: pointer;
            font-family: inherit;
            font-size: 12px;
            padding: 4px 12px;
            transition: background 0.15s ease;
        }
        #wppack-debug .wpd-plugin-back:hover {
            background: #f3f4f6;
        }

        /* ---- Dump / code preview ---- */
        #wppack-debug .wpd-dump-item {
            margin-bottom: 12px;
        }
        #wppack-debug .wpd-dump-item:last-child {
            margin-bottom: 0;
        }
        #wppack-debug .wpd-dump-file {
            font-size: 12px;
            color: #6b7280;
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            margin-bottom: 4px;
        }
        #wppack-debug .wpd-dump-code {
            background: #fafafa;
            padding: 8px 12px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
        }

        /* ---- Mail body / attachments ---- */
        #wppack-debug .wpd-mail-body {
            margin-top: 8px;
        }
        #wppack-debug .wpd-mail-body .wpd-dump-code {
            max-height: 200px;
            overflow-y: auto;
        }
        #wppack-debug .wpd-mail-attachments {
            margin-top: 8px;
        }

        /* ---- Status tags (no margin-left, for use in titles) ---- */
        #wppack-debug .wpd-status-tag {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1px 5px;
            border-radius: 4px;
            vertical-align: middle;
        }
        #wppack-debug .wpd-status-sent {
            background: rgba(0, 163, 42, 0.08);
            color: #008a20;
        }
        #wppack-debug .wpd-status-failed {
            background: rgba(204, 24, 24, 0.08);
            color: #cc1818;
        }
        #wppack-debug .wpd-status-pending {
            background: rgba(153, 104, 0, 0.08);
            color: #996800;
        }

        /* ================================================================
           Responsive
           ================================================================ */

        /* Tablet: icon-only sidebar */
        @media (max-width: 1024px) {
            #wppack-debug .wpd-sidebar {
                width: 52px;
            }
            #wppack-debug .wpd-sidebar-label {
                display: none;
            }
            #wppack-debug .wpd-sidebar-item {
                justify-content: center;
                padding: 8px;
            }
        }

        /* Mobile: no sidebar */
        @media (max-width: 768px) {
            #wppack-debug .wpd-sidebar {
                display: none;
            }
            #wppack-debug .wpd-overlay {
                height: min(60vh, calc(100vh - 40px));
            }
            #wppack-debug .wpd-perf-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            #wppack-debug .wpd-perf-wf-label {
                width: 100px;
            }
            #wppack-debug .wpd-timeline-label {
                width: 100px;
            }
            #wppack-debug .wpd-table .wpd-col-caller {
                width: 160px;
            }
        }

        /* Small mobile */
        @media (max-width: 480px) {
            #wppack-debug .wpd-badge {
                padding: 0 8px;
            }
            #wppack-debug .wpd-badge-value {
                display: none;
            }
            #wppack-debug .wpd-bar-env {
                display: none;
            }
            #wppack-debug .wpd-bar-version {
                display: none;
            }
            #wppack-debug .wpd-perf-cards {
                grid-template-columns: 1fr;
            }
            #wppack-debug .wpd-perf-wf-label {
                width: 70px;
                font-size: 11px;
            }
            #wppack-debug .wpd-perf-wf-value {
                width: 60px;
            }
        }
        CSS;
    }

    public function renderJs(): string
    {
        return <<<'JS'
        (function() {
            var root = document.getElementById('wppack-debug');
            if (!root) return;

            var STORAGE_KEY = 'wppack-debug-minimized';
            if (localStorage.getItem(STORAGE_KEY) === '1') {
                root.classList.add('wpd-minimized');
            }

            var overlay = root.querySelector('.wpd-overlay');
            var contentHeader = root.querySelector('.wpd-content-header .wpd-panel-title');
            var activePanel = null;

            // Badge scroll fade
            var badgesWrap = root.querySelector('.wpd-bar-badges-wrap');
            var badgesScroll = root.querySelector('.wpd-bar-badges');
            function updateBadgeFade() {
                if (!badgesWrap || !badgesScroll) return;
                var sl = badgesScroll.scrollLeft;
                var maxSl = badgesScroll.scrollWidth - badgesScroll.clientWidth;
                if (maxSl <= 0) {
                    badgesWrap.classList.remove('wpd-fade-left', 'wpd-fade-right');
                    return;
                }
                badgesWrap.classList.toggle('wpd-fade-left', sl > 2);
                badgesWrap.classList.toggle('wpd-fade-right', sl < maxSl - 2);
            }
            if (badgesScroll) {
                badgesScroll.addEventListener('scroll', updateBadgeFade);
                updateBadgeFade();
            }

            function closeOverlay() {
                overlay.style.display = 'none';
                root.classList.remove('wpd-panel-open');
                var badges = root.querySelectorAll('.wpd-badge');
                for (var i = 0; i < badges.length; i++) {
                    badges[i].classList.remove('wpd-active');
                }
                var items = root.querySelectorAll('.wpd-sidebar-item');
                for (var i = 0; i < items.length; i++) {
                    items[i].classList.remove('wpd-active');
                }
                var wpBtn = root.querySelector('.wpd-bar-wp');
                if (wpBtn) wpBtn.classList.remove('wpd-active');
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
                // Show overlay
                overlay.style.display = 'flex';
                root.classList.add('wpd-panel-open');

                // Switch content
                var contents = root.querySelectorAll('.wpd-panel-content');
                for (var i = 0; i < contents.length; i++) {
                    contents[i].style.display = 'none';
                }
                var target = root.querySelector('#wpd-pc-' + name);
                if (target) target.style.display = '';

                // Scroll content to top
                var body = root.querySelector('.wpd-content-body');
                if (body) body.scrollTop = 0;

                // Update header title from sidebar label
                var sidebarItem = root.querySelector('.wpd-sidebar-item[data-panel="' + name + '"]');
                if (sidebarItem && contentHeader) {
                    var label = sidebarItem.querySelector('.wpd-sidebar-label');
                    contentHeader.textContent = label ? label.textContent : name;
                }

                // Highlight sidebar item
                var items = root.querySelectorAll('.wpd-sidebar-item');
                for (var i = 0; i < items.length; i++) {
                    if (items[i].getAttribute('data-panel') === name) {
                        items[i].classList.add('wpd-active');
                    } else {
                        items[i].classList.remove('wpd-active');
                    }
                }

                // Highlight badge
                var badges = root.querySelectorAll('.wpd-badge');
                for (var i = 0; i < badges.length; i++) {
                    if (badges[i].getAttribute('data-panel') === name) {
                        badges[i].classList.add('wpd-active');
                        var accent = badges[i].getAttribute('data-accent');
                        badges[i].style.setProperty('--wpd-accent', accent || '#3858e9');
                    } else {
                        badges[i].classList.remove('wpd-active');
                    }
                }

                // Highlight logo/version button for wordpress panel
                var wpBtn = root.querySelector('.wpd-bar-wp');
                if (wpBtn) {
                    if (name === 'wordpress') {
                        wpBtn.classList.add('wpd-active');
                    } else {
                        wpBtn.classList.remove('wpd-active');
                    }
                }

                activePanel = name;
                resetPluginDetailView();
            }

            root.addEventListener('click', function(e) {
                // Mini button — restore toolbar
                var miniBtn = e.target.closest('.wpd-mini');
                if (miniBtn) {
                    root.classList.remove('wpd-minimized');
                    localStorage.removeItem(STORAGE_KEY);
                    return;
                }

                // Logo/version click — toggle WordPress panel
                var wpBtn = e.target.closest('.wpd-bar-wp');
                if (wpBtn) {
                    var panel = wpBtn.getAttribute('data-panel');
                    if (activePanel === panel) {
                        closeOverlay();
                    } else {
                        openPanel(panel);
                    }
                    return;
                }

                // Sidebar item click — always switch, never close
                var sidebarItem = e.target.closest('.wpd-sidebar-item');
                if (sidebarItem) {
                    var panel = sidebarItem.getAttribute('data-panel');
                    if (activePanel !== panel) {
                        openPanel(panel);
                    }
                    return;
                }

                // Badge click — toggle panel
                var badge = e.target.closest('.wpd-badge');
                if (badge) {
                    var panel = badge.getAttribute('data-panel');
                    if (activePanel === panel) {
                        closeOverlay();
                    } else {
                        openPanel(panel);
                    }
                    return;
                }

                // Environment info click — toggle panel
                var envBar = e.target.closest('.wpd-bar-env');
                if (envBar) {
                    var panel = envBar.getAttribute('data-panel');
                    if (panel) {
                        if (activePanel === panel) {
                            closeOverlay();
                        } else {
                            openPanel(panel);
                        }
                    }
                    return;
                }

                // Close button in content header
                var closeBtn = e.target.closest('[data-action="close-panel"]');
                if (closeBtn) {
                    closeOverlay();
                    return;
                }

                // Plugin detail link
                var pluginLink = e.target.closest('.wpd-plugin-detail-link');
                if (pluginLink) {
                    var pluginSlug = pluginLink.getAttribute('data-plugin');
                    var panelContent = pluginLink.closest('.wpd-panel-content');
                    if (panelContent) {
                        var list = panelContent.querySelector('.wpd-plugin-list');
                        if (list) list.style.display = 'none';
                        var detail = panelContent.querySelector('.wpd-plugin-detail[data-plugin="' + pluginSlug + '"]');
                        if (detail) detail.style.display = '';
                    }
                    return;
                }

                // Plugin back button
                var backBtn = e.target.closest('[data-action="plugin-back"]');
                if (backBtn) {
                    var panelContent = backBtn.closest('.wpd-panel-content');
                    if (panelContent) {
                        var details = panelContent.querySelectorAll('.wpd-plugin-detail');
                        for (var i = 0; i < details.length; i++) {
                            details[i].style.display = 'none';
                        }
                        var list = panelContent.querySelector('.wpd-plugin-list');
                        if (list) list.style.display = '';
                    }
                    return;
                }

                // Close/minimize toolbar
                var minimizeBtn = e.target.closest('[data-action="minimize"]');
                if (minimizeBtn) {
                    closeOverlay();
                    root.classList.add('wpd-minimized');
                    localStorage.setItem(STORAGE_KEY, '1');
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
                        var opening = ctx.style.display === 'none';
                        ctx.style.display = opening ? '' : 'none';
                        var indicator = toggle.querySelector('.wpd-log-indicator');
                        if (indicator) indicator.textContent = opening ? '\u2212' : '+';
                    }
                }
            });

            // Escape key closes overlay
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && activePanel !== null) {
                    closeOverlay();
                }
            });

            // Timeline bar tooltips
            var tooltip = document.createElement('div');
            tooltip.className = 'wpd-tooltip';
            tooltip.style.display = 'none';
            root.appendChild(tooltip);

            root.addEventListener('mouseover', function(e) {
                var el = e.target.closest('[data-tooltip]');
                if (!el) return;
                tooltip.textContent = el.getAttribute('data-tooltip');
                tooltip.style.display = '';
                var rect = el.getBoundingClientRect();
                var tipRect = tooltip.getBoundingClientRect();
                var left = rect.left + rect.width / 2 - tipRect.width / 2;
                if (left < 4) left = 4;
                if (left + tipRect.width > window.innerWidth - 4) left = window.innerWidth - 4 - tipRect.width;
                tooltip.style.left = left + 'px';
                tooltip.style.top = (rect.top - tipRect.height - 6) + 'px';
            });

            root.addEventListener('mouseout', function(e) {
                var el = e.target.closest('[data-tooltip]');
                if (el) tooltip.style.display = 'none';
            });
        })();

        // --- Ajax request tracking ---
        (function(){
            var ajaxCount = 0;
            function isAdminAjax(url){
                try { return new URL(url, location.origin).pathname.indexOf('admin-ajax.php') !== -1; } catch(e){ return false; }
            }
            function extractAction(url, body){
                try {
                    var u = new URL(url, location.origin);
                    var a = u.searchParams.get('action');
                    if(a) return a;
                } catch(e){}
                if(body){
                    if(typeof body === 'string'){
                        try { var p = new URLSearchParams(body); var a2 = p.get('action'); if(a2) return a2; } catch(e){}
                    }
                    if(typeof FormData !== 'undefined' && body instanceof FormData){
                        var a3 = body.get('action'); if(a3) return String(a3);
                    }
                }
                return '(unknown)';
            }
            function addAjaxRow(action, method, status, duration, size){
                var tbody = document.getElementById('wpd-ajax-tbody');
                var empty = document.getElementById('wpd-ajax-empty');
                if(!tbody) return;
                if(empty) empty.style.display = 'none';
                ajaxCount++;
                var tr = document.createElement('tr');
                var statusColor = status >= 200 && status < 300 ? '#008a20' : (status >= 400 ? '#cc1818' : '#996800');
                tr.innerHTML = '<td><code>' + action + '</code></td>'
                    + '<td>' + method + '</td>'
                    + '<td><span style="color:' + statusColor + '">' + status + '</span></td>'
                    + '<td class="wpd-col-right">' + duration.toFixed(0) + ' ms</td>'
                    + '<td class="wpd-col-right">' + (size > 0 ? (size > 1024 ? (size/1024).toFixed(1) + ' KB' : size + ' B') : '-') + '</td>';
                tbody.appendChild(tr);
                // Update badge
                var badges = document.querySelectorAll('.wpd-badge[data-panel="ajax"] .wpd-badge-value');
                badges.forEach(function(b){ b.textContent = String(ajaxCount); });
            }

            // Patch XMLHttpRequest
            var origOpen = XMLHttpRequest.prototype.open;
            var origSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.open = function(method, url){
                this._wpdMethod = method;
                this._wpdUrl = String(url);
                return origOpen.apply(this, arguments);
            };
            XMLHttpRequest.prototype.send = function(body){
                if(this._wpdUrl && isAdminAjax(this._wpdUrl)){
                    var self = this;
                    var action = extractAction(self._wpdUrl, body);
                    var method = (self._wpdMethod || 'GET').toUpperCase();
                    var start = performance.now();
                    self.addEventListener('loadend', function(){
                        var dur = performance.now() - start;
                        var size = 0;
                        try { var r = self.responseText; if(r) size = r.length; } catch(e){}
                        addAjaxRow(action, method, self.status, dur, size);
                    });
                }
                return origSend.apply(this, arguments);
            };

            // Patch fetch
            var origFetch = window.fetch;
            if(origFetch){
                window.fetch = function(input, init){
                    var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                    if(!isAdminAjax(url)) return origFetch.apply(this, arguments);
                    var method = ((init && init.method) || 'GET').toUpperCase();
                    var body = (init && init.body) || null;
                    var action = extractAction(url, body);
                    var start = performance.now();
                    return origFetch.apply(this, arguments).then(function(response){
                        var dur = performance.now() - start;
                        var clone = response.clone();
                        clone.text().then(function(text){
                            addAjaxRow(action, method, response.status, dur, text.length);
                        }).catch(function(){
                            addAjaxRow(action, method, response.status, dur, 0);
                        });
                        return response;
                    }).catch(function(err){
                        addAjaxRow(action, method, 0, performance.now() - start, 0);
                        throw err;
                    });
                };
            }
        })();
        JS;
    }
}
