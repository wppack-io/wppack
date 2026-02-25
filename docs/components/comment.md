# Comment コンポーネント

Comment コンポーネントは、型安全性、モデレーションワークフロー、拡張されたコメント機能を備えた、WordPress コメント管理をモダンなオブジェクト指向で行うためのコンポーネントです。

## このコンポーネントの機能

Comment コンポーネントは以下の機能で WordPress コメント管理を変革します：

- **オブジェクト指向のコメント管理** - 型付きプロパティとメソッド
- **アトリビュートベースのコメント操作** - 宣言的な設定
- **型安全なコメントプロパティ** - 自動メタフィールド処理
- **アトリビュートによるコメントメタ管理** - 構造化データ
- **コメントスレッドと階層** - ネスト会話対応
- **コメントバリデーションとサニタイズ** - データ整合性
- **一括コメント操作** - 効率的な管理

## クイック例

従来の WordPress コメント処理の代わりに：

```php
// 従来の WordPress - 手動のコメント管理
$comment_data = [
    'comment_post_ID' => 123,
    'comment_author' => 'John Doe',
    'comment_author_email' => 'john@example.com',
    'comment_content' => 'Great article!',
    'comment_approved' => 0,
];

$comment_id = wp_insert_comment($comment_data);
if (!$comment_id) {
    error_log('Failed to insert comment');
    return;
}

// メタを手動で更新
update_comment_meta($comment_id, 'rating', 5);
update_comment_meta($comment_id, 'is_verified', true);

// コメントを取得
$comment = get_comment($comment_id);
if ($comment->comment_approved == '1') {
    // 承認されたコメントを処理
}

// コメントを手動でクエリ
$comments = get_comments([
    'post_id' => 123,
    'status' => 'approve',
    'meta_query' => [
        [
            'key' => 'rating',
            'value' => 4,
            'compare' => '>='
        ]
    ]
]);
```

モダンな WpPack アプローチを使用します：

```php
use WpPack\Component\Comment\AbstractComment;
use WpPack\Component\Comment\Attribute\Comment;
use WpPack\Component\Comment\Attribute\CommentMeta;

#[Comment(
    type: 'review',
    requiresApproval: true,
    allowReplies: true
)]
class ProductReview extends AbstractComment
{
    #[CommentMeta('rating', type: 'integer', min: 1, max: 5)]
    public int $rating;

    #[CommentMeta('is_verified', type: 'boolean')]
    public bool $isVerified = false;

    #[CommentMeta('helpful_votes', type: 'integer')]
    public int $helpfulVotes = 0;

    public function validate(): array
    {
        $errors = [];

        if (empty($this->content)) {
            $errors[] = 'Review content is required';
        }

        if ($this->rating < 1 || $this->rating > 5) {
            $errors[] = 'Rating must be between 1 and 5';
        }

        return $errors;
    }

    public function approve(): void
    {
        if ($this->isApproved()) {
            return;
        }

        $this->setStatus('approved');
        $this->save();
    }

    public function markHelpful(): void
    {
        $this->helpfulVotes++;
        $this->save();
    }

    public function isHighRated(): bool
    {
        return $this->rating >= 4;
    }
}

// リポジトリでの使用
$commentRepository = $container->get(CommentRepository::class);

// レビューを作成して保存
$review = new ProductReview();
$review->postId = 123;
$review->authorName = 'John Doe';
$review->authorEmail = 'john@example.com';
$review->content = 'Excellent product! Highly recommend.';
$review->rating = 5;

$errors = $review->validate();
if (empty($errors)) {
    $commentRepository->save($review);
}

// Fluent API でレビューをクエリ
$highRatedReviews = $commentRepository
    ->ofType(ProductReview::class)
    ->wherePostId(123)
    ->whereMeta('rating', '>=', 4)
    ->whereStatus('approved')
    ->orderBy('created', 'desc')
    ->get();
```

## インストール

```bash
composer require wppack/comment
```

## コア機能

### 型付きコメントクラス

完全な型安全性と自動メタ処理でコメントタイプを定義します：

