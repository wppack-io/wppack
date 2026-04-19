# Console コンポーネント

**パッケージ:** `wppack/console`
**名前空間:** `WPPack\Component\Console\`
**レイヤー:** Feature

Console コンポーネントは、Symfony Console に倣った `configure()` + `execute()` パターンで WP-CLI コマンドを型安全に作成するためのフレームワークです。DI コンテナとの統合により、`#[AsCommand]` アトリビュートを付けたコマンドクラスが自動的に検出・登録されます。

## インストール

```bash
composer require wppack/console
```

## このコンポーネントの機能

- **`configure()` + `execute()` パターン** — 引数/オプション定義と実行ロジックを明確に分離
- **型安全な入出力** — `InputInterface` / `OutputStyle` による型付きアクセス
- **WP-CLI synopsis 自動生成** — `InputDefinition` から `wp help` 用の定義を自動構築
- **DI 自動登録** — `#[AsCommand]` + `RegisterCommandsPass` で追加設定不要
- **テスト容易性** — `ArrayInput` + `BufferedOutput` でコマンドのユニットテストが可能
- **リッチ出力** — success/error/warning/table/progress 等の出力ヘルパー

## 基本コンセプト

### Before（従来の WordPress）

```php
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('myplugin import-users', function ($args, $assoc_args) {
        $file = $args[0] ?? WP_CLI::error('Please provide a CSV file path.');
        $role = $assoc_args['role'] ?? 'subscriber';

        WP_CLI::log('Starting user import...');
        // ... import logic ...
        WP_CLI::success("Imported users.");
    });
}
```

### After（WPPack）

```php
use WPPack\Component\Console\AbstractCommand;
use WPPack\Component\Console\Attribute\AsCommand;
use WPPack\Component\Console\Input\InputArgument;
use WPPack\Component\Console\Input\InputDefinition;
use WPPack\Component\Console\Input\InputInterface;
use WPPack\Component\Console\Input\InputOption;
use WPPack\Component\Console\Output\OutputStyle;

#[AsCommand(name: 'myplugin import-users', description: 'Import users from CSV')]
final class ImportUsersCommand extends AbstractCommand
{
    public function __construct(
        private readonly UserRepository $userRepo,
    ) {}

    protected function configure(InputDefinition $definition): void
    {
        $definition
            ->addArgument(new InputArgument('file', InputArgument::REQUIRED, 'CSV file path'))
            ->addOption(new InputOption('role', InputOption::VALUE_OPTIONAL, 'Default role', 'subscriber'))
            ->addOption(new InputOption('skip-email', InputOption::VALUE_NONE, 'Skip welcome emails'));
    }

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        $file = $input->getArgument('file');
        $output->info("Importing from {$file}...");

        $rows = $this->parseCsv($file);
        $progress = $output->progress(count($rows));

        foreach ($rows as $row) {
            $this->userRepo->create($row, $input->getOption('role'));
            $progress->advance();
        }

        $progress->finish();
        $output->success(sprintf('Imported %d users.', count($rows)));
        return self::SUCCESS;
    }
}
```

## クイックスタート

### コマンドの作成

1. `AbstractCommand` を継承し、`#[AsCommand]` アトリビュートを付ける
2. `configure()` で引数/オプションを定義する
3. `execute()` にロジックを実装する

```php
use WPPack\Component\Console\AbstractCommand;
use WPPack\Component\Console\Attribute\AsCommand;
use WPPack\Component\Console\Input\InputArgument;
use WPPack\Component\Console\Input\InputDefinition;
use WPPack\Component\Console\Input\InputInterface;
use WPPack\Component\Console\Input\InputOption;
use WPPack\Component\Console\Output\OutputStyle;

#[AsCommand(name: 'myapp generate-report', description: 'Generate a site report')]
final class GenerateReportCommand extends AbstractCommand
{
    protected function configure(InputDefinition $definition): void
    {
        $definition
            ->addArgument(new InputArgument('type', InputArgument::REQUIRED, 'Report type'))
            ->addOption(new InputOption('format', InputOption::VALUE_OPTIONAL, 'Output format', 'table'))
            ->addOption(new InputOption('include-drafts', InputOption::VALUE_NONE, 'Include draft posts'));
    }

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        $type = $input->getArgument('type');
        $format = $input->getOption('format');
        $includeDrafts = $input->getOption('include-drafts');

        $output->info("Generating {$type} report...");

        // Build report data
        $headers = ['ID', 'Title', 'Status'];
        $rows = [
            ['1', 'Hello World', 'publish'],
            ['2', 'Draft Post', 'draft'],
        ];

        match ($format) {
            'table' => $output->table($headers, $rows),
            'json' => $output->line(json_encode(['headers' => $headers, 'rows' => $rows], JSON_PRETTY_PRINT)),
            default => $output->error("Unknown format: {$format}"),
        };

        $output->success('Report generated successfully!');
        return self::SUCCESS;
    }
}
```

### 引数の定義

位置引数は `InputArgument` で定義します:

```php
// 必須引数
$definition->addArgument(new InputArgument('file', InputArgument::REQUIRED, 'CSV file path'));

// オプション引数（デフォルト値付き）
$definition->addArgument(new InputArgument('format', InputArgument::OPTIONAL, 'Output format', 'json'));

// 配列引数（残りの引数をすべて受け取る）
$definition->addArgument(new InputArgument('files', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Files'));
```

### オプションの定義

名前付きオプションは `InputOption` で定義します:

```php
// フラグ（--verbose）
$definition->addOption(new InputOption('verbose', InputOption::VALUE_NONE, 'Verbose output'));

// 値必須（--format=csv）
$definition->addOption(new InputOption('format', InputOption::VALUE_REQUIRED, 'Output format'));

// 値オプション（--role[=editor]）
$definition->addOption(new InputOption('role', InputOption::VALUE_OPTIONAL, 'User role', 'subscriber'));
```

