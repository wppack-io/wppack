# WordPress プラグイン脆弱性パターンと WpPack の対策

## はじめに

WordPress プラグインのセキュリティ脆弱性は、WordPress エコシステム全体で最も頻繁に報告されるセキュリティリスクです。Patchstack や WPScan の年次レポートによれば、報告される WordPress 脆弱性の 90% 以上がプラグインに起因しており、XSS・CSRF・認可の不備が上位を占めています。

このガイドでは、WordPress プラグインで頻出する脆弱性パターンを OWASP Top 10 ベースで体系的に整理し、WpPack の各コンポーネントがどのように対処しているかを示します。

**対象読者**: WpPack を利用するプロフェッショナル開発者、セキュリティレビュアー

> **注意**: WpPack のセキュリティコンポーネントは「安全なデフォルト」を提供しますが、すべての脆弱性を自動的に防ぐわけではありません。各セクションの「開発者の責任」を必ず確認してください。

---

## 1. SQL インジェクション

### 概要

SQL インジェクションは、ユーザー入力がそのまま SQL クエリに結合されることで、攻撃者が任意の SQL を実行できる脆弱性です。データの窃取・改ざん・削除、認証バイパス、さらにはサーバー全体の侵害に繋がります。

### 典型的な脆弱パターン

```php
// ❌ 危険: ユーザー入力を直接結合
$id = $_GET['id'];
$wpdb->query("SELECT * FROM {$wpdb->posts} WHERE ID = {$id}");

// ❌ 危険: LIKE 句でのワイルドカード未処理
$search = $_GET['s'];
$wpdb->query($wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE post_title LIKE '%{$search}%'"
));

// ❌ 危険: IN 句の動的生成
$ids = implode(',', $_GET['ids']);
$wpdb->query("SELECT * FROM {$wpdb->posts} WHERE ID IN ({$ids})");
```

### WpPack の対策

**Database コンポーネント** はネイティブの Prepared Statements を提供します。MySQL 環境では `mysqli_prepare()` + `bind_param()` によるパラメータバインディングを使用し、非 MySQL 環境では `$wpdb->prepare()` にフォールバックします。

```php
use WpPack\Component\Database\DatabaseManager;

// ✅ 安全: パラメータバインディング（%s = string, %d = integer, %f = float）
$db->fetchAllAssociative(
    "SELECT * FROM {$db->posts} WHERE ID = %d",
    [$id]
);

// ✅ 安全: LIKE 句もパラメータバインディング
// $wpdb->esc_like() でワイルドカード文字をエスケープしてからバインド
$db->fetchAllAssociative(
    "SELECT * FROM {$db->posts} WHERE post_title LIKE %s",
    ['%' . $wpdb->esc_like($search) . '%']
);
```

**Query コンポーネント** は `WP_Query` ラッパーとして、ホワイトリストベースのプレフィクス検証を提供します。

```php
use WpPack\Component\Query\PostQueryBuilder;

// ✅ 安全: ビルダーパターンで安全なクエリ構築
$posts = $builder
    ->where('meta.price > :price')
    ->where('tax.category IN :category')
    ->setParameters(['price' => 1000, 'category' => ['news']])
    ->get();
```

各ビルダーは許可されたプレフィクスのみを受け付けます:

| ビルダー | 許可プレフィクス |
|---------|----------------|
| `PostQueryBuilder` | `meta`, `tax`, `post` |
| `UserQueryBuilder` | `meta`, `user` |
| `TermQueryBuilder` | `meta`, `term` |

不正なプレフィクスが使用された場合は `InvalidArgumentException` がスローされます。

### ⚠ 開発者の責任

- Database コンポーネント経由で**必ず**パラメータバインディングを使用すること
- `$wpdb->query()` や `$wpdb->get_results()` を直接使用しないこと
- テーブル名やカラム名はバインディングできないため、`quoteIdentifier()` でエスケープするか、ホワイトリストで検証すること

---

## 2. クロスサイトスクリプティング（XSS）

### 概要

XSS は、攻撃者が悪意のある JavaScript をページに注入し、他のユーザーのブラウザで実行させる脆弱性です。セッションハイジャック、管理者権限の奪取、フィッシングなどに利用されます。WordPress プラグイン脆弱性の中で最も報告件数が多いカテゴリです。

### 典型的な脆弱パターン

