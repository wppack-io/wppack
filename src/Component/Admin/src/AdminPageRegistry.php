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

namespace WpPack\Component\Admin;

use WpPack\Component\Templating\TemplateRendererInterface;

final class AdminPageRegistry
{
    /**
     * Tracks registered pages per parent slug for post-registration sorting.
     *
     * @var array<string, array<string, int>> parent => [slug => position]
     */
    private array $submenuPositions = [];

    public function __construct(
        private readonly ?TemplateRendererInterface $renderer = null,
    ) {}

    public function register(AbstractAdminPage $page, bool $network = false): void
    {
        $page->setNetwork($network);

        if ($this->renderer !== null) {
            $page->setTemplateRenderer($this->renderer);
        }

        $menuHook = $page->isNetwork() ? 'network_admin_menu' : 'admin_menu';
        add_action($menuHook, $page->addMenuPage(...));

        // Track submenu positions for sorting
        $parent = $page->parent;
        if ($parent !== null && $page->position !== null) {
            if ($network && $parent === 'options-general.php') {
                $parent = 'settings.php';
            }
            $this->submenuPositions[$parent][$page->slug] = $page->position;
            add_action($menuHook, $this->sortSubmenu(...), 999);
        }

        if ($page->hasEnqueueOverride()) {
            add_action('admin_enqueue_scripts', $page->handleEnqueue(...));
        }
    }

    /**
     * Re-sort only WpPack submenu items by their registered position value,
     * keeping WordPress core items in their original positions.
     */
    private function sortSubmenu(): void
    {
        global $submenu;

        foreach ($this->submenuPositions as $parent => $slugPositions) {
            if (!isset($submenu[$parent]) || !\is_array($submenu[$parent])) {
                continue;
            }

            // Separate WpPack items from the rest
            $wppackItems = [];
            $otherItems = [];

            foreach ($submenu[$parent] as $item) {
                if (isset($slugPositions[$item[2]])) {
                    $wppackItems[] = $item;
                } else {
                    $otherItems[] = $item;
                }
            }

            // Sort WpPack items by position
            usort($wppackItems, fn (array $a, array $b): int => $slugPositions[$a[2]] <=> $slugPositions[$b[2]]);

            // Append sorted WpPack items after core items
            $submenu[$parent] = array_merge($otherItems, $wppackItems);
        }
    }

    public function unregister(string $menuSlug): void
    {
        remove_menu_page($menuSlug);
    }

    public function unregisterSubmenu(string $parentSlug, string $menuSlug): void
    {
        remove_submenu_page($parentSlug, $menuSlug);
    }
}
