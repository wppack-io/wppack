# Block Named Hook アトリビュート

Gutenberg ブロックエディターに関連するフック（ブロックアセットの読み込み、カテゴリ管理、レンダリングカスタマイズ、ブロック登録設定など）の Named Hook アトリビュートです。

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Block/Subscriber/`

## アクション

| アトリビュート | WordPress フック | 説明 |
|---|---|---|
| `#[EnqueueBlockAssetsAction]` | `enqueue_block_assets` | ブロックのフロント・エディター共通アセットの読み込み |
| `#[EnqueueBlockEditorAssetsAction]` | `enqueue_block_editor_assets` | ブロックエディター専用アセットの読み込み |

## フィルター

| アトリビュート | WordPress フック | 説明 |
|---|---|---|
| `#[BlockCategoriesAllFilter]` | `block_categories_all` | ブロックカテゴリの追加・変更 |
| `#[BlockEditorSettingsAllFilter]` | `block_editor_settings_all` | ブロックエディターの設定を変更 |
| `#[BlockTypeMetadataFilter]` | `block_type_metadata` | ブロックタイプのメタデータを変更 |
| `#[BlockTypeMetadataSettingsFilter]` | `block_type_metadata_settings` | ブロックタイプのメタデータ設定を変更 |
| `#[RegisterBlockTypeArgsFilter]` | `register_block_type_args` | ブロックタイプ登録時の引数を変更 |
| `#[PreRenderBlockFilter]` | `pre_render_block` | ブロックレンダリング前にコンテンツを差し替え |
| `#[RenderBlockFilter]` | `render_block` | ブロックのレンダリング出力を変更 |
| `#[RenderBlockDataFilter]` | `render_block_data` | ブロックのレンダリングデータを変更 |
| `#[RestPreInsertBlockFilter]` | `rest_pre_insert_block` | REST API 経由のブロック挿入前にデータを変更 |
| `#[RestPrepareBlockFilter]` | `rest_prepare_block` | REST API のブロックレスポンスを変更 |

## コード例

### ブロックカテゴリとエディターアセットの管理

```php
<?php

declare(strict_types=1);

namespace App\Block\Subscriber;

use WpPack\Component\Hook\Attribute\Block\Action\EnqueueBlockEditorAssetsAction;
use WpPack\Component\Hook\Attribute\Block\Filter\BlockCategoriesAllFilter;
use WpPack\Component\Hook\Attribute\Block\Filter\BlockEditorSettingsAllFilter;

final class BlockEditorSubscriber
{
    #[BlockCategoriesAllFilter]
    public function addCustomCategories(array $categories, \WP_Block_Editor_Context $context): array
    {
        array_unshift($categories, [
            'slug' => 'my-plugin',
            'title' => __('My Plugin Blocks', 'my-plugin'),
            'icon' => 'star-filled',
        ]);

        return $categories;
    }

    #[EnqueueBlockEditorAssetsAction]
    public function enqueueEditorAssets(): void
    {
        wp_enqueue_script(
            'my-plugin-blocks',
            plugins_url('build/blocks.js', __DIR__),
            ['wp-blocks', 'wp-element', 'wp-editor'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'my-plugin-blocks-editor',
            plugins_url('build/editor.css', __DIR__),
            ['wp-edit-blocks'],
            '1.0.0'
        );
    }

    #[BlockEditorSettingsAllFilter]
    public function customizeEditorSettings(array $settings, \WP_Block_Editor_Context $context): array
    {
        // カスタムフォントサイズを追加
        $settings['__experimentalFeatures']['typography']['fontSizes']['custom'] = [
            ['name' => __('Extra Small', 'my-plugin'), 'slug' => 'extra-small', 'size' => '12px'],
            ['name' => __('Extra Large', 'my-plugin'), 'slug' => 'extra-large', 'size' => '48px'],
        ];

        return $settings;
    }
}
```

### ブロックのレンダリングカスタマイズ

```php
<?php

declare(strict_types=1);

namespace App\Block\Subscriber;

use WpPack\Component\Hook\Attribute\Block\Filter\RenderBlockFilter;
use WpPack\Component\Hook\Attribute\Block\Filter\PreRenderBlockFilter;
use WpPack\Component\Hook\Attribute\Block\Filter\RenderBlockDataFilter;

final class BlockRenderSubscriber
{
    #[PreRenderBlockFilter]
    public function preRenderBlock(?string $preRender, array $parsedBlock, ?\WP_Block $parentBlock): ?string
    {
        // 特定のブロックをキャッシュから取得
        if ($parsedBlock['blockName'] === 'my-plugin/expensive-block') {
            $cacheKey = 'block_' . md5(serialize($parsedBlock['attrs']));
            $cached = wp_cache_get($cacheKey, 'my-plugin-blocks');
            if ($cached !== false) {
                return $cached;
            }
        }

        return $preRender;
    }

    #[RenderBlockFilter]
    public function modifyBlockOutput(string $blockContent, array $block): string
    {
        // 画像ブロックに遅延読み込みを追加
        if ($block['blockName'] === 'core/image') {
            $blockContent = str_replace('<img ', '<img loading="lazy" ', $blockContent);
        }

        // テーブルブロックにレスポンシブラッパーを追加
        if ($block['blockName'] === 'core/table') {
            $blockContent = '<div class="table-responsive">' . $blockContent . '</div>';
        }

        return $blockContent;
    }

    #[RenderBlockDataFilter]
    public function modifyBlockData(array $parsedBlock, array $sourceBlock, ?\WP_Block $parentBlock): array
    {
        // 段落ブロックにデフォルトクラスを追加
        if ($parsedBlock['blockName'] === 'core/paragraph') {
            $parsedBlock['attrs']['className'] = trim(
                ($parsedBlock['attrs']['className'] ?? '') . ' my-plugin-paragraph'
            );
        }

        return $parsedBlock;
    }
}
```

### ブロックタイプ登録の制御

```php
<?php

declare(strict_types=1);

namespace App\Block\Subscriber;

use WpPack\Component\Hook\Attribute\Block\Action\EnqueueBlockAssetsAction;
use WpPack\Component\Hook\Attribute\Block\Filter\RegisterBlockTypeArgsFilter;

final class BlockRegistrationSubscriber
{
    #[RegisterBlockTypeArgsFilter]
    public function modifyBlockTypeArgs(array $args, string $blockType): array
    {
        // 特定のコアブロックを無効化
        $disabledBlocks = ['core/latest-comments', 'core/rss'];
        if (in_array($blockType, $disabledBlocks, true)) {
            $args['supports'] = array_merge($args['supports'] ?? [], ['inserter' => false]);
        }

        return $args;
    }

    #[EnqueueBlockAssetsAction]
    public function enqueueSharedAssets(): void
    {
        wp_enqueue_style(
            'my-plugin-blocks-shared',
            plugins_url('build/blocks-shared.css', __DIR__),
            [],
            '1.0.0'
        );
    }
}
```
