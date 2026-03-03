# Command コンポーネント

**パッケージ:** `wppack/command`
**名前空間:** `WpPack\Component\Command\`
**レイヤー:** Feature

Command コンポーネントは、型安全性と整理されたコード構成を備えた、WP-CLI コマンドをモダンなアトリビュートベースで作成するためのコンポーネントです。

## インストール

```bash
composer require wppack/command
```

## このコンポーネントの機能

- **アトリビュートベースのコマンド定義** - クリーンで宣言的な構文
- **型安全な引数とオプション** - 自動パースとバリデーション
- **自動ヘルプ生成** - コマンドアトリビュートと docblock から生成
- **プログレスバーとテーブル** - より良いユーザー体験
- **出力フォーマット** - 一貫したスタイリングメソッド
- **テストユーティリティ** - 信頼性のあるコマンドテスト
- **コマンドの自動検出と登録** - 自動セットアップ

## 基本コンセプト

### Before（従来の WordPress）

```php
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('myplugin import-users', 'myplugin_import_users_command');
}

function myplugin_import_users_command($args, $assoc_args) {
    if (empty($args[0])) {
        WP_CLI::error('Please provide a CSV file path.');
    }

    $file = $args[0];
    $skip_email = isset($assoc_args['skip-email']) ? true : false;
    $role = isset($assoc_args['role']) ? $assoc_args['role'] : 'subscriber';

    if (!file_exists($file)) {
        WP_CLI::error("File not found: $file");
    }

    WP_CLI::log('Starting user import...');

    $handle = fopen($file, 'r');
    $count = 0;

    while (($data = fgetcsv($handle)) !== false) {
        $count++;
    }

    fclose($handle);
    WP_CLI::success("Imported $count users.");
}
```

### After（WpPack）

```php
use WpPack\Component\Command\AbstractCommand;
use WpPack\Component\Command\Attribute\Command;
use WpPack\Component\Command\Attribute\Argument;
use WpPack\Component\Command\Attribute\Option;

#[Command('myplugin import-users', description: 'Import users from a CSV file')]
class ImportUsersCommand extends AbstractCommand
{
    #[Argument(description: 'Path to the CSV file', required: true)]
    private string $file;

    #[Option(description: 'Skip sending welcome emails')]
    private bool $skipEmail = false;

    #[Option(description: 'Default role for imported users')]
    private string $role = 'subscriber';

    public function handle(): void
    {
        if (!file_exists($this->file)) {
            $this->error("File not found: {$this->file}");
            return;
        }

        $this->info('Starting user import...');

        $rows = $this->parseCsv($this->file);
        $progress = $this->progress(count($rows));

        foreach ($rows as $row) {
            $this->importUser($row);
            $progress->advance();
        }

        $progress->finish();
        $this->success("Imported " . count($rows) . " users.");
    }
}
```

## クイックスタート

### コマンドの作成

```php
use WpPack\Component\Command\AbstractCommand;
use WpPack\Component\Command\Attribute\Command;
use WpPack\Component\Command\Attribute\Argument;
use WpPack\Component\Command\Attribute\Option;

#[Command('myapp generate-report', description: 'Generate a site report')]
class GenerateReportCommand extends AbstractCommand
{
    #[Argument(description: 'Report type', required: true)]
    private string $type;

    #[Option(description: 'Output format', default: 'table')]
    private string $format;

    #[Option(description: 'Include draft posts')]
    private bool $includeDrafts = false;

    #[Option(description: 'Date range start')]
    private ?string $from = null;

    #[Option(description: 'Date range end')]
    private ?string $to = null;

    public function handle(): void
    {
        $this->info("Generating {$this->type} report...");

        $data = match ($this->type) {
            'content' => $this->getContentReport(),
            'users' => $this->getUserReport(),
            'performance' => $this->getPerformanceReport(),
            default => $this->error("Unknown report type: {$this->type}"),
        };

        match ($this->format) {
            'table' => $this->table($data['headers'], $data['rows']),
            'csv' => $this->outputCsv($data),
            'json' => $this->line(json_encode($data, JSON_PRETTY_PRINT)),
            default => $this->error("Unknown format: {$this->format}"),
        };

        $this->success('Report generated successfully!');
    }

    private function getContentReport(): array
    {
        $statuses = $this->includeDrafts
            ? ['publish', 'draft']
            : ['publish'];

        $posts = get_posts([
            'post_status' => $statuses,
            'posts_per_page' => -1,
            'date_query' => array_filter([
                'after' => $this->from,
                'before' => $this->to,
            ]),
        ]);

        return [
            'headers' => ['ID', 'Title', 'Status', 'Author', 'Date'],
            'rows' => array_map(fn ($post) => [
                $post->ID,
                $post->post_title,
                $post->post_status,
                get_the_author_meta('display_name', $post->post_author),
                $post->post_date,
            ], $posts),
        ];
    }
}
```

### プログレスバーと長時間タスク

```php
#[Command('myapp process-images', description: 'Process and optimize all images')]
class ProcessImagesCommand extends AbstractCommand
{
    #[Option(description: 'Maximum width in pixels')]
    private int $maxWidth = 1920;

