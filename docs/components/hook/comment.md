## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Comment/Subscriber/`

### コメント表示フック

#### #[CommentTextFilter]

**WordPress フック:** `comment_text`

コメント本文の表示時にテキストを加工するフィルターです。

```php
use WpPack\Component\Hook\Attribute\Comment\CommentTextFilter;

class CommentFormatter
{
    #[CommentTextFilter]
    public function formatCommentText(string $commentText, ?\WP_Comment $comment): string
    {
        // メンション（@username）をリンクに変換
        $commentText = preg_replace(
            '/@([a-zA-Z0-9_]+)/',
            '<a href="/author/$1" class="mention">@$1</a>',
            $commentText
        );

        // インラインコードをハイライト
        $commentText = preg_replace(
            '/`([^`]+)`/',
            '<code>$1</code>',
            $commentText
        );

        return $commentText;
    }
}
```

### コメント承認フック

#### #[PreCommentApprovedFilter]

**WordPress フック:** `pre_comment_approved`

コメントの承認状態を決定するフィルターです。スパム判定や自動承認のロジックを実装できます。

```php
use WpPack\Component\Hook\Attribute\Comment\PreCommentApprovedFilter;

class CommentModerator
{
    #[PreCommentApprovedFilter]
    public function moderateComment(int|string $approved, array $commentdata): int|string
    {
        // 過去に承認済みのメールアドレスからのコメントは自動承認
        $previousCount = get_comments([
            'author_email' => $commentdata['comment_author_email'],
            'status' => 'approve',
            'count' => true,
        ]);

        if ($previousCount >= 3) {
            return 1;
        }

        // スパムワードが含まれている場合はスパムとして処理
        $spamWords = ['buy now', 'free money', 'click here'];
        $content = strtolower($commentdata['comment_content']);

        foreach ($spamWords as $word) {
            if (str_contains($content, $word)) {
                return 'spam';
            }
        }

        return $approved;
    }
}
```

### コメントフォームフック

#### #[CommentFormDefaultFieldsFilter]

**WordPress フック:** `comment_form_default_fields`

コメントフォームのデフォルトフィールド（名前・メール・URL）をカスタマイズするフィルターです。

```php
use WpPack\Component\Hook\Attribute\Comment\CommentFormDefaultFieldsFilter;

class CommentFormCustomizer
{
    #[CommentFormDefaultFieldsFilter]
    public function customizeFormFields(array $fields): array
    {
        // URL フィールドを削除
        unset($fields['url']);

        // 電話番号フィールドを追加
        $fields['phone'] = sprintf(
            '<p class="comment-form-phone"><label for="phone">%s</label>' .
            '<input id="phone" name="phone" type="tel" value="" size="30" /></p>',
            __('Phone (optional)', 'wppack')
        );

        return $fields;
    }
}
```

#### #[CommentFormFieldCommentFilter]

**WordPress フック:** `comment_form_field_comment`

コメント本文のテキストエリアフィールドをカスタマイズするフィルターです。

```php
use WpPack\Component\Hook\Attribute\Comment\CommentFormFieldCommentFilter;

class CommentFieldCustomizer
{
    #[CommentFormFieldCommentFilter]
    public function customizeCommentField(string $field): string
    {
        // プレースホルダーを追加
        $field = str_replace(
            '<textarea ',
            '<textarea placeholder="' . esc_attr__('Write your comment here...', 'wppack') . '" ',
            $field
        );

        // 最大文字数を設定
        $field = str_replace(
            '<textarea ',
            '<textarea maxlength="2000" ',
            $field
        );

        return $field;
    }
}
```

### コメント前処理フック

#### #[PreCommentOnPostAction]

**WordPress フック:** `pre_comment_on_post`

コメントが投稿に紐付けられる前に実行されるアクションです。投稿のコメント可否チェックなどに利用できます。

```php
use WpPack\Component\Hook\Attribute\Comment\PreCommentOnPostAction;

class CommentPreCheck
{
    #[PreCommentOnPostAction]
    public function checkBeforeComment(int $postId): void
    {
        $post = get_post($postId);

        // 公開から90日以上経過した投稿へのコメントを禁止
        $publishedDate = strtotime($post->post_date);
        $daysOld = (time() - $publishedDate) / DAY_IN_SECONDS;

        if ($daysOld > 90) {
            wp_die(
                __('This post is too old to accept new comments.', 'wppack'),
                __('Comments Closed', 'wppack'),
                ['response' => 403]
            );
        }
    }
}
```

### コメント投稿フック

#### #[CommentPostAction]

**WordPress フック:** `comment_post`

コメントがデータベースに保存された直後に実行されるアクションです。通知送信やメタデータの追加に利用できます。

```php
use WpPack\Component\Hook\Attribute\Comment\CommentPostAction;

