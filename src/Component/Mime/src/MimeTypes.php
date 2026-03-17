<?php

declare(strict_types=1);

namespace WpPack\Component\Mime;

final class MimeTypes implements MimeTypesInterface
{
    private static ?self $default = null;

    /** @var list<MimeTypeGuesserInterface> */
    private array $guessers = [];

    /**
     * @param list<MimeTypeGuesserInterface> $guessers Additional guessers (registered with highest priority)
     */
    public function __construct(array $guessers = [])
    {
        $this->registerGuesser(new ExtensionMimeTypeGuesser());
        $this->registerGuesser(new FileinfoMimeTypeGuesser());

        $wpGuesser = new WordPressMimeTypeGuesser();
        if ($wpGuesser->isGuesserSupported()) {
            $this->registerGuesser($wpGuesser);
        }

        foreach ($guessers as $guesser) {
            $this->registerGuesser($guesser);
        }
    }

    public static function getDefault(): self
    {
        return self::$default ??= new self();
    }

    public static function setDefault(self $default): void
    {
        self::$default = $default;
    }

    /**
     * @internal For testing only
     */
    public static function reset(): void
    {
        self::$default = null;
    }

    public function registerGuesser(MimeTypeGuesserInterface $guesser): void
    {
        array_unshift($this->guessers, $guesser);
    }

    public function isGuesserSupported(): bool
    {
        foreach ($this->guessers as $guesser) {
            if ($guesser->isGuesserSupported()) {
                return true;
            }
        }

        return false;
    }

    public function guessMimeType(string $path): ?string
    {
        foreach ($this->guessers as $guesser) {
            if (!$guesser->isGuesserSupported()) {
                continue;
            }

            $mimeType = $guesser->guessMimeType($path);
            if ($mimeType !== null) {
                return $mimeType;
            }
        }

        return null;
    }

    public function getExtensions(string $mimeType): array
    {
        $mimeType = strtolower($mimeType);

        if (\function_exists('wp_get_mime_types')) {
            /** @var array<string, string> $wpTypes */
            $wpTypes = wp_get_mime_types();
            $extensions = [];
            foreach ($wpTypes as $extPattern => $mime) {
                if (strtolower($mime) === $mimeType) {
                    foreach (explode('|', $extPattern) as $ext) {
                        $extensions[] = $ext;
                    }
                }
            }
            if ($extensions !== []) {
                return $extensions;
            }
        }

        return MimeTypeMap::MIMES_TO_EXTENSIONS[$mimeType] ?? [];
    }

    public function getMimeTypes(string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        if (\function_exists('wp_get_mime_types')) {
            /** @var array<string, string> $wpTypes */
            $wpTypes = wp_get_mime_types();
            foreach ($wpTypes as $extPattern => $mime) {
                $exts = explode('|', $extPattern);
                if (\in_array($extension, $exts, true)) {
                    return [$mime];
                }
            }
        }

        return MimeTypeMap::EXTENSIONS_TO_MIMES[$extension] ?? [];
    }

    public function getAllowedMimeTypes(?int $userId = null): array
    {
        if (\function_exists('get_allowed_mime_types')) {
            /** @var array<string, string> */
            return get_allowed_mime_types($userId);
        }

        return array_map(
            static fn(array $mimes): string => $mimes[0],
            MimeTypeMap::EXTENSIONS_TO_MIMES,
        );
    }

    public function getExtensionType(string $extension): ?string
    {
        $extension = strtolower(ltrim($extension, '.'));

        if (\function_exists('wp_ext2type')) {
            /** @var string|null */
            return wp_ext2type($extension);
        }

        return MimeTypeMap::EXTENSION_TYPES[$extension] ?? null;
    }

    public function validateFile(string $filePath, string $filename): FileTypeInfo
    {
        if (\function_exists('wp_check_filetype_and_ext')) {
            /** @var array{ext: string|false, type: string|false, proper_filename: string|false} $result */
            $result = wp_check_filetype_and_ext($filePath, $filename);

            return new FileTypeInfo(
                extension: \is_string($result['ext']) && $result['ext'] !== '' ? $result['ext'] : null,
                mimeType: \is_string($result['type']) && $result['type'] !== '' ? $result['type'] : null,
                properFilename: \is_string($result['proper_filename']) && $result['proper_filename'] !== ''
                    ? $result['proper_filename'] : null,
            );
        }

        $mimeType = $this->guessMimeType($filePath);
        $ext = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));
        $validExt = $ext !== '' ? $ext : null;

        return new FileTypeInfo(
            extension: $validExt,
            mimeType: $mimeType,
        );
    }

    public function sanitize(string $mimeType): string
    {
        if (\function_exists('sanitize_mime_type')) {
            /** @var string */
            return sanitize_mime_type($mimeType);
        }

        return (string) preg_replace('/[^-+*.a-zA-Z0-9\/]/', '', $mimeType);
    }
}
