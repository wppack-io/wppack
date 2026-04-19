## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/PostType/Subscriber/`

### 投稿保存フック

#### #[SavePostAction]

**WordPress Hook:** `save_post` / `save_post_{post_type}`

`postType` パラメータを指定すると、特定の投稿タイプに限定したフック `save_post_{post_type}` が使用されます。

```php
use WPPack\Component\Hook\Attribute\PostType\Action\SavePostAction;

class PostSaveHandler
{
    // すべての投稿タイプ
    #[SavePostAction]
    public function onSavePost(int $postId, \WP_Post $post, bool $update): void
    {
        if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }

        // カスタムメタデータを保存
        if (isset($_POST['custom_field'])) {
            update_post_meta($postId, 'custom_field', sanitize_text_field($_POST['custom_field']));
        }
    }

    // 特定の投稿タイプに限定
    #[SavePostAction(postType: 'product')]
    public function onSaveProduct(int $postId, \WP_Post $post, bool $update): void
    {
        // 商品情報の保存処理
        if (isset($_POST['price'])) {
            update_post_meta($postId, 'price', floatval($_POST['price']));
        }
    }
}
```

### 投稿削除フック

#### #[DeletePostAction]

**WordPress Hook:** `delete_post`

```php
use WPPack\Component\Hook\Attribute\PostType\Action\DeletePostAction;

class PostDeleteHandler
{
    #[DeletePostAction]
    public function onDeletePost(int $postId, \WP_Post $post): void
    {
        // 関連データのクリーンアップ
        delete_post_meta($postId, 'custom_field');
    }
}
```

### 投稿ステータス変更フック

#### #[TransitionPostStatusAction]

**WordPress Hook:** `transition_post_status`

```php
use WPPack\Component\Hook\Attribute\PostType\Action\TransitionPostStatusAction;

class PostStatusHandler
{
    #[TransitionPostStatusAction]
    public function onStatusChange(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        // 公開時に通知を送信
        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            $this->notifySubscribers($post);
        }

        // 非公開化時にキャッシュをクリア
        if ($oldStatus === 'publish' && $newStatus !== 'publish') {
            $this->clearCache($post);
        }
    }
}
```

## クイックリファレンス

```php
// 投稿保存
#[SavePostAction(postType?: string = null, priority?: int = 10)]  // 投稿保存時（postType で投稿タイプを限定可能）

// 投稿削除
#[DeletePostAction(priority?: int = 10)]                          // 投稿削除時

// ステータス変更
#[TransitionPostStatusAction(priority?: int = 10)]                // 投稿ステータス変更時
```
