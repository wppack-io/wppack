<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'admin', priority: 115)]
final class AdminDataCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'admin';
    }

    public function getLabel(): string
    {
        return 'Admin';
    }

    public function collect(): void
    {
        if (!function_exists('is_admin') || !is_admin()) {
            $this->data = [
                'is_admin' => false,
                'page_hook' => '',
                'screen' => [],
                'admin_menus' => [],
                'admin_bar_nodes' => [],
                'total_menus' => 0,
                'total_submenus' => 0,
            ];

            return;
        }

        global $menu, $submenu, $pagenow, $wp_admin_bar;

        $screen = [];
        if (function_exists('get_current_screen')) {
            $currentScreen = get_current_screen();
            if ($currentScreen !== null) {
                $screen = [
                    'id' => $currentScreen->id,
                    'base' => $currentScreen->base,
                    'post_type' => $currentScreen->post_type,
                    'taxonomy' => $currentScreen->taxonomy,
                ];
            }
        }

        $adminMenus = [];
        $totalSubmenus = 0;
        if (is_array($menu)) {
            foreach ($menu as $menuItem) {
                if (!is_array($menuItem) || !isset($menuItem[0]) || $menuItem[0] === '') {
                    continue;
                }

                $menuSlug = $menuItem[2] ?? '';
                $menuEntry = [
                    'title' => wp_strip_all_tags($menuItem[0]),
                    'slug' => $menuSlug,
                    'capability' => $menuItem[1] ?? '',
                ];

                if (isset($submenu[$menuSlug]) && is_array($submenu[$menuSlug])) {
                    $subItems = [];
                    foreach ($submenu[$menuSlug] as $subItem) {
                        if (is_array($subItem)) {
                            $subItems[] = [
                                'title' => wp_strip_all_tags($subItem[0] ?? ''),
                                'slug' => $subItem[2] ?? '',
                            ];
                        }
                    }
                    $menuEntry['submenu'] = $subItems;
                    $totalSubmenus += count($subItems);
                }

                $adminMenus[] = $menuEntry;
            }
        }

        $adminBarNodes = [];
        if ($wp_admin_bar instanceof \WP_Admin_Bar) {
            $nodes = $wp_admin_bar->get_nodes();
            if (is_array($nodes)) {
                foreach ($nodes as $node) {
                    if ($node->parent === '') {
                        $adminBarNodes[] = [
                            'id' => $node->id,
                            'title' => wp_strip_all_tags($node->title ?? ''),
                        ];
                    }
                }
            }
        }

        $this->data = [
            'is_admin' => true,
            'page_hook' => $pagenow ?? '',
            'screen' => $screen,
            'admin_menus' => $adminMenus,
            'admin_bar_nodes' => $adminBarNodes,
            'total_menus' => count($adminMenus),
            'total_submenus' => $totalSubmenus,
        ];
    }

    public function getBadgeValue(): string
    {
        if (!($this->data['is_admin'] ?? false)) {
            return '';
        }

        $screen = $this->data['screen'] ?? [];

        return (string) ($screen['id'] ?? $this->data['page_hook'] ?? '');
    }

    public function getBadgeColor(): string
    {
        return 'default';
    }
}