class CommentNotifier
{
    #[CommentPostAction]
    public function onCommentPost(int $commentId, int|string $approved, array $commentdata): void
    {
        $comment = get_comment($commentId);
        if (!$comment) {
            return;
        }

        // 投稿者に通知
        $post = get_post($comment->comment_post_ID);
        if ($post && $post->post_author) {
            $author = get_userdata($post->post_author);
            wp_mail(
                $author->user_email,
                sprintf(__('New comment on "%s"', 'wppack'), $post->post_title),
                sprintf(__('%s left a new comment.', 'wppack'), $comment->comment_author)
            );
        }

        // 親コメントの作者に返信通知
        if ($comment->comment_parent) {
            $parent = get_comment($comment->comment_parent);
            if ($parent && $parent->comment_author_email) {
                wp_mail(
                    $parent->comment_author_email,
                    __('New reply to your comment', 'wppack'),
                    sprintf(__('%s replied to your comment.', 'wppack'), $comment->comment_author)
                );
            }
        }
    }
}
```

#### #[WpInsertCommentAction]

**WordPress フック:** `wp_insert_comment`

`wp_insert_comment()` でコメントが挿入されたときに実行されるアクションです。コメントオブジェクトが引数として渡されます。

```php
use WpPack\Component\Hook\Attribute\Comment\WpInsertCommentAction;

class CommentStatisticsUpdater
{
    #[WpInsertCommentAction]
    public function onInsertComment(int $commentId, \WP_Comment $comment): void
    {
        // コメント統計を更新
        $postId = $comment->comment_post_ID;
        $count = wp_count_comments($postId);
        update_post_meta($postId, '_approved_comment_count', $count->approved);

        // 最新コメント日時を記録
        update_post_meta($postId, '_last_comment_date', $comment->comment_date);
    }
}
```

### コメントステータスフック

#### #[TransitionCommentStatusAction]

**WordPress フック:** `transition_comment_status`

コメントのステータスが変更されたときに実行されるアクションです。承認・スパム判定・ゴミ箱移動などの状態遷移を追跡できます。

```php
use WpPack\Component\Hook\Attribute\Comment\TransitionCommentStatusAction;

class CommentStatusHandler
{
    #[TransitionCommentStatusAction]
    public function onStatusChange(string $newStatus, string $oldStatus, \WP_Comment $comment): void
    {
        // 承認時にコメント投稿者に通知
        if ($newStatus === 'approved' && $oldStatus !== 'approved') {
            wp_mail(
                $comment->comment_author_email,
                __('Your comment has been approved', 'wppack'),
                sprintf(
                    __('Your comment on "%s" has been approved and is now visible.', 'wppack'),
                    get_the_title($comment->comment_post_ID)
                )
            );
        }

        // スパム判定時にログを記録
        if ($newStatus === 'spam') {
            update_comment_meta($comment->comment_ID, '_marked_spam_at', current_time('mysql'));
            update_comment_meta($comment->comment_ID, '_previous_status', $oldStatus);
        }

        // コメントカウントキャッシュを更新
        clean_post_cache($comment->comment_post_ID);
    }
}
```

### コメント編集フック

#### #[EditCommentAction]

**WordPress フック:** `edit_comment`

管理画面でコメントが編集されたときに実行されるアクションです。編集履歴の追跡などに利用できます。

```php
use WpPack\Component\Hook\Attribute\Comment\EditCommentAction;

class CommentEditTracker
{
    #[EditCommentAction]
    public function onEditComment(int $commentId, array $data): void
    {
        // 編集履歴を記録
        $editHistory = get_comment_meta($commentId, '_edit_history', true) ?: [];
        $editHistory[] = [
            'edited_by' => get_current_user_id(),
            'edited_at' => current_time('mysql'),
        ];
        update_comment_meta($commentId, '_edit_history', $editHistory);

        // 編集回数を更新
        $editCount = count($editHistory);
        update_comment_meta($commentId, '_edit_count', $editCount);
    }
}
```

## クイックリファレンス

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
```