```php
#[Comment(type: 'testimonial')]
class Testimonial extends AbstractComment
{
    #[CommentMeta('company', required: true)]
    public string $company;

    #[CommentMeta('position')]
    public string $position;

    #[CommentMeta('rating', type: 'integer', min: 1, max: 10)]
    public int $rating = 10;

    #[CommentMeta('featured', type: 'boolean')]
    public bool $featured = false;

    #[CommentMeta('image_url')]
    public ?string $imageUrl = null;

    public function markAsFeatured(): void
    {
        $this->featured = true;
        $this->save();
    }

    public function getAuthorInfo(): string
    {
        $info = $this->authorName;

        if ($this->position) {
            $info .= ', ' . $this->position;
        }

        if ($this->company) {
            $info .= ' at ' . $this->company;
        }

        return $info;
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->content)) {
            $errors[] = 'Testimonial content is required';
        }

        if (empty($this->company)) {
            $errors[] = 'Company name is required';
        }

        if (strlen($this->content) < 20) {
            $errors[] = 'Testimonial must be at least 20 characters';
        }

        return $errors;
    }
}
```

### 高度なコメントスレッド

ネストされたコメント会話をスレッドサポートで処理します：

```php
#[Comment(allowReplies: true, maxDepth: 3)]
class ThreadedComment extends AbstractComment
{
    #[CommentMeta('thread_depth', type: 'integer')]
    public int $threadDepth = 0;

    #[CommentMeta('reply_count', type: 'integer')]
    public int $replyCount = 0;

    public function getReplies(): array
    {
        return $this->comments
            ->whereParentId($this->getId())
            ->whereStatus('approved')
            ->orderBy('created', 'asc')
            ->get();
    }

    public function getParent(): ?ThreadedComment
    {
        if (!$this->parentId) {
            return null;
        }

        return $this->comments->find($this->parentId, ThreadedComment::class);
    }

    public function addReply(ThreadedComment $reply): void
    {
        $reply->parentId = $this->getId();
        $reply->threadDepth = $this->threadDepth + 1;

        if ($reply->threadDepth > 3) {
            throw new Exception('Maximum thread depth exceeded');
        }

        $this->comments->save($reply);

        // 返信数を更新
        $this->replyCount++;
        $this->save();
    }

    public function canReply(): bool
    {
        return $this->threadDepth < 3 && $this->isApproved();
    }
}
```

### コメントリポジトリとクエリ

コメント管理のための強力なリポジトリパターン：

```php
class CommentService
{
    public function __construct(
        private CommentRepository $comments
    ) {}

    public function createComment(int $postId, array $data): AbstractComment
    {
        $comment = new Comment();
        $comment->postId = $postId;
        $comment->authorName = $data['author_name'];
        $comment->authorEmail = $data['author_email'];
        $comment->content = $data['content'];

        // コメントをバリデーション
        $errors = $comment->validate();
        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $this->comments->save($comment);

        return $comment;
    }

    public function getApprovedComments(int $postId): array
    {
        return $this->comments
            ->wherePostId($postId)
            ->whereStatus('approved')
            ->orderBy('created', 'asc')
            ->get();
    }

    public function bulkApprove(array $commentIds): int
    {
        $approved = 0;

        foreach ($commentIds as $id) {
            $comment = $this->comments->find($id);

            if ($comment && $comment->isPending()) {
                $comment->approve();
                $approved++;
            }
        }

        return $approved;
    }
}
```

## クイックスタート

### 1. 基本的なコメントクラスの作成

```php
use WpPack\Component\Comment\AbstractComment;
use WpPack\Component\Comment\Attribute\Comment;
use WpPack\Component\Comment\Attribute\CommentMeta;

#[Comment(type: 'product_review')]
class ProductReview extends AbstractComment
{
    #[CommentMeta('rating', type: 'integer', min: 1, max: 5)]
    public int $rating = 5;

    #[CommentMeta('recommend', type: 'boolean')]
    public bool $recommend = true;

    #[CommentMeta('verified_purchase', type: 'boolean')]
    public bool $verifiedPurchase = false;

    #[CommentMeta('helpful_count', type: 'integer')]
    public int $helpfulCount = 0;

    public function validate(): array
    {
        $errors = [];

        if (empty($this->content)) {
            $errors[] = 'Review content is required';
        }

        if (strlen($this->content) < 10) {
            $errors[] = 'Review must be at least 10 characters long';
        }

        if ($this->rating < 1 || $this->rating > 5) {
            $errors[] = 'Rating must be between 1 and 5 stars';
        }

        return $errors;
    }

    public function markHelpful(): void
    {
        $this->helpfulCount++;
        $this->save();
    }

    public function isHighRated(): bool
    {
        return $this->rating >= 4;
    }

    public function getStarDisplay(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }
}
```

### 2. コメントサービスの作成

