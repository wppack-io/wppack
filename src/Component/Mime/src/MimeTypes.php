<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Mime;

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

        return MimeTypeMap::MIMES_TO_EXTENSIONS[$mimeType] ?? [];
    }

    public function getMimeTypes(string $extension): array
    {
        $extension = strtolower(ltrim($extension, '.'));

        /** @var array<string, string> $wpTypes */
        $wpTypes = wp_get_mime_types();
        $mimeTypes = [];
        foreach ($wpTypes as $extPattern => $mime) {
            $exts = explode('|', $extPattern);
            if (\in_array($extension, $exts, true)) {
                $mimeTypes[] = $mime;
            }
        }
        if ($mimeTypes !== []) {
            return $mimeTypes;
        }

        return MimeTypeMap::EXTENSIONS_TO_MIMES[$extension] ?? [];
    }

    public function getAllowedMimeTypes(?int $userId = null): array
    {
        /** @var array<string, string> */
        return get_allowed_mime_types($userId);
    }

    public function getExtensionType(string $extension): ?string
    {
        $extension = strtolower(ltrim($extension, '.'));

        /** @var string|null */
        return wp_ext2type($extension);
    }

    public function validateFile(string $filePath, string $filename): FileTypeInfo
    {
        /** @var array{ext: string|false, type: string|false, proper_filename: string|false} $result */
        $result = wp_check_filetype_and_ext($filePath, $filename);

        return new FileTypeInfo(
            extension: \is_string($result['ext']) && $result['ext'] !== '' ? $result['ext'] : null,
            mimeType: \is_string($result['type']) && $result['type'] !== '' ? $result['type'] : null,
            properFilename: \is_string($result['proper_filename']) && $result['proper_filename'] !== ''
                ? $result['proper_filename'] : null,
        );
    }

    public function sanitize(string $mimeType): string
    {
        /** @var string */
        return sanitize_mime_type($mimeType);
    }
}