```php
// ❌ 危険: ユーザー入力をエスケープせず出力
echo $_GET['q'];

// ❌ 危険: データベースの値をそのまま出力
echo get_post_meta($post_id, 'custom_field', true);

// ❌ 危険: 属性値のエスケープ漏れ
echo '<input value="' . $user_input . '">';

// ❌ 危険: URL の未検証出力
echo '<a href="' . $url . '">Link</a>'; // javascript: スキームが通る
```

### WpPack の対策

**Escaper コンポーネント** はコンテキスト別のエスケープメソッドを提供します。

```php
use WpPack\Component\Escaper\Escaper;

// ✅ HTML 本文コンテキスト（&, <, >, ", ' をエスケープ）
echo $escaper->html($value);

// ✅ HTML 属性コンテキスト
echo '<input value="' . $escaper->attr($value) . '">';

// ✅ URL コンテキスト（スキーム検証 + エンティティエンコード）
echo '<a href="' . $escaper->url($value) . '">';

// ✅ JavaScript コンテキスト（クォート・バックスラッシュのエスケープ）
echo '<script>var x = "' . $escaper->js($value) . '";</script>';

// ✅ textarea コンテキスト（二重エンコード対応）
echo '<textarea>' . $escaper->textarea($value) . '</textarea>';

// ✅ 動的コンテキスト選択
echo $escaper->escape($value, 'html');
```

不正なストラテジー名が指定された場合は `InvalidArgumentException` がスローされます。

**Sanitizer コンポーネント** は入力時のサニタイズを提供します。

```php
use WpPack\Component\Sanitizer\Sanitizer;

$sanitizer->text($input);      // HTML タグ除去、UTF-8 検証、余分な空白除去
$sanitizer->textarea($input);  // 改行を保持した上でサニタイズ
$sanitizer->ksesPost($input);  // 投稿に許可された HTML タグのみ保持
$sanitizer->url($input);       // URL サニタイズ（DB 保存用）
$sanitizer->email($input);     // メールアドレスサニタイズ
$sanitizer->filename($input);  // ファイル名サニタイズ
$sanitizer->htmlClass($input); // HTML クラス名（英数字・ハイフン・アンダースコアのみ）
```

**Templating コンポーネント** の Twig ブリッジでは、自動エスケープが有効です。

```twig
{# ✅ 自動的に HTML エスケープされる #}
{{ post.title }}

{# ✅ WordPressExtension のフィルターも利用可能 #}
{{ value|esc_attr }}
{{ value|esc_html }}
{{ value|wp_kses_post }}
```

### ⚠ 開発者の責任

- **出力時に適切なコンテキストのエスケーパーを選択すること** — `html()` と `attr()` を混同しないこと
- **Sanitizer（入力用）と Escaper（出力用）を正しく使い分けること** — サニタイズは入力を正規化するもの、エスケープは出力を安全にするもの。両方を適切なタイミングで使用すること
- `wp_kses_post()` での許可タグが要件に合っているか確認すること

---

## 3. クロスサイトリクエストフォージェリ（CSRF）

### 概要

CSRF は、認証済みユーザーに意図しないリクエストを送信させる攻撃です。管理者がフォーラム投稿やメールのリンクをクリックしただけで、プラグイン設定の変更やデータ削除が実行される可能性があります。

### 典型的な脆弱パターン

```php
// ❌ 危険: admin_post ハンドラで nonce チェックなし
add_action('admin_post_delete_item', function () {
    $id = $_POST['id'];
    $wpdb->delete('items', ['id' => $id]);
    wp_redirect(admin_url());
});

// ❌ 危険: AJAX ハンドラで referer チェックなし
add_action('wp_ajax_update_settings', function () {
    update_option('my_setting', $_POST['value']);
});
```

### WpPack の対策

**Nonce コンポーネント** は WordPress の nonce メカニズムをラップします。

```php
use WpPack\Component\Nonce\NonceManager;

// nonce フィールドの出力
echo $nonce->field('delete_item_' . $id);

// nonce 検証
if (!$nonce->verify($_POST['_wpnonce'], 'delete_item_' . $id)) {
    wp_die('Invalid nonce.');
}

// URL に nonce を付加
$url = $nonce->url(admin_url('admin-post.php?action=delete'), 'delete_action');
```