```php
class ProductReviewService
{
    public function __construct(
        private CommentRepository $comments
    ) {}

    public function createReview(int $productId, array $data): ProductReview
    {
        $review = new ProductReview();
        $review->postId = $productId;
        $review->authorName = $data['author_name'];
        $review->authorEmail = $data['author_email'];
        $review->content = $data['content'];
        $review->rating = (int) $data['rating'];
        $review->recommend = isset($data['recommend']) && $data['recommend'];
        $review->verifiedPurchase = $this->isVerifiedPurchase($productId, $data['author_email']);

        // レビューをバリデーション
        $errors = $review->validate();
        if (!empty($errors)) {
            throw new ValidationException('Review validation failed', $errors);
        }

        // レビューを保存
        $this->comments->save($review);

        return $review;
    }

    public function getProductReviews(int $productId, string $status = 'approved'): array
    {
        return $this->comments
            ->ofType(ProductReview::class)
            ->wherePostId($productId)
            ->whereStatus($status)
            ->orderBy('created', 'desc')
            ->get();
    }

    public function getAverageRating(int $productId): float
    {
        $reviews = $this->comments
            ->ofType(ProductReview::class)
            ->wherePostId($productId)
            ->whereStatus('approved')
            ->get();

        if (empty($reviews)) {
            return 0.0;
        }

        $totalRating = array_sum(array_map(fn($review) => $review->rating, $reviews));
        return round($totalRating / count($reviews), 1);
    }

    private function isVerifiedPurchase(int $productId, string $email): bool
    {
        // ユーザーがこの商品を購入済みかチェック
        $orders = wc_get_orders([
            'billing_email' => $email,
            'status' => 'completed',
            'limit' => -1
        ]);

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $productId) {
                    return true;
                }
            }
        }

        return false;
    }
}
```

### 3. WordPress との統合

```php
class ReviewFormHandler
{
    public function __construct(
        private ProductReviewService $reviewService
    ) {}

    #[Action('wp_ajax_submit_review')]
    #[Action('wp_ajax_nopriv_submit_review')]
    public function onWpAjaxSubmitReview(): void
    {
        // nonce を検証
        if (!wp_verify_nonce($_POST['_wpnonce'], 'submit_review')) {
            wp_send_json_error('Security check failed');
        }

        $productId = intval($_POST['product_id']);
        $data = [
            'author_name' => sanitize_text_field($_POST['author_name']),
            'author_email' => sanitize_email($_POST['author_email']),
            'content' => wp_kses_post($_POST['content']),
            'rating' => intval($_POST['rating']),
            'recommend' => isset($_POST['recommend'])
        ];

        try {
            $review = $this->reviewService->createReview($productId, $data);

            wp_send_json_success([
                'message' => 'Review submitted successfully!',
                'review_id' => $review->getId(),
                'status' => $review->getStatus()
            ]);

        } catch (ValidationException $e) {
            wp_send_json_error([
                'message' => 'Please correct the errors below:',
                'errors' => $e->getErrors()
            ]);
        } catch (Exception $e) {
            wp_send_json_error('An error occurred while submitting your review.');
        }
    }
}
```

### 4. フロントエンドレビューフォーム

```html
<form id="review-form" method="post">
    <?php wp_nonce_field('submit_review'); ?>
    <input type="hidden" name="action" value="submit_review">
    <input type="hidden" name="product_id" value="<?php echo get_the_ID(); ?>">

    <div class="form-group">
        <label for="author_name">Your Name *</label>
        <input type="text" id="author_name" name="author_name" required>
    </div>

    <div class="form-group">
        <label for="author_email">Your Email *</label>
        <input type="email" id="author_email" name="author_email" required>
    </div>

    <div class="form-group">
        <label for="rating">Rating *</label>
        <div class="star-rating">
            <input type="radio" id="star5" name="rating" value="5">
            <label for="star5">★</label>
            <input type="radio" id="star4" name="rating" value="4">
            <label for="star4">★</label>
            <input type="radio" id="star3" name="rating" value="3">
            <label for="star3">★</label>
            <input type="radio" id="star2" name="rating" value="2">
            <label for="star2">★</label>
            <input type="radio" id="star1" name="rating" value="1">
            <label for="star1">★</label>
        </div>
    </div>

    <div class="form-group">
        <label for="content">Your Review *</label>
        <textarea id="content" name="content" rows="4" required></textarea>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="recommend" value="1">
            I would recommend this product
        </label>
    </div>

    <button type="submit">Submit Review</button>
</form>

<script>
document.getElementById('review-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const response = await fetch(ajaxurl, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert(result.data.message);
            this.reset();
            location.reload();
        } else {
            if (result.data.errors) {
                let errorMsg = result.data.message + '\n';
                result.data.errors.forEach(error => errorMsg += '- ' + error + '\n');
                alert(errorMsg);
            } else {
                alert(result.data);
            }
        }
    } catch (error) {
        alert('Network error. Please try again.');
    }
});
</script>
```

