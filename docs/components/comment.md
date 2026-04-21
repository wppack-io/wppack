# Comment コンポーネント

**パッケージ:** `wppack/comment`
**名前空間:** `WPPack\Component\Comment\`
**Category:** Content

WordPress コメント関連フックを Named Hook アトリビュートで型安全に利用するためのコンポーネントです。

> [!WARNING]
> このコンポーネントは設計段階です。ソースコードの実装はまだありません。以下は設計仕様として参照してください。

## インストール

```bash
composer require wppack/comment
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_filter('comment_text', 'filter_comment_text', 10, 2);
function filter_comment_text(string $comment_text, ?WP_Comment $comment): string {
    return wpautop($comment_text);
}

add_filter('pre_comment_approved', 'moderate_comment', 10, 2);
function moderate_comment($approved, array $commentdata) {
    if (str_contains($commentdata['comment_content'], 'spam')) {
        return 'spam';
    }
    return $approved;
}

add_action('comment_post', 'after_comment_post', 10, 3);
function after_comment_post(int $comment_id, $approved, array $commentdata): void {
    // Send notification
}
```

### After（WPPack）

```php
use WPPack\Component\Comment\Attribute\CommentTextFilter;
use WPPack\Component\Comment\Attribute\PreCommentApprovedFilter;
use WPPack\Component\Comment\Attribute\CommentPostAction;

class CommentHandler
{
    #[CommentTextFilter]
    public function filterCommentText(string $commentText, ?\WP_Comment $comment): string
    {
        return wpautop($commentText);
    }

    #[PreCommentApprovedFilter]
    public function moderateComment(int|string $approved, array $commentdata): int|string
    {
        if (str_contains($commentdata['comment_content'], 'spam')) {
            return 'spam';
        }
        return $approved;
    }

    #[CommentPostAction]
    public function afterCommentPost(int $commentId, int|string $approved, array $commentdata): void
    {
        // Send notification
    }
}
```

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Comment](./hook/comment.md) を参照してください。

## WordPress 統合

- **WordPress コメントシステム**との完全な互換性を維持
- **WordPress ディスカッション設定**（コメント承認、スレッド表示等）と連携
- **Akismet** などのスパム対策プラグインと共存可能
- **WordPress のユーザーロールと権限**（`moderate_comments` など）と互換性あり
- **マルチサイトネットワーク**でのサイトごとのコメント設定に対応

## 依存関係

### 推奨
- **Mailer コンポーネント** - コメント通知メール送信用
- **EventDispatcher コンポーネント** - コメントライフサイクルイベント用