**Ajax コンポーネント** は `#[Ajax]` アトリビュートで宣言的に CSRF 保護を適用します。

```php
use WpPack\Component\Ajax\Attribute\Ajax;
use WpPack\Component\Ajax\Access;

class ItemController
{
    // ✅ checkReferer で自動的に nonce 検証
    #[Ajax(action: 'delete_item', access: Access::Authenticated, checkReferer: 'delete_item')]
    public function delete(): void
    {
        // check_ajax_referer() が自動的に呼ばれる
    }
}
```

**Rest コンポーネント** の REST API エンドポイントは WordPress の REST nonce メカニズム（`X-WP-Nonce` ヘッダ）を使用します。

### ⚠ 開発者の責任

- フォーム送信・状態変更操作で**必ず** nonce を検証すること
- Ajax ハンドラでは `checkReferer` パラメータを設定すること
- nonce のアクション名はリソース固有にすること（例: `delete_item_{$id}`）

---

## 4. 認証・認可の不備（Broken Access Control）

### 概要

認証・認可の不備は、権限のないユーザーが保護されたリソースや機能にアクセスできてしまう脆弱性です。管理者のみが利用すべき機能が一般ユーザーや未認証者に公開されるなど、WordPress プラグインでは XSS に次いで多く報告されています。

### 典型的な脆弱パターン

```php
// ❌ 危険: nopriv で管理操作が可能
add_action('wp_ajax_nopriv_delete_all_posts', 'handle_delete_all');

// ❌ 危険: current_user_can() チェック漏れ
add_action('wp_ajax_export_users', function () {
    // 権限チェックなしでユーザーデータをエクスポート
    $users = get_users();
    wp_send_json($users);
});

// ❌ 危険: REST API で permission_callback なし
register_rest_route('myplugin/v1', '/settings', [
    'callback' => 'update_settings',
    // permission_callback が未設定
]);
```

### WpPack の対策

**Security コンポーネント** は Voter パターンによる DENY-first の認可システムを提供します。

```php
use WpPack\Component\Security\Authorization\Voter\VoterInterface;

class ArticleVoter implements VoterInterface
{
    public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int
    {
        // 認証されていないユーザーは拒否
        if (!$token->isAuthenticated()) {
            return self::ACCESS_DENIED;
        }

        return match ($attribute) {
            'EDIT' => $this->canEdit($token->getUser(), $subject),
            'DELETE' => $this->canDelete($token->getUser(), $subject),
            default => self::ACCESS_ABSTAIN,
        };
    }
}
```

組み込みの `CapabilityVoter` は WordPress の `user_can()` に委任します。`RoleVoter` は `ROLE_` プレフィクスのロールチェックを処理します。

**`#[IsGranted]` アトリビュート** は宣言的に権限チェックを適用します。

```php
use WpPack\Component\Security\Attribute\IsGranted;

class AdminController
{
    #[IsGranted('manage_options')]
    public function updateSettings(): void
    {
        // manage_options 権限がないユーザーはアクセス不可
    }
}
```

**Rest コンポーネント** は `#[Permission]` と `#[IsGranted]` の 2 層で認可を制御します。

```php
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Security\Attribute\IsGranted;

class SettingsController
{
    // ✅ Permission + IsGranted の AND 条件
    #[RestRoute(route: '/settings', methods: HttpMethod::POST, namespace: 'myplugin/v1')]
    #[Permission(callback: '__return_true')]
    #[IsGranted('manage_options')]
    public function update(Request $request): JsonResponse
    {
        // Permission callback と capability チェックの両方を通過する必要がある
    }
}
```

**Ajax コンポーネント** は `Access` enum でアクセス範囲を制御します。

```php
use WpPack\Component\Ajax\Access;

// Authenticated: 認証済みユーザーのみ（wp_ajax_ のみ登録）
#[Ajax(action: 'admin_action', access: Access::Authenticated)]

// Public: 認証済み + 未認証（wp_ajax_ + wp_ajax_nopriv_ 両方登録）
#[Ajax(action: 'public_action', access: Access::Public)]

// Guest: 未認証ユーザーのみ（wp_ajax_nopriv_ のみ登録）
#[Ajax(action: 'guest_action', access: Access::Guest)]
```

**AuthenticationException** は `getSafeMessage()` により、ユーザー列挙攻撃を防ぐ汎用的なエラーメッセージを返します。