## コメントスレッドの例

### 1. スレッドコメントクラスの作成

```php
#[Comment(allowReplies: true, maxDepth: 3)]
class ThreadedComment extends AbstractComment
{
    #[CommentMeta('thread_depth', type: 'integer')]
    public int $threadDepth = 0;

    #[CommentMeta('reply_count', type: 'integer')]
    public int $replyCount = 0;

    public function addReply(ThreadedComment $reply): void
    {
        if ($this->threadDepth >= 3) {
            throw new Exception('Maximum thread depth exceeded');
        }

        $reply->parentId = $this->getId();
        $reply->threadDepth = $this->threadDepth + 1;

        $this->comments->save($reply);

        // 返信数を更新
        $this->replyCount++;
        $this->save();
    }

    public function getReplies(): array
    {
        return $this->comments
            ->whereParentId($this->getId())
            ->whereStatus('approved')
            ->orderBy('created', 'asc')
            ->get();
    }

    public function canReply(): bool
    {
        return $this->threadDepth < 3 && $this->isApproved();
    }

    public function getThread(): array
    {
        $thread = [];
        $current = $this;

        // すべての祖先を取得
        while ($current->parentId) {
            $parent = $this->comments->find($current->parentId, ThreadedComment::class);
            if (!$parent) break;

            array_unshift($thread, $parent);
            $current = $parent;
        }

        // 現在のコメントを追加
        $thread[] = $this;

        return $thread;
    }
}
```

### 2. スレッドコメントの表示

```php
function display_threaded_comments($postId) {
    $commentService = $container->get(CommentService::class);
    $comments = $commentService->getApprovedComments($postId);

    // 親でグループ化
    $parentComments = array_filter($comments, fn($c) => $c->parentId === 0);
    $childComments = array_filter($comments, fn($c) => $c->parentId > 0);

    // 子を親でグループ化
    $grouped = [];
    foreach ($childComments as $child) {
        $grouped[$child->parentId][] = $child;
    }

    echo '<div class="comments-list">';
    foreach ($parentComments as $comment) {
        display_comment($comment, $grouped);
    }
    echo '</div>';
}

function display_comment($comment, $grouped, $depth = 0) {
    $indent = str_repeat('  ', $depth);

    echo "<div class='comment depth-{$depth}' style='margin-left: " . ($depth * 20) . "px;'>";
    echo "<div class='comment-content'>";
    echo "<strong>" . esc_html($comment->authorName) . "</strong>";
    echo "<p>" . esc_html($comment->content) . "</p>";

    if ($comment->canReply()) {
        echo "<button onclick='showReplyForm({$comment->getId()})'>Reply</button>";
    }

    echo "</div>";

    // 返信を表示
    if (isset($grouped[$comment->getId()])) {
        foreach ($grouped[$comment->getId()] as $reply) {
            display_comment($reply, $grouped, $depth + 1);
        }
    }

    echo "</div>";
}
```

## 高度な機能

### 一括操作

コメント管理のための効率的な一括操作：

```php
class BulkCommentOperations
{
    public function bulkApprove(array $commentIds): array
    {
        $results = [];

        foreach ($commentIds as $id) {
            try {
                $comment = $this->comments->find($id);

                if ($comment && $comment->isPending()) {
                    $comment->approve();
                    $results[$id] = ['success' => true];
                } else {
                    $results[$id] = ['success' => false, 'reason' => 'Not pending'];
                }
            } catch (Exception $e) {
                $results[$id] = ['success' => false, 'reason' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function deleteSpamComments(int $olderThanDays = 30): int
    {
        $cutoffDate = (new DateTime())->sub(new DateInterval("P{$olderThanDays}D"));

        $spamComments = $this->comments
            ->whereStatus('spam')
            ->whereDate('created', '<', $cutoffDate->format('Y-m-d'))
            ->get();

        $deletedCount = 0;

        foreach ($spamComments as $comment) {
            try {
                $this->comments->delete($comment, true);
                $deletedCount++;
            } catch (Exception $e) {
                error_log("Failed to delete spam comment: " . $e->getMessage());
            }
        }

        return $deletedCount;
    }
}
```

