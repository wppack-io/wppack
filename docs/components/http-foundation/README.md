# HttpFoundation Component

**パッケージ:** `wppack/http-foundation`
**名前空間:** `WpPack\Component\HttpFoundation\`
**レイヤー:** Abstraction

WordPress における HTTP リクエスト処理のためのオブジェクト指向レイヤーを提供します。`$_GET`、`$_POST`、`$_FILES` などのスーパーグローバルへの型安全なアクセスと、基本的なレスポンスクラスを提供します。

## インストール

```bash
composer require wppack/http-foundation
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - スーパーグローバルへの直接アクセス
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$file = $_FILES['upload'] ?? null;

// 手動でのバリデーション
if (empty($name) || !is_email($email)) {
    wp_die('Invalid input');
}

// 手動でのファイルアップロード処理
if ($file && $file['error'] === UPLOAD_ERR_OK) {
    $upload = wp_handle_upload($file, ['test_form' => false]);
    if (isset($upload['error'])) {
        wp_die($upload['error']);
    }
}

// 手動でのレスポンス送信
header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
```

### After（WpPack）

```php
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\JsonResponse;

class FormController
{
    public function handleSubmit(Request $request): JsonResponse
    {
        $name = $request->post->getString('name');
        $email = $request->post->getString('email');
        $page = $request->query->getInt('page', 1);

        if (empty($name) || empty($email)) {
            return new JsonResponse(
                ['error' => 'Name and email are required'],
                400
            );
        }

        if ($file = $request->files->get('upload')) {
            if ($file->isValid()) {
                $upload = $file->wpHandleUpload();
            }
        }

        return new JsonResponse(['success' => true, 'name' => $name]);
    }
}
```

## Request クラス

### リクエストの取得

```php
use WpPack\Component\HttpFoundation\Request;

// 現在の HTTP リクエストからインスタンスを生成
$request = Request::createFromGlobals();
```

### 型安全なパラメータアクセス

`ParameterBag` を使って型安全にリクエストパラメータへアクセスできます：

```php
// GET パラメータ（$_GET）
$page = $request->query->getInt('page', 1);
$active = $request->query->getBoolean('active', true);
$search = $request->query->getString('q', '');

// POST パラメータ（$_POST）
$name = $request->post->getString('name');
$email = $request->post->getString('email');
$count = $request->post->getInt('count', 0);

// いずれかのソースから取得
$value = $request->get('param');
```

### HTTP ヘッダーアクセス

```php
$contentType = $request->headers->get('Content-Type');
$userAgent = $request->headers->get('User-Agent');
$authorization = $request->headers->get('Authorization');
```

### リクエスト情報

```php
$method = $request->getMethod();       // HTTP メソッド
$isAjax = $request->isAjax();         // AJAX リクエストかどうか
$isSecure = $request->isSecure();     // HTTPS かどうか
$clientIp = $request->getClientIp();  // クライアント IP
```

### JSON ペイロード

```php
if ($request->isJson()) {
    $data = $request->toArray();
}
```

## File クラス

`File` は `\SplFileInfo` を拡張した汎用ファイルクラスです。MIME タイプ検出、拡張子推測、ファイル移動を提供します：

```php
use WpPack\Component\HttpFoundation\File\File;

$file = new File('/path/to/document.pdf');

// SplFileInfo のメソッドがそのまま使える
$path = $file->getPathname();  // '/path/to/document.pdf'
$size = $file->getSize();       // ファイルサイズ
$name = $file->getBasename();   // 'document.pdf'

// MIME タイプ検出（ディスクから検出）
$mimeType = $file->getMimeType();     // 'application/pdf'
$extension = $file->guessExtension(); // 'pdf'

// ファイルの移動（新しい File インスタンスを返す）
$moved = $file->move('/new/directory', 'renamed.pdf');
```

存在しないファイルを指定すると `FileNotFoundException` がスローされます：

```php
use WpPack\Component\HttpFoundation\File\Exception\FileNotFoundException;

try {
    $file = new File('/nonexistent/file.txt');
} catch (FileNotFoundException $e) {
    // ファイルが存在しない
}
```

## ファイルアップロード

`UploadedFile` は `File` を拡張し、アップロード固有の機能を追加します。`$_FILES` を `UploadedFile` オブジェクトとしてラップし、型安全に操作できます：

```php
use WpPack\Component\HttpFoundation\File\UploadedFile;

$file = $request->files->get('upload');

if ($file === null) {
    return new JsonResponse(['error' => 'No file uploaded'], 400);
}

if (!$file->isValid()) {
    return new JsonResponse(
        ['error' => $file->getErrorMessage()],
        400
    );
}

// ファイル情報の取得
$size = $file->getSize();
$mimeType = $file->getMimeType();           // ディスクから検出
$clientMime = $file->getClientMimeType();   // クライアント提供の MIME
$originalName = $file->getClientOriginalName();
$extension = $file->guessExtension();

// wp_handle_upload() を使ったアップロード
$result = $file->wpHandleUpload(['test_form' => false]);
```

### ファイルバリデーション

```php
$file = $request->files->get('image');