```php
// 内部的な詳細メッセージ（ログ用）
$exception->getMessage(); // "User 'admin' not found."

// 安全なメッセージ（ユーザー向け）
$exception->getSafeMessage(); // "Authentication failed."
```

### ⚠ 開発者の責任

- **すべてのエンドポイントに適切な権限チェックを付与すること**
- REST API では `#[Permission]` を省略しないこと（省略時は `LogicException`）
- Ajax では `Access::Public` を使用する際に、本当に未認証アクセスが必要か確認すること
- Voter のカスタム実装では、未知の `$attribute` に対して `ACCESS_ABSTAIN` を返すこと

---

## 5. 安全でないデシリアライゼーション

### 概要

安全でないデシリアライゼーションは、PHP の `unserialize()` で信頼できないデータを処理することで、任意のクラスのインスタンス化や、マジックメソッド（`__wakeup()`, `__destruct()`）の悪用によるリモートコード実行に繋がる脆弱性です。

### 典型的な脆弱パターン

```php
// ❌ 危険: ユーザー入力を直接 unserialize
$data = unserialize($_POST['data']);

// ❌ 危険: maybe_unserialize() で任意クラスのインスタンス化
$options = maybe_unserialize(get_option('plugin_data'));

// ❌ 危険: Cookie からのデシリアライズ
$cart = unserialize($_COOKIE['cart']);
```

### WpPack の対策

**Serializer コンポーネント** は PHP の `unserialize()` を一切使用しません。`ObjectNormalizer` はコンストラクタベースのデシリアライゼーションを行い、公開プロパティのみを処理します。

```php
use WpPack\Component\Serializer\Normalizer\ObjectNormalizer;

$normalizer = new ObjectNormalizer();

// ✅ 安全: コンストラクタ引数ベースでオブジェクトを復元
// ReflectionClass を使用し、コンストラクタパラメータとデータをマッチング
$object = $normalizer->denormalize($data, MyClass::class);
```

`ObjectNormalizer` のセキュリティ特性:
- `public` プロパティのみ正規化/復元対象
- クラス存在チェック（`class_exists()` で不明なクラスを拒否）
- コンストラクタの型宣言に基づく型安全な復元
- `private` / `protected` プロパティは無視

**Messenger コンポーネント** の `JsonSerializer` は JSON ベースのシリアライゼーションを使用します。

```php
use WpPack\Component\Messenger\Serializer\JsonSerializer;

// ✅ JSON エンコード/デコード（unserialize 不使用）
$encoded = $serializer->encode($envelope);
// ['headers' => ['type' => 'App\Message\SendEmail', ...], 'body' => '{"to":"..."}']

$decoded = $serializer->decode($encoded);
```

`JsonSerializer` はデコード時にメッセージクラスとスタンプクラスの存在を検証します:

```php
// クラスが存在しない場合は MessageDecodingFailedException をスロー
if (!class_exists($messageClass)) {
    throw new MessageDecodingFailedException(
        sprintf('Message class "%s" not found.', $messageClass)
    );
}
```

### ⚠ 開発者の責任

- PHP の `unserialize()` をユーザー入力に対して使用しないこと
- Messenger のメッセージクラスは `class_exists()` チェックのみでクラスホワイトリストは組み込まれていないため、信頼境界外（外部キューなど）からのメッセージを処理する場合は、メッセージクラスの許可リストを別途実装することを検討すること
- `maybe_unserialize()` の使用を避け、JSON や Serializer コンポーネントを使用すること

---

## 6. サーバーサイドリクエストフォージェリ（SSRF）

### 概要

SSRF は、サーバーが攻撃者の指定した URL にリクエストを送信させられる脆弱性です。内部ネットワークへのアクセス、クラウドメタデータサービス（`169.254.169.254`）からのクレデンシャル窃取、ポートスキャンなどに悪用されます。

### 典型的な脆弱パターン

```php
// ❌ 危険: ユーザー入力 URL を直接リクエスト
$url = $_POST['url'];
$response = wp_remote_get($url);

// ❌ 危険: リダイレクト先の未検証
$response = wp_remote_get($url, ['redirection' => 5]);
// 攻撃者が http://example.com → http://169.254.169.254/ にリダイレクト

// ❌ 危険: URL パラメータの部分的な検証
$host = parse_url($_GET['url'], PHP_URL_HOST);
if ($host === 'api.example.com') { /* OK? */ }
// DNS リバインディングで回避可能
```

