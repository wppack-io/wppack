# Comment コンポーネント

**パッケージ:** `wppack/comment`
**名前空間:** `WpPack\Component\Comment\`
**レイヤー:** Application

WordPress コメント関連フックを Named Hook アトリビュートで型安全に利用するためのコンポーネントです。

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

### After（WpPack）

```php
use WpPack\Component\Comment\Attribute\CommentTextFilter;
use WpPack\Component\Comment\Attribute\PreCommentApprovedFilter;
use WpPack\Component\Comment\Attribute\CommentPostAction;

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

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](./hook/comment.md) を参照してください。
## Hook アトリビュートリファレンス

| アトリビュート | WordPress フック | 種別 | 説明 |
|---|---|---|---|
| `#[CommentTextFilter]` | `comment_text` | Filter | コメントテキストの表示を変更 |
| `#[PreCommentApprovedFilter]` | `pre_comment_approved` | Filter | コメントの承認状態を判定 |
| `#[CommentFormDefaultFieldsFilter]` | `comment_form_default_fields` | Filter | フォームのデフォルトフィールドをカスタマイズ |
| `#[CommentFormFieldCommentFilter]` | `comment_form_field_comment` | Filter | コメント本文フィールドをカスタマイズ |
| `#[PreCommentOnPostAction]` | `pre_comment_on_post` | Action | コメント投稿前の検証処理 |
| `#[CommentPostAction]` | `comment_post` | Action | コメント投稿後の処理 |
| `#[WpInsertCommentAction]` | `wp_insert_comment` | Action | コメント挿入後の処理 |
| `#[TransitionCommentStatusAction]` | `transition_comment_status` | Action | コメントステータス変更時の処理 |
| `#[EditCommentAction]` | `edit_comment` | Action | コメント編集後の処理 |

すべてのアトリビュートは `priority` パラメータ（デフォルト: `10`）をサポートします。

## WordPress 統合

- **WordPress コメントシステム**との完全な互換性を維持
- **WordPress ディスカッション設定**（コメント承認、スレッド表示等）と連携
- **Akismet** などのスパム対策プラグインと共存可能
- **WordPress のユーザーロールと権限**（`moderate_comments` など）と互換性あり
- **マルチサイトネットワーク**でのサイトごとのコメント設定に対応

## 依存関係

### 必須
- **Hook コンポーネント** - Named Hook アトリビュートの基盤

### 推奨
- **Mailer コンポーネント** - コメント通知メール送信用
- **EventDispatcher コンポーネント** - コメントライフサイクルイベント用