// サイズチェック
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file->getSize() > $maxSize) {
    return new JsonResponse(['error' => 'File too large'], 400);
}

// MIME タイプチェック（ディスクから検出された MIME を使用）
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($file->getMimeType(), $allowedTypes, true)) {
    return new JsonResponse(['error' => 'File type not allowed'], 400);
}

// WordPress のアップロードディレクトリに移動（File インスタンスを返す）
$uploadDir = wp_upload_dir();
$filename = uniqid() . '.' . $file->guessExtension();
$movedFile = $file->move($uploadDir['path'], $filename);
```

## JsonResponse

JSON レスポンスを簡単に生成できます：

```php
use WpPack\Component\HttpFoundation\JsonResponse;

// 基本的な JSON レスポンス
$response = new JsonResponse(['message' => 'Success']);

// ステータスコード指定
$response = new JsonResponse(
    ['error' => 'Not found'],
    404
);

// ヘッダー付き
$response = new JsonResponse(
    ['data' => $result],
    200,
    ['X-Custom-Header' => 'value']
);

// レスポンスの送信
$response->send();
```

## WordPress AJAX ハンドラーでの使用例

```php
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\JsonResponse;

final class UserApiHandler
{
    public function __construct(
        private readonly Request $request,
    ) {}

    #[Action('wp_ajax_get_users', priority: 10)]
    public function onWpAjaxGetUsers(): void
    {
        $page = $this->request->query->getInt('page', 1);
        $perPage = $this->request->query->getInt('per_page', 20);
        $search = $this->request->query->getString('search', '');

        $users = get_users([
            'number' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'search' => $search ? "*{$search}*" : '',
        ]);

        $response = new JsonResponse([
            'success' => true,
            'data' => array_map(fn($u) => [
                'id' => $u->ID,
                'name' => $u->display_name,
                'email' => $u->user_email,
            ], $users),
        ]);

        $response->send();
        wp_die();
    }

    #[Action('wp_ajax_update_user', priority: 10)]
    public function onWpAjaxUpdateUser(): void
    {
        if ($this->request->getMethod() !== 'POST') {
            (new JsonResponse(['error' => 'Method not allowed'], 405))->send();
            wp_die();
        }

        $userId = $this->request->post->getInt('user_id');
        $name = $this->request->post->getString('name');
        $email = $this->request->post->getString('email');

        if (empty($name) || empty($email)) {
            (new JsonResponse(['error' => 'Name and email are required'], 400))->send();
            wp_die();
        }

        wp_update_user([
            'ID' => $userId,
            'display_name' => $name,
            'user_email' => $email,
        ]);

        (new JsonResponse(['success' => true]))->send();
        wp_die();
    }
}
```

## ファイルアップロードハンドラーの例

```php
final class FileUploadHandler
{
    #[Action('wp_ajax_upload_file', priority: 10)]
    public function onWpAjaxUploadFile(): void
    {
        $request = Request::createFromGlobals();

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            (new JsonResponse([
                'success' => false,
                'error' => $file?->getErrorMessage() ?? 'No file uploaded',
            ], 400))->send();
            wp_die();
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedTypes, true)) {
            (new JsonResponse([
                'success' => false,
                'error' => 'File type not allowed',
            ], 400))->send();
            wp_die();
        }

        $result = $file->wpHandleUpload(['test_form' => false]);

        (new JsonResponse([
            'success' => true,
            'file' => [
                'url' => $result['url'],
                'type' => $result['type'],
            ],
        ]))->send();
        wp_die();
    }
}
```

## クイックリファレンス

### Request メソッド

```php
$request = Request::createFromGlobals();

// パラメータアクセス
$request->get('param')               // いずれかのソースから取得
$request->query->get('param')        // GET パラメータ
$request->post->get('param')         // POST パラメータ
$request->headers->get('header')     // HTTP ヘッダー
$request->cookies->get('cookie')     // Cookie
$request->files->get('file')         // アップロードファイル

// 型付きゲッター
$request->query->getInt('page', 1)
$request->query->getBoolean('active')
$request->query->getString('name', '')

// リクエスト情報
$request->getMethod()                // HTTP メソッド
$request->isAjax()                   // AJAX リクエストか
$request->isSecure()                 // HTTPS か
$request->isJson()                   // JSON リクエストか
$request->getClientIp()              // クライアント IP
$request->toArray()                  // JSON ボディを配列に変換
```

### JsonResponse

```php
new JsonResponse($data, $status, $headers)
$response->send()
```

## Response クラス

### Response（基底）

```php
use WpPack\Component\HttpFoundation\Response;

$response = new Response('Hello', 200, ['X-Custom' => 'value']);
$response->send(); // ヘッダー送信 + echo
```

### RedirectResponse

```php
use WpPack\Component\HttpFoundation\RedirectResponse;

$response = new RedirectResponse('/new-url', 302, safe: true);
// Location ヘッダーが自動設定される
```

### BinaryFileResponse

```php
use WpPack\Component\HttpFoundation\BinaryFileResponse;