### WpPack の対策

**HttpClient コンポーネント** は WordPress の `wp_remote_request()` をラップし、WordPress のデフォルト SSL 検証とタイムアウト制御を提供します。

```php
use WpPack\Component\HttpClient\HttpClient;

// ✅ WordPress のデフォルトセキュリティ設定を継承
$client = (new HttpClient())
    ->baseUri('https://api.example.com')
    ->timeout(10)
    ->asJson();

$response = $client->get('/endpoint');
```

WordPress の `wp_remote_request()` は以下のデフォルト保護を提供します:
- SSL 証明書検証（`sslverify` デフォルト `true`）
- リダイレクト回数制限（デフォルト 5 回）
- タイムアウト制御

#### SSRF 防止: `safe()` メソッドと `SafeHttpClient`

ユーザー提供の URL にリクエストを送信する場合、`safe()` メソッドまたは `SafeHttpClient` を使用して SSRF 攻撃を防止できます。内部的に WordPress の `reject_unsafe_urls` オプション（`wp_http_validate_url()` による検証）を有効化し、以下の保護を提供します:

- プライベート IP・予約済み IP 範囲へのリクエストをブロック（`127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16` — クラウドメタデータエンドポイント含む）
- 許可ポートの制限（80, 443, 8080 のみ）
- URL に埋め込まれた認証情報の拒否（例: `http://user:pass@host/`）
- リダイレクト先の検証（間接的な SSRF を防止）

ブロックされた場合は `ConnectionException`（PSR-18 `NetworkExceptionInterface` 実装）がスローされます。

**`safe()` fluent メソッド — アドホックな利用に最適:**

```php
use WpPack\Component\HttpClient\HttpClient;

$http = new HttpClient();

// ✅ ユーザー提供 URL に対して safe() で SSRF 防止
$response = $http->safe()->get($userProvidedUrl);

// ✅ 他のオプションと組み合わせ可能
$response = $http
    ->safe()
    ->timeout(10)
    ->asJson()
    ->post($userProvidedUrl, $data);
```

**`SafeHttpClient` — DI 注入パターンに最適:**

`SafeHttpClient` は `reject_unsafe_urls` を常に強制する改ざん不可能なサブクラスです。`withOptions()` で保護を無効化できないため、サービスコンテナからの注入に適しています。

```php
use WpPack\Component\HttpClient\SafeHttpClient;

// ✅ DI コンテナで SafeHttpClient を注入（保護を無効化不可）
final class WebhookService
{
    public function __construct(
        private readonly SafeHttpClient $http,
    ) {}

    public function send(string $userProvidedUrl, array $payload): void
    {
        // reject_unsafe_urls が常に有効 — 安全が保証される
        $this->http->asJson()->post($userProvidedUrl, $payload);
    }
}
```

### ⚠ 開発者の責任

- **ユーザー提供 URL を扱う場合は `SafeHttpClient` または `safe()` を使用すること** — SSRF 保護が自動的に適用されます
- **`HttpClient`（safe なし）は信頼できる固定 URL 専用** — 自社 API や既知の外部サービスなど、URL がコード内でハードコードされている場合に使用
- **ドメインホワイトリストが必要な場合は別途実装すること** — `reject_unsafe_urls` はパブリック IP の外部ドメインをすべて許可するため、特定のドメインのみを許可したい場合はアプリケーション側で検証が必要

---

## 7. ファイルアップロード・パストラバーサル

### 概要

ファイルアップロードの不備は、悪意のあるファイル（PHP シェルなど）のアップロードやパストラバーサル（`../` を含むパス）によるサーバー上の任意ファイルの読み取り・上書きに繋がります。

### 典型的な脆弱パターン

```php
// ❌ 危険: アップロードファイルのパスを未検証で使用
$path = $_POST['path'];
move_uploaded_file($_FILES['file']['tmp_name'], ABSPATH . $path);

// ❌ 危険: ファイル名を未サニタイズで使用
$name = $_FILES['file']['name'];
file_put_contents(wp_upload_dir()['basedir'] . '/' . $name, $content);

// ❌ 危険: MIME タイプをクライアント申告のまま信頼
if ($_FILES['file']['type'] === 'image/png') { /* OK? */ }
```

### WpPack の対策