### 終了コード

```php
return self::SUCCESS; // 0 — 正常終了
return self::FAILURE; // 1 — 一般的なエラー
return self::INVALID; // 2 — 無効な入力
```

## 出力メソッド

`OutputStyle` はリッチな出力ヘルパーを提供します。WP-CLI 環境では `WP_CLI::success()` 等に委譲し、テスト環境ではバッファに書き込みます。

```php
$output->info('Informational message');     // [INFO] テキスト
$output->success('Success message');         // [SUCCESS] テキスト
$output->warning('Warning message');         // [WARNING] テキスト
$output->error('Error message');             // [ERROR] テキスト（WP-CLI時はプロセス終了）
$output->line('Plain text');                 // フォーマットなし
$output->newLine();                          // 空行
```

### テーブル

```php
$output->table(
    ['ID', 'Name', 'Email'],
    [
        ['1', 'Alice', 'alice@example.com'],
        ['2', 'Bob', 'bob@example.com'],
    ],
);
```

### プログレスバー

```php
$progress = $output->progress(count($items), 'Importing');

foreach ($items as $item) {
    $this->process($item);
    $progress->advance();
}

$progress->finish();
```

### 対話的入力

```php
$confirmed = $output->confirm('Continue?', true);
$name = $output->ask('Enter name:', 'default');
```

## DI 統合

### 自動登録（推奨）

`#[AsCommand]` アトリビュートを付けたコマンドは、`RegisterCommandsPass` が自動的に検出し `CommandRegistry` に登録します。

```php
// ServiceProvider で CommandRegistry を登録
$builder->register(CommandRegistry::class);

// コマンドを DI コンテナに登録
$builder->register(ImportUsersCommand::class)->autowire();

// CompilerPass を追加
$builder->addCompilerPass(new RegisterCommandsPass());
```

### タグベースの登録

アトリビュートの代わりにタグを使うこともできます:

```php
$builder->register(ImportUsersCommand::class)
    ->addTag('console.command');
```

### 手動登録

DI を使わない場合は直接 `CommandRegistry` を利用できます:

```php
$registry = new CommandRegistry();
$registry->add(new ImportUsersCommand($userRepo));
$registry->register(); // WP_CLI::add_command() を一括呼び出し
```

## テスト

`ArrayInput` と `BufferedOutput` を使い、WP-CLI なしでコマンドをテストできます:

```php
use WPPack\Component\Console\AbstractCommand;
use WPPack\Component\Console\Input\ArrayInput;
use WPPack\Component\Console\Input\InputArgument;
use WPPack\Component\Console\Input\InputDefinition;
use WPPack\Component\Console\Input\InputInterface;
use WPPack\Component\Console\Output\BufferedOutput;
use WPPack\Component\Console\Output\OutputStyle;

#[Test]
public function greetingCommandOutputsMessage(): void
{
    $command = new #[AsCommand(name: 'test greet', description: 'Greet')] class extends AbstractCommand {
        protected function configure(InputDefinition $definition): void
        {
            $definition->addArgument(new InputArgument('name', InputArgument::REQUIRED, 'Name'));
        }

        protected function execute(InputInterface $input, OutputStyle $output): int
        {
            $output->success('Hello, ' . $input->getArgument('name') . '!');
            return self::SUCCESS;
        }
    };

    $input = new ArrayInput(arguments: ['name' => 'World']);
    $buffer = new BufferedOutput();
    $output = new OutputStyle($buffer);

    $exitCode = $command->run($input, $output);

    self::assertSame(AbstractCommand::SUCCESS, $exitCode);
    self::assertStringContainsString('Hello, World!', $buffer->getBuffer());
}
```

## アーキテクチャ

```
AsCommand attribute → RegisterCommandsPass auto-detect → CommandRegistry.add()
                                                                  ↓
                                                             register()
                                                                  ↓
                                                      WP_CLI::add_command()
                                                                  ↓
                                                      CommandRunner(__invoke)
                                                                  ↓
                                                WpCliInput + OutputStyle を構築
                                                                  ↓
                                                    AbstractCommand::execute()
```

### WP-CLI 互換性

- **加算的な統合**: `CommandRegistry::register()` は `WP_CLI::add_command()` を内部で呼ぶだけ。WP-CLI の仕組みを置き換えない
- **既存コマンドとの共存**: 従来の `WP_CLI::add_command()` で直接登録したコマンドはそのまま動く
- **`wp help` 対応**: `InputDefinition::toSynopsis()` が WP-CLI の synopsis フォーマットを生成
- **WP-CLI 非存在時**: `CommandRegistry::register()` は `class_exists('WP_CLI')` でガードし、エラーにならない

## 使い方

```bash
# コマンドの実行
wp myplugin import-users /path/to/users.csv --skip-email --role=editor
wp myapp generate-report content --format=table --include-drafts
```

## このコンポーネントの使用場面

**最適な用途:**
- データのインポート/エクスポート操作
- データベースメンテナンスタスク
- デプロイ自動化
- コンテンツマイグレーション
- バッチ処理
- サイトセットアップと設定

**代替を検討すべき場合:**
- シンプルなワンオフスクリプト → 直接 `WP_CLI::add_command()` で十分
- 管理画面 UI の方が適したタスク → Admin コンポーネント

## 依存関係

### 必須
- **WP-CLI** — WordPress コマンドラインインターフェース（実行時のみ。テスト時は不要）

### 推奨
- **DependencyInjection コンポーネント** — コマンドの自動検出・登録