    #[Option(description: 'JPEG quality (1-100)')]
    private int $quality = 85;

    #[Option(description: 'Dry run without making changes')]
    private bool $dryRun = false;

    public function handle(): void
    {
        $images = $this->getUnprocessedImages();

        if (empty($images)) {
            $this->info('No images to process.');
            return;
        }

        $this->info(sprintf('Found %d images to process.', count($images)));

        if ($this->dryRun) {
            $this->warning('DRY RUN - No changes will be made.');
        }

        $progress = $this->progress(count($images));
        $processed = 0;
        $errors = 0;

        foreach ($images as $image) {
            try {
                if (!$this->dryRun) {
                    $this->processImage($image);
                }
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $this->warning("Failed: {$image->post_title} - {$e->getMessage()}");
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();

        $this->success("Processed: {$processed}, Errors: {$errors}");
    }
}
```

### データベース管理コマンド

```php
#[Command('myapp db:cleanup', description: 'Clean up database tables')]
class DatabaseCleanupCommand extends AbstractCommand
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    #[Option(description: 'Clean post revisions')]
    private bool $revisions = true;

    #[Option(description: 'Clean spam comments')]
    private bool $spam = true;

    #[Option(description: 'Clean expired transients')]
    private bool $transients = true;

    #[Option(description: 'Optimize tables after cleanup')]
    private bool $optimize = false;

    public function handle(): void
    {
        $this->info('Starting database cleanup...');
        $results = [];

        if ($this->revisions) {
            $count = $this->db->query(
                "DELETE FROM {$this->db->posts} WHERE post_type = 'revision'"
            );
            $results[] = ['Post Revisions', $count];
        }

        if ($this->spam) {
            $count = $this->db->query(
                "DELETE FROM {$this->db->comments} WHERE comment_approved = 'spam'"
            );
            $results[] = ['Spam Comments', $count];
        }

        if ($this->transients) {
            $count = $this->db->query(
                "DELETE FROM {$this->db->options}
                 WHERE option_name LIKE '_transient_timeout_%'
                 AND option_value < UNIX_TIMESTAMP()"
            );
            $results[] = ['Expired Transients', $count];
        }

        $this->table(['Type', 'Deleted'], $results);

        if ($this->optimize) {
            $this->info('Optimizing tables...');
            $this->db->query("OPTIMIZE TABLE {$this->db->posts}, {$this->db->comments}, {$this->db->options}");
            $this->success('Tables optimized.');
        }

        $this->success('Database cleanup completed!');
    }
}
```

## コマンドの登録

```php
add_action('cli_init', function () {
    $container = new WpPack\Container();
    $container->register([
        ImportUsersCommand::class,
        GenerateReportCommand::class,
        ProcessImagesCommand::class,
        DatabaseCleanupCommand::class,
    ]);
});
```

## 使い方

```bash
# コマンドの実行
wp myplugin import-users /path/to/users.csv --skip-email --role=editor
wp myapp generate-report content --format=table --include-drafts
wp myapp process-images --max-width=1200 --quality=90 --dry-run
wp myapp db:cleanup --revisions --spam --transients --optimize
```

## 出力メソッド

```php
$this->info('Informational message');     // 青色テキスト
$this->success('Success message');         // 緑色テキスト
$this->warning('Warning message');         // 黄色テキスト
$this->error('Error message');             // 赤色テキスト、コマンド終了
$this->line('Plain text');                 // フォーマットなし
$this->newLine();                          // 空行

// テーブル
$this->table(['Header1', 'Header2'], [['row1a', 'row1b'], ['row2a', 'row2b']]);

// プログレスバー
$progress = $this->progress($total);
$progress->advance();
$progress->finish();

// 確認プロンプト
$confirmed = $this->confirm('Continue?', true);
```

## このコンポーネントの使用場面

**最適な用途：**
- データのインポート/エクスポート操作
- データベースメンテナンスタスク
- デプロイ自動化
- コンテンツマイグレーション
- バッチ処理
- サイトセットアップと設定

**代替を検討すべき場合：**
- シンプルなワンオフスクリプト
- 管理画面 UI の方が適したタスク

## 依存関係

### 必須
- **WP-CLI** - WordPress コマンドラインインターフェース

### 推奨
- **DependencyInjection コンポーネント** - コマンドへのサービスインジェクション用
- **Logger コンポーネント** - コマンド実行ログ用