**Sanitizer コンポーネント** の `filename()` メソッドは、ファイル名から危険な文字を除去します。

```php
use WpPack\Component\Sanitizer\Sanitizer;

// ✅ 特殊文字除去、スペースをダッシュに置換
$safeName = $sanitizer->filename($userInput);
// "../../../etc/passwd" → "etc-passwd"

// ✅ MIME タイプ検証
$safeMime = $sanitizer->mimeType($userInput);
// "image/png; charset=utf-8" → "" (不正な形式)
```

**Storage コンポーネント** はクラウドストレージ抽象化を提供し、ローカルファイルシステムへの直接アクセスを回避します。

```php
use WpPack\Component\Storage\Adapter\Storage;

// ✅ クラウドストレージ経由（ローカルパストラバーサルのリスクなし）
$adapter = Storage::fromDsn('s3://bucket-name');
$adapter->write('uploads/document.pdf', $content, [
    'ContentType' => 'application/pdf',
]);
```

**S3StoragePlugin** は以下のセキュリティ対策を実装しています:

| 対策 | 詳細 |
|------|------|
| 権限チェック | `#[IsGranted('upload_files')]` — WordPress の `upload_files` 権限を要求 |
| MIME ホワイトリスト | WordPress の `get_allowed_mime_types()` に基づく厳格な検証 |
| ファイルサイズ制限 | デフォルト 100 MB、`UploadPolicy` で設定可能 |
| Pre-Signed URL | サーバーを経由せずにクライアントから直接 S3 にアップロード |
| 登録前検証 | ファイルが S3 に実際に存在するか確認してからアタッチメント登録 |
| リサイズ画像の拒否 | リサイズ済み画像の重複登録を防止 |

### ⚠ 開発者の責任

- Filesystem コンポーネントを使用する場合、パスの検証は呼び出し側の責任
- Wpress コンポーネントの `extractTo()` はアーカイブエントリのパスを直接使用するため、信頼できないアーカイブを展開する場合はパストラバーサルに注意すること
- アップロードされたファイルの MIME タイプは、クライアント申告値ではなくサーバー側で検証すること
- ローカルファイルシステムに書き込む場合は `realpath()` でパスを正規化し、意図したディレクトリ内であることを確認すること

---

## 8. メールヘッダーインジェクション

### 概要

メールヘッダーインジェクションは、メールアドレスやヘッダー値に改行文字（CR/LF）を挿入することで、追加のヘッダーや本文を注入する攻撃です。スパム送信、BCC によるメール窃取、フィッシングメールの送信に悪用されます。

### 典型的な脆弱パターン

```php
// ❌ 危険: ユーザー入力をヘッダーに直接使用
$from = $_POST['from'];
wp_mail($to, $subject, $message, "From: {$from}");
// 攻撃者: "attacker@evil.com\r\nBcc: victim@example.com"

// ❌ 危険: 件名の未検証
$subject = $_POST['subject'];
wp_mail($to, $subject, $message);
// 攻撃者: "Hello\r\nBcc: spam-list@evil.com\r\n\r\nSpam body"
```

### WpPack の対策

**Mailer コンポーネント** は多層的なインジェクション防止を実装しています。

**Address クラス** — メールアドレスと表示名の検証:

```php
use WpPack\Component\Mailer\Address;

// ✅ CR/LF/NULL を含むアドレスは InvalidArgumentException
new Address("user@example.com\r\nBcc: victim@test.com", 'Name');
// → InvalidArgumentException: Address contains invalid control characters.

// ✅ FILTER_VALIDATE_EMAIL による形式検証
new Address('not-an-email');
// → InvalidArgumentException: "not-an-email" is not a valid email address.
```

**Headers クラス** — ヘッダー値のインジェクション防止:

```php
use WpPack\Component\Mailer\Header\Headers;

// ✅ ヘッダー名・値に CR/LF/NULL が含まれると拒否
$headers->add('X-Custom', "value\r\nBcc: attacker@evil.com");
// → InvalidArgumentException: Header contains invalid control characters.
```

**DSN クレデンシャル保護** — DSN のパスワードは `getPassword()` getter 経由でのみアクセスされるため、DSN オブジェクトを誤ってログに出力してもクレデンシャルが露出しにくい設計になっています。

**TLS デフォルト** — SMTP 送信はポート 587（STARTTLS）をデフォルトで使用します。