## コメントのテスト

### ユニットテスト

```php
use WpPack\Component\Comment\Testing\CommentTestCase;

class ProductReviewTest extends CommentTestCase
{
    public function testCreateReview(): void
    {
        $review = new ProductReview();
        $review->postId = 123;
        $review->authorName = 'John Doe';
        $review->authorEmail = 'john@example.com';
        $review->content = 'Great product! Highly recommend.';
        $review->rating = 5;
        $review->recommend = true;

        $errors = $review->validate();
        $this->assertEmpty($errors);

        $this->assertTrue($review->isHighRated());
        $this->assertEquals('★★★★★☆☆☆☆☆', $review->getStarDisplay());
    }

    public function testReviewValidation(): void
    {
        $review = new ProductReview();
        $review->content = 'Short'; // 短すぎる
        $review->rating = 6; // 無効な評価

        $errors = $review->validate();

        $this->assertNotEmpty($errors);
        $this->assertContains('Review must be at least 10 characters long', $errors);
        $this->assertContains('Rating must be between 1 and 5 stars', $errors);
    }

    public function testHelpfulVotes(): void
    {
        $review = $this->createComment(ProductReview::class);
        $initialCount = $review->helpfulCount;

        $review->markHelpful();

        $this->assertEquals($initialCount + 1, $review->helpfulCount);
    }
}

class ThreadedCommentTest extends CommentTestCase
{
    public function testAddReply(): void
    {
        $parent = $this->createComment(ThreadedComment::class);
        $reply = new ThreadedComment();
        $reply->content = 'This is a reply';
        $reply->authorName = 'Replier';
        $reply->authorEmail = 'reply@example.com';

        $parent->addReply($reply);

        $this->assertEquals($parent->getId(), $reply->parentId);
        $this->assertEquals(1, $reply->threadDepth);
        $this->assertEquals(1, $parent->replyCount);
    }

    public function testMaxDepthRestriction(): void
    {
        $parent = $this->createComment(ThreadedComment::class);
        $parent->threadDepth = 3;

        $reply = new ThreadedComment();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Maximum thread depth exceeded');

        $parent->addReply($reply);
    }
}
```

## このコンポーネントの使用場面

**最適な用途：**
- 高度なコメント管理が必要な WordPress サイト
- 商品レビューと評価を持つ EC サイト
- カスタムコメントタイプ（レビュー、テスティモニアルなど）を持つプロジェクト
- コメントスレッドと階層が必要なシステム

**代替を検討すべき場合：**
- 基本的なコメント機能で十分なシンプルな WordPress サイト
- WordPress デフォルトのコメント機能のみを使用するサイト
- カスタムコメント要件のないプロジェクト

## パフォーマンス機能

### 効率的なクエリ

スマートキャッシュを備えた最適化されたデータベースクエリ：

```php
// 効率的なコメントクエリ
$comments = $this->comments
    ->wherePostId($postId)
    ->whereStatus('approved')
    ->with(['meta', 'replies'])
    ->cache(3600)
    ->get();
```

## 依存関係

### 必須
- **なし** - WordPress 組み込みのコメント関数で独立して動作

### 推奨
- **EventDispatcher コンポーネント** - コメントライフサイクルイベント用
- **Mailer コンポーネント** - コメント通知用
- **Cache コンポーネント** - コメントデータキャッシュ用

## クイックリファレンス

### 基本的なコメント構造

```php
#[Comment(type: 'my_comment')]
class MyComment extends AbstractComment
{
    #[CommentMeta('field_name', type: 'string')]
    public string $fieldName;

    public function validate(): array
    {
        // バリデーションロジック
        return [];
    }
}
```

### コメントリポジトリメソッド

```php
$comments = $repository
    ->ofType(MyComment::class)
    ->wherePostId($postId)
    ->whereStatus('approved')
    ->whereMeta('field', 'value')
    ->orderBy('created', 'desc')
    ->get();
```

### コメントメタタイプ

```php
#[CommentMeta('string_field', type: 'string')]
#[CommentMeta('int_field', type: 'integer', min: 1, max: 10)]
#[CommentMeta('bool_field', type: 'boolean')]
#[CommentMeta('array_field', type: 'array')]
```
