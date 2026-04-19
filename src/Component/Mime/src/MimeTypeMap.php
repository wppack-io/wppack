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

/**
 * Comprehensive MIME type ↔ extension mapping database.
 *
 * Data sourced from Symfony Mime component and IANA registry.
 */
final class MimeTypeMap
{
    /**
     * Extension → list of MIME types.
     *
     * @var array<string, list<string>>
     */
    public const EXTENSIONS_TO_MIMES = [
        // Images
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'jpe' => ['image/jpeg'],
        'gif' => ['image/gif'],
        'png' => ['image/png'],
        'bmp' => ['image/bmp'],
        'svg' => ['image/svg+xml'],
        'svgz' => ['image/svg+xml'],
        'tiff' => ['image/tiff'],
        'tif' => ['image/tiff'],
        'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif'],
        'heic' => ['image/heic'],
        'heif' => ['image/heif'],
        'psd' => ['image/vnd.adobe.photoshop'],
        'ai' => ['application/postscript'],
        'eps' => ['application/postscript'],
        'ps' => ['application/postscript'],

        // Audio
        'mp3' => ['audio/mpeg'],
        'ogg' => ['audio/ogg'],
        'oga' => ['audio/ogg'],
        'flac' => ['audio/flac', 'audio/x-flac'],
        'wav' => ['audio/wav', 'audio/x-wav'],
        'aac' => ['audio/aac'],
        'wma' => ['audio/x-ms-wma'],
        'm4a' => ['audio/mp4', 'audio/x-m4a'],
        'mid' => ['audio/midi'],
        'midi' => ['audio/midi'],
        'opus' => ['audio/opus'],
        'aif' => ['audio/aiff', 'audio/x-aiff'],
        'aiff' => ['audio/aiff', 'audio/x-aiff'],
        'ra' => ['audio/x-realaudio'],
        'weba' => ['audio/webm'],

        // Video
        'mp4' => ['video/mp4'],
        'm4v' => ['video/mp4', 'video/x-m4v'],
        'webm' => ['video/webm'],
        'avi' => ['video/x-msvideo'],
        'mov' => ['video/quicktime'],
        'qt' => ['video/quicktime'],
        'wmv' => ['video/x-ms-wmv'],
        'mkv' => ['video/x-matroska'],
        'flv' => ['video/x-flv'],
        'ogv' => ['video/ogg'],
        'mpeg' => ['video/mpeg'],
        'mpg' => ['video/mpeg'],
        '3gp' => ['video/3gpp'],
        '3g2' => ['video/3gpp2'],
        'ts' => ['video/mp2t'],