> 詳細: [Mailer セキュリティガイド](../components/mailer/security.md)

### ⚠ 開発者の責任

- `wp_mail()` を直接使用せず、Mailer コンポーネント経由でメールを送信すること
- DSN クレデンシャルは環境変数で管理し、コードに埋め込まないこと
- 可能であれば IAM ロール認証（クレデンシャルレス）を使用すること

---

## 9. 機密情報の漏洩

### 概要

機密情報の漏洩は、エラーメッセージ・ログ・レスポンスに含まれるデータベースクレデンシャル、API キー、内部パスなどが攻撃者に露出する脆弱性です。本番環境での `WP_DEBUG = true` は最も一般的な原因の一つです。

### 典型的な脆弱パターン

```php
// ❌ 危険: デバッグ情報をそのまま出力
var_dump($wpdb->last_query);
echo $wpdb->last_error;

// ❌ 危険: 例外メッセージをユーザーに表示
try {
    $db->query($sql);
} catch (\Exception $e) {
    echo $e->getMessage(); // DB クレデンシャルが含まれる可能性
}

// ❌ 危険: error_log に機密情報
error_log("API Key: {$apiKey}, Response: {$response}");
```

### WpPack の対策

**Mailer DSN** — DSN のパスワードは `getPassword()` getter 経由でのみアクセスされるため、DSN オブジェクトを誤ってログに出力してもクレデンシャルが露出しにくい設計になっています。

**TransportException** — メール送信失敗時の例外メッセージからクレデンシャルを除去します。

**Security コンポーネント** — `AuthenticationException::getSafeMessage()` でユーザー向けの安全なエラーメッセージを返し、内部詳細を隠蔽します。

```php
use Psr\Log\LoggerInterface;

// ✅ Logger コンポーネントでの安全なログ出力
$logger->error('Authentication failed', [
    'exception' => $e,
    // クレデンシャルや個人情報をコンテキストに含めない
]);
```

**OAuthSecurity / SamlSecurity** — 認証エラー時のメッセージは一律のエラーページに統一し、ユーザー列挙攻撃を防止します。

### ⚠ 開発者の責任

- 本番環境で `WP_DEBUG` を `false` に設定すること
- `error_log()` を直接使用せず、Logger コンポーネント経由でログを出力すること
- 例外をキャッチした際、ユーザー向けには汎用的なエラーメッセージを返すこと
- API キーやトークンをログに出力しないこと

---

## 10. OAuth / SSO 実装の不備

### 概要

OAuth 2.0 / OpenID Connect および SAML の実装不備は、アカウント乗っ取り、セッションハイジャック、権限昇格に繋がります。特に state パラメータの未検証やリダイレクト URI の未制限は深刻な脆弱性となります。

### 典型的な脆弱パターン

```php
// ❌ 危険: state パラメータ未検証（CSRF）
$code = $_GET['code'];
$token = $provider->getAccessToken($code);

// ❌ 危険: リダイレクト先の未検証（Open Redirect）
$redirect = $_GET['redirect_to'];
wp_redirect($redirect);

// ❌ 危険: ID トークンの署名未検証
$claims = json_decode(base64_decode(explode('.', $idToken)[1]));

// ❌ 危険: email のみでアカウントを紐付け
$user = get_user_by('email', $claims['email']);
wp_set_auth_cookie($user->ID);
```

### WpPack の対策

**OAuthSecurity コンポーネント** は以下の保護を提供します:

| 脅威 | 対策 |
|------|------|
| 認可コード傍受 | PKCE（RFC 7636）S256 がデフォルト有効 |
| CSRF | state パラメータのワンタイム検証（Transient ベース） |
| トークン偽造 | JWKS による JWT 署名検証 + claims 検証 |
| リプレイ攻撃 | nonce + state の消費（使い捨て） |
| オープンリダイレクト | 同一オリジンチェック + `wp_validate_redirect()` |
| アカウント乗っ取り | Subject ID バインディング（email 一致だけでは紐付けない） |
| タイミング攻撃 | `hash_equals()` による定時間比較 |
| セッション固定 | 認証成功時にセッション再生成 |
| XSS / プロフィール改ざん | OAuth claims のサニタイズ |
| MITM / ダウングレード | HTTPS 強制 |