$response = new BinaryFileResponse('/path/to/file.pdf', 'report.pdf', 'attachment');
```

## HTTP 例外

HTTP エラーを例外として throw できます。Rest / Routing / Ajax の各コンポーネントで統一的に利用されます：

```php
use WpPack\Component\HttpFoundation\Exception\NotFoundException;
use WpPack\Component\HttpFoundation\Exception\ForbiddenException;
use WpPack\Component\HttpFoundation\Exception\BadRequestException;

throw new NotFoundException('User not found.');       // 404
throw new ForbiddenException('Access denied.');       // 403
throw new BadRequestException('Invalid input.');      // 400
```

| 例外クラス | ステータスコード | デフォルト errorCode |
|-----------|----------------|---------------------|
| `BadRequestException` | 400 | `http_bad_request` |
| `UnauthorizedException` | 401 | `http_unauthorized` |
| `ForbiddenException` | 403 | `http_forbidden` |
| `NotFoundException` | 404 | `http_not_found` |
| `MethodNotAllowedException` | 405 | `http_method_not_allowed` |
| `ConflictException` | 409 | `http_conflict` |
| `UnprocessableEntityException` | 422 | `http_unprocessable_entity` |

## Kernel 統合

`Kernel::boot()` 時に `Request::createFromGlobals()` が自動的に呼ばれ、DI コンテナに synthetic service として登録されます。コントローラやサービスでコンストラクタインジェクション可能です：

```php
final class MyService
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function doSomething(): void
    {
        $page = $this->request->query->getInt('page', 1);
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Request` | HTTP リクエストラッパー |
| `Response` | 基底レスポンス |
| `JsonResponse` | JSON レスポンス |
| `RedirectResponse` | リダイレクトレスポンス |
| `BinaryFileResponse` | ファイルダウンロードレスポンス |
| `ParameterBag` | パラメータコレクション（型安全なゲッター付き） |
| `HeaderBag` | HTTP ヘッダーコレクション |
| `ServerBag` | サーバー変数コレクション |
| `FileBag` | アップロードファイルコレクション |
| `File\File` | 汎用ファイルクラス（`\SplFileInfo` 拡張） |
| `File\UploadedFile` | アップロードファイルラッパー（`File` 拡張） |
| `File\Exception\FileException` | ファイル操作例外 |
| `File\Exception\FileNotFoundException` | ファイル不在例外 |
| `ValueResolverInterface` | パラメータ値解決のインターフェース |
| `ArgumentResolver` | リゾルバチェーンによるメソッドパラメータ解決 |
| `RequestValueResolver` | `Request` 型ヒントパラメータの解決 |

## ArgumentResolver

`ArgumentResolver` は、メソッドパラメータの自動注入を提供する仕組みです。`ValueResolverInterface` を実装したリゾルバのチェーンを使って、メソッドの各パラメータに対応する値を解決します。

### ValueResolverInterface

各パラメータの解決ロジックを定義するインターフェースです。

```php
use WpPack\Component\HttpFoundation\ValueResolverInterface;

interface ValueResolverInterface
{
    public function supports(\ReflectionParameter $parameter): bool;
    public function resolve(\ReflectionParameter $parameter): mixed;
}
```

### ArgumentResolver の使い方

`ArgumentResolver` はリゾルバの配列を受け取り、`createResolver()` で対象メソッドのパラメータリゾルバを生成します。

```php
use WpPack\Component\HttpFoundation\ArgumentResolver;
use WpPack\Component\HttpFoundation\RequestValueResolver;
use WpPack\Component\Security\ValueResolver\CurrentUserValueResolver;

$argumentResolver = new ArgumentResolver([
    new RequestValueResolver($request),
    new CurrentUserValueResolver($security),
]);

// 対象オブジェクトのメソッドに対するリゾルバを生成
$resolver = $argumentResolver->createResolver($target, '__invoke');
```

`createResolver()` は、対象メソッドに解決可能なパラメータがある場合は `\Closure` を、ない場合は `null` を返します。

### RequestValueResolver

`Request` 型ヒントのパラメータに現在のリクエストオブジェクトを注入します。

```php
use WpPack\Component\HttpFoundation\RequestValueResolver;

$resolver = new RequestValueResolver($request);
```

### カスタムリゾルバの実装

`ValueResolverInterface` を実装してカスタムリゾルバを追加できます。

```php
use WpPack\Component\HttpFoundation\ValueResolverInterface;

final class MyServiceValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly MyService $service,
    ) {}

    public function supports(\ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof \ReflectionNamedType
            && $type->getName() === MyService::class;
    }

    public function resolve(\ReflectionParameter $parameter): mixed
    {
        return $this->service;
    }
}
```

```php
$argumentResolver = new ArgumentResolver([
    new RequestValueResolver($request),
    new CurrentUserValueResolver($security),
    new MyServiceValueResolver($myService),
]);
```

Routing コンポーネントの `Router` クラスは `ArgumentResolver` をコンストラクタで受け取り、ルートハンドラのパラメータ注入を自動的に設定します。

## 依存関係

### 必須
- なし（単独で動作可能）

### 推奨
- **Kernel Component** - DI コンテナへの自動登録
- **Hook Component** - WordPress リクエストライフサイクルとの統合
- **Validator Component** - リクエストデータのバリデーション