        // Documents
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'dot' => ['application/msword'],
        'dotx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.template'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'xlt' => ['application/vnd.ms-excel'],
        'xltx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.template'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'pot' => ['application/vnd.ms-powerpoint'],
        'potx' => ['application/vnd.openxmlformats-officedocument.presentationml.template'],
        'odt' => ['application/vnd.oasis.opendocument.text'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
        'odp' => ['application/vnd.oasis.opendocument.presentation'],
        'odg' => ['application/vnd.oasis.opendocument.graphics'],
        'odf' => ['application/vnd.oasis.opendocument.formula'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'pages' => ['application/x-iwork-pages-sffpages'],
        'numbers' => ['application/x-iwork-numbers-sffnumbers'],
        'key' => ['application/x-iwork-keynote-sffkey'],

        // Text
        'txt' => ['text/plain'],
        'csv' => ['text/csv'],
        'tsv' => ['text/tab-separated-values'],
        'log' => ['text/plain'],
        'md' => ['text/markdown'],
        'markdown' => ['text/markdown'],
        'ini' => ['text/plain'],
        'cfg' => ['text/plain'],
        'conf' => ['text/plain'],
        'diff' => ['text/x-diff'],
        'patch' => ['text/x-diff'],
        'ics' => ['text/calendar'],
        'vcf' => ['text/vcard'],
        'vcard' => ['text/vcard'],

        // Code / Markup
        'html' => ['text/html'],
        'htm' => ['text/html'],
        'xhtml' => ['application/xhtml+xml'],
        'css' => ['text/css'],
        'js' => ['application/javascript', 'text/javascript'],
        'mjs' => ['application/javascript'],
        'json' => ['application/json'],
        'jsonld' => ['application/ld+json'],
        'xml' => ['application/xml', 'text/xml'],
        'xsl' => ['application/xslt+xml', 'application/xml'],
        'xslt' => ['application/xslt+xml'],
        'dtd' => ['application/xml-dtd'],
        'yaml' => ['application/x-yaml', 'text/yaml'],
        'yml' => ['application/x-yaml', 'text/yaml'],
        'toml' => ['application/toml'],
        'php' => ['application/x-httpd-php', 'text/x-php'],
        'py' => ['text/x-python', 'application/x-python'],
        'rb' => ['application/x-ruby', 'text/x-ruby'],
        'java' => ['text/x-java-source'],
        'c' => ['text/x-c'],
        'cpp' => ['text/x-c++src'],
        'h' => ['text/x-c'],
        'sh' => ['application/x-sh', 'text/x-shellscript'],
        'bash' => ['application/x-sh'],
        'sql' => ['application/sql', 'text/x-sql'],
        'rss' => ['application/rss+xml'],
        'atom' => ['application/atom+xml'],
        'wsdl' => ['application/wsdl+xml'],
        'manifest' => ['text/cache-manifest'],

        // Archives
        'zip' => ['application/zip'],
        'gz' => ['application/gzip'],
        'gzip' => ['application/gzip'],
        'tar' => ['application/x-tar'],
        'tgz' => ['application/x-tar', 'application/gzip'],
        'rar' => ['application/vnd.rar', 'application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
        'bz2' => ['application/x-bzip2'],
        'xz' => ['application/x-xz'],
        'zst' => ['application/zstd'],
        'lz' => ['application/x-lzip'],
        'cab' => ['application/vnd.ms-cab-compressed'],
        'dmg' => ['application/x-apple-diskimage'],
        'iso' => ['application/x-iso9660-image'],
        'jar' => ['application/java-archive'],

        // Fonts
        'woff' => ['font/woff', 'application/font-woff'],
        'woff2' => ['font/woff2'],
        'ttf' => ['font/ttf', 'application/x-font-ttf'],
        'otf' => ['font/otf', 'application/x-font-opentype'],
        'eot' => ['application/vnd.ms-fontobject'],

        // Binary / Executables
        'exe' => ['application/x-msdownload', 'application/vnd.microsoft.portable-executable'],
        'msi' => ['application/x-msdownload'],
        'dll' => ['application/x-msdownload'],
        'deb' => ['application/x-debian-package'],
        'rpm' => ['application/x-rpm'],
        'apk' => ['application/vnd.android.package-archive'],
        'swf' => ['application/x-shockwave-flash'],
        'bin' => ['application/octet-stream'],

        // Data formats
        'wasm' => ['application/wasm'],
        'sqlite' => ['application/x-sqlite3'],
        'gpx' => ['application/gpx+xml'],
        'kml' => ['application/vnd.google-earth.kml+xml'],
        'kmz' => ['application/vnd.google-earth.kmz'],
        'geojson' => ['application/geo+json'],

        // Misc
        'eml' => ['message/rfc822'],
        'mhtml' => ['message/rfc822', 'multipart/related'],
        'pem' => ['application/x-pem-file'],
        'crt' => ['application/x-x509-ca-cert'],
        'der' => ['application/x-x509-ca-cert'],
        'p12' => ['application/x-pkcs12'],
        'pfx' => ['application/x-pkcs12'],
        'torrent' => ['application/x-bittorrent'],
        'wpress' => ['application/octet-stream'],
    ];

    /**
     * MIME type → list of extensions.
     *
     * @var array<string, list<string>>
     */
    public const MIMES_TO_EXTENSIONS = [
        // Images
        'image/jpeg' => ['jpg', 'jpeg', 'jpe'],
        'image/gif' => ['gif'],
        'image/png' => ['png'],
        'image/bmp' => ['bmp'],
        'image/svg+xml' => ['svg', 'svgz'],
        'image/tiff' => ['tiff', 'tif'],
        'image/x-icon' => ['ico'],
        'image/vnd.microsoft.icon' => ['ico'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'image/heic' => ['heic'],
        'image/heif' => ['heif'],
        'image/vnd.adobe.photoshop' => ['psd'],

        // Audio
        'audio/mpeg' => ['mp3'],
        'audio/ogg' => ['ogg', 'oga'],
        'audio/flac' => ['flac'],
        'audio/x-flac' => ['flac'],
        'audio/wav' => ['wav'],
        'audio/x-wav' => ['wav'],
        'audio/aac' => ['aac'],
        'audio/x-ms-wma' => ['wma'],
        'audio/mp4' => ['m4a'],
        'audio/x-m4a' => ['m4a'],
        'audio/midi' => ['mid', 'midi'],
        'audio/opus' => ['opus'],
        'audio/aiff' => ['aif', 'aiff'],
        'audio/x-aiff' => ['aif', 'aiff'],
        'audio/x-realaudio' => ['ra'],
        'audio/webm' => ['weba'],

        // Video
        'video/mp4' => ['mp4', 'm4v'],
        'video/x-m4v' => ['m4v'],
        'video/webm' => ['webm'],
        'video/x-msvideo' => ['avi'],
        'video/quicktime' => ['mov', 'qt'],
        'video/x-ms-wmv' => ['wmv'],
        'video/x-matroska' => ['mkv'],
        'video/x-flv' => ['flv'],
        'video/ogg' => ['ogv'],
        'video/mpeg' => ['mpeg', 'mpg'],
        'video/3gpp' => ['3gp'],
        'video/3gpp2' => ['3g2'],
        'video/mp2t' => ['ts'],

        // Documents
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc', 'dot'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template' => ['dotx'],
        'application/vnd.ms-excel' => ['xls', 'xlt'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.template' => ['xltx'],
        'application/vnd.ms-powerpoint' => ['ppt', 'pot'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'application/vnd.openxmlformats-officedocument.presentationml.template' => ['potx'],
        'application/vnd.oasis.opendocument.text' => ['odt'],
        'application/vnd.oasis.opendocument.spreadsheet' => ['ods'],
        'application/vnd.oasis.opendocument.presentation' => ['odp'],
        'application/vnd.oasis.opendocument.graphics' => ['odg'],
        'application/vnd.oasis.opendocument.formula' => ['odf'],
        'application/rtf' => ['rtf'],
        'text/rtf' => ['rtf'],
        'application/x-iwork-pages-sffpages' => ['pages'],
        'application/x-iwork-numbers-sffnumbers' => ['numbers'],
        'application/x-iwork-keynote-sffkey' => ['key'],

        // Text
        'text/plain' => ['txt', 'log', 'ini', 'cfg', 'conf'],
        'text/csv' => ['csv'],
        'text/tab-separated-values' => ['tsv'],
        'text/markdown' => ['md', 'markdown'],
        'text/x-diff' => ['diff', 'patch'],
        'text/calendar' => ['ics'],
        'text/vcard' => ['vcf', 'vcard'],

        // Code / Markup
        'text/html' => ['html', 'htm'],
        'application/xhtml+xml' => ['xhtml'],
        'text/css' => ['css'],
        'application/javascript' => ['js', 'mjs'],
        'text/javascript' => ['js'],
        'application/json' => ['json'],
        'application/ld+json' => ['jsonld'],
        'application/xml' => ['xml', 'xsl'],
        'text/xml' => ['xml'],
        'application/xslt+xml' => ['xsl', 'xslt'],
        'application/xml-dtd' => ['dtd'],
        'application/x-yaml' => ['yaml', 'yml'],
        'text/yaml' => ['yaml', 'yml'],
        'application/toml' => ['toml'],
        'application/x-httpd-php' => ['php'],
        'text/x-php' => ['php'],
        'text/x-python' => ['py'],
        'application/x-python' => ['py'],
        'application/x-ruby' => ['rb'],
        'text/x-ruby' => ['rb'],
        'text/x-java-source' => ['java'],
        'text/x-c' => ['c', 'h'],
        'text/x-c++src' => ['cpp'],
        'application/x-sh' => ['sh', 'bash'],
        'text/x-shellscript' => ['sh'],
        'application/sql' => ['sql'],
        'text/x-sql' => ['sql'],
        'application/rss+xml' => ['rss'],
        'application/atom+xml' => ['atom'],
        'application/wsdl+xml' => ['wsdl'],
        'text/cache-manifest' => ['manifest'],

        // Archives
        'application/zip' => ['zip'],
        'application/gzip' => ['gz', 'gzip', 'tgz'],
        'application/x-tar' => ['tar', 'tgz'],
        'application/vnd.rar' => ['rar'],
        'application/x-rar-compressed' => ['rar'],
        'application/x-7z-compressed' => ['7z'],
        'application/x-bzip2' => ['bz2'],
        'application/x-xz' => ['xz'],
        'application/zstd' => ['zst'],
        'application/x-lzip' => ['lz'],
        'application/vnd.ms-cab-compressed' => ['cab'],
        'application/x-apple-diskimage' => ['dmg'],
        'application/x-iso9660-image' => ['iso'],
        'application/java-archive' => ['jar'],

        // Fonts
        'font/woff' => ['woff'],
        'application/font-woff' => ['woff'],
        'font/woff2' => ['woff2'],
        'font/ttf' => ['ttf'],
        'application/x-font-ttf' => ['ttf'],
        'font/otf' => ['otf'],
        'application/x-font-opentype' => ['otf'],
        'application/vnd.ms-fontobject' => ['eot'],

        // Binary
        'application/x-msdownload' => ['exe', 'msi', 'dll'],
        'application/vnd.microsoft.portable-executable' => ['exe'],
        'application/x-debian-package' => ['deb'],
        'application/x-rpm' => ['rpm'],
        'application/vnd.android.package-archive' => ['apk'],
        'application/x-shockwave-flash' => ['swf'],
        'application/octet-stream' => ['bin', 'wpress'],

        // Data formats
        'application/wasm' => ['wasm'],
        'application/x-sqlite3' => ['sqlite'],
        'application/gpx+xml' => ['gpx'],
        'application/vnd.google-earth.kml+xml' => ['kml'],
        'application/vnd.google-earth.kmz' => ['kmz'],
        'application/geo+json' => ['geojson'],

        // Misc
        'application/postscript' => ['ai', 'eps', 'ps'],
        'message/rfc822' => ['eml', 'mhtml'],
        'multipart/related' => ['mhtml'],
        'application/x-pem-file' => ['pem'],
        'application/x-x509-ca-cert' => ['crt', 'der'],
        'application/x-pkcs12' => ['p12', 'pfx'],
        'application/x-bittorrent' => ['torrent'],
    ];
}
