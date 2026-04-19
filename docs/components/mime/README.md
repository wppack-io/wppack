# Mime コンポーネント

MIME 型の判定・拡張子マッピングを統一的に提供するコンポーネント。WordPress の MIME 関連関数をフル統合し、WordPress なしでも静的マップ＋finfo で動作するグレースフルフォールバックを備える。

## インストール

```bash
composer require wppack/mime
```

## 概要

- ファイル内容からの MIME 型判定（finfo / WordPress）
- 拡張子 ↔ MIME 型の双方向マッピング
- WordPress `upload_mimes` フィルター対応のアップロード許可リスト
- ファイルタイプカテゴリ判定（image, audio, video, document 等）
- ファイル検証（コンテンツ + ファイル名の整合性チェック）
- MIME 型文字列のサニタイズ

## 基本的な使い方

### MIME 型の判定

```php
use WPPack\Component\Mime\MimeTypes;

$mimeTypes = MimeTypes::getDefault();

// ファイル内容から MIME 型を判定
$mimeType = $mimeTypes->guessMimeType('/path/to/image.png');
// => 'image/png'
```

### 拡張子 ↔ MIME 型の変換

```php
// MIME 型 → 拡張子
$extensions = $mimeTypes->getExtensions('image/jpeg');
// => ['jpg', 'jpeg', 'jpe']

// 拡張子 → MIME 型
$types = $mimeTypes->getMimeTypes('jpg');
// => ['image/jpeg']
```

### ファイルタイプカテゴリ

```php
$type = $mimeTypes->getExtensionType('mp4');
// => 'video'

$type = $mimeTypes->getExtensionType('docx');
// => 'document'
```

### ファイル検証

```php
$info = $mimeTypes->validateFile('/tmp/upload.tmp', 'photo.jpg');

if ($info->isValid()) {
    echo $info->extension;      // 'jpg'
    echo $info->mimeType;       // 'image/jpeg'
    echo $info->properFilename; // null or corrected filename
}
```

### アップロード許可 MIME 型

```php
// WordPress 環境: get_allowed_mime_types() を使用
// 非 WordPress 環境: 全 MIME 型を返す
$allowed = $mimeTypes->getAllowedMimeTypes();
```

### MIME 型のサニタイズ

```php
$clean = $mimeTypes->sanitize('image/jpeg; charset=utf-8');
// => 'image/jpeg'
```

## Guesser チェーン

`MimeTypes::getDefault()` は以下の順序で guesser を登録する（後ろが高優先）:

1. **`ExtensionMimeTypeGuesser`** — ファイル拡張子から静的マップを参照（フォールバック）
2. **`FileinfoMimeTypeGuesser`** — PHP `finfo` でファイル内容から判定
3. **`WordPressMimeTypeGuesser`** — `wp_check_filetype()` を使用（WordPress ロード時のみ）

### カスタム guesser の追加

```php
use WPPack\Component\Mime\MimeTypeGuesserInterface;

class CustomGuesser implements MimeTypeGuesserInterface
{
    public function isGuesserSupported(): bool
    {
        return true;
    }

    public function guessMimeType(string $path): ?string
    {
        // カスタムロジック
        return null;
    }
}

$mimeTypes = MimeTypes::getDefault();
$mimeTypes->registerGuesser(new CustomGuesser());
```

## WordPress 統合

WordPress がロードされている場合、以下の関数を優先的に使用する:

| メソッド | WordPress 関数 | フォールバック |
|----------|---------------|---------------|
| `guessMimeType()` | `wp_check_filetype()` | finfo + 拡張子マップ |
| `getExtensions()` | `wp_get_mime_types()` | 静的マップ |
| `getMimeTypes()` | `wp_get_mime_types()` | 静的マップ |
| `getAllowedMimeTypes()` | `get_allowed_mime_types()` | 全 MIME 型 |
| `getExtensionType()` | `wp_ext2type()` | 静的マップ |
| `validateFile()` | `wp_check_filetype_and_ext()` | finfo + 拡張子マッチング |
| `sanitize()` | `sanitize_mime_type()` | 正規表現 |