```php
// ✅ Subject ID バインディング — email だけでなく IdP の sub クレームでアカウントを紐付け
// これにより、同じメールアドレスを持つ別の IdP ユーザーによる乗っ取りを防止
```

**SamlSecurity コンポーネント** は以下の保護を提供します:

| 脅威 | 対策 |
|------|------|
| リプレイ攻撃 | `onelogin/php-saml` の InResponseTo 検証 |
| 署名偽造 | Assertion 署名検証 |
| NameID 傍受 | NameID 暗号化（オプション） |
| アカウント乗っ取り | NameID バインディング |
| 権限昇格 | デフォルトロール制限 |
| 情報漏洩 | エラーメッセージのサニタイズ |

**本番環境では `strict: true` が必須**です（署名検証と InResponseTo 検証を有効化）。

**CrossSiteRedirector**（マルチサイト SSO）は HMAC 署名 + ホストホワイトリスト + HTTPS でクロスサイトリダイレクトを保護します。

```php
// ✅ allowedHosts で許可するホストを明示的に指定
// HMAC トークンにより改ざんを防止
```

> 詳細: [OAuth Security](../components/security/oauth-security.md) / [SAML Security](../components/security/saml-security.md)

### ⚠ 開発者の責任

- OAuth/SAML の設定で IdP のエンドポイント URL を正しく設定すること
- SAML では本番環境で `strict: true` を有効にすること
- JIT（Just-In-Time）ユーザープロビジョニング時のデフォルトロールを最小権限に設定すること
- マルチサイトでは `allowedHosts` を明示的に設定すること

---

## セキュリティチェックリスト

開発時に確認すべき項目の一覧です。

### 入力処理

- [ ] すべてのユーザー入力を Sanitizer コンポーネントでサニタイズしているか
- [ ] SQL クエリは Database コンポーネントのパラメータバインディングを使用しているか
- [ ] ファイルアップロードで MIME タイプとファイルサイズを検証しているか
- [ ] ユーザー提供 URL へのリクエストに `SafeHttpClient` または `safe()` を使用しているか

### 出力処理

- [ ] すべての出力を Escaper コンポーネントの適切なコンテキストでエスケープしているか
- [ ] エラーメッセージに機密情報（クレデンシャル、SQL クエリ、パス）が含まれていないか
- [ ] ログ出力に API キーやパスワードが含まれていないか

### 認証・認可

- [ ] すべてのエンドポイントに `#[Permission]` または `#[IsGranted]` が付与されているか
- [ ] Ajax ハンドラで `Access` enum が適切に設定されているか
- [ ] 管理者向け機能が一般ユーザーや未認証者からアクセスできないか

### CSRF 対策

- [ ] 状態変更操作（POST/PUT/DELETE）で nonce を検証しているか
- [ ] Ajax ハンドラで `checkReferer` を設定しているか

### SSO

- [ ] OAuth で PKCE が有効になっているか（デフォルト有効）
- [ ] SAML で `strict: true` が設定されているか
- [ ] リダイレクト先が `wp_validate_redirect()` で検証されているか

### インフラストラクチャ

- [ ] 本番環境で `WP_DEBUG` が `false` に設定されているか
- [ ] クレデンシャルが環境変数または IAM ロールで管理されているか
- [ ] HTTPS が強制されているか

---

## WpPack セキュリティコンポーネント横断マトリクス

脆弱性カテゴリと対応するコンポーネントの関係を示します。

| 脆弱性カテゴリ | 主要コンポーネント | 補助コンポーネント |
|--------------|------------------|------------------|
| SQL インジェクション | Database, Query | — |
| XSS | Escaper, Sanitizer | Templating (Twig) |
| CSRF | Nonce | Ajax, Rest |
| 認証・認可の不備 | Security (Voter) | Rest, Ajax, Role |
| デシリアライゼーション | Serializer | Messenger |
| SSRF | HttpClient (SafeHttpClient) | — |
| ファイルアップロード | Sanitizer, Storage | S3StoragePlugin |
| メールヘッダーインジェクション | Mailer | — |
| 機密情報の漏洩 | Security, Mailer | Logger |
| OAuth/SSO | OAuthSecurity, SamlSecurity | Security |

### 凡例

- **主要コンポーネント**: 脆弱性に対する直接的な防御機能を提供
- **補助コンポーネント**: 組み合わせて使用することで防御を強化
