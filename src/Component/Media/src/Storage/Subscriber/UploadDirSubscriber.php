<?php

declare(strict_types=1);

namespace WpPack\Component\Media\Storage\Subscriber;

use WpPack\Component\Filesystem\Attribute\Filter\UploadDirFilter;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\UrlResolver;

#[AsHookSubscriber]
final readonly class UploadDirSubscriber
{
    public function __construct(
        private StorageConfiguration $config,
        private UrlResolver $urlResolver,
    ) {}

    /**
     * Rewrite upload directory paths and URLs to use object storage.
     *
     * @param array<string, string> $dirs
     * @return array<string, string>
     */
    #[UploadDirFilter]
    public function filterUploadDir(array $dirs): array
    {
        $basePath = sprintf('%s://%s/%s', $this->config->protocol, $this->config->bucket, $this->config->prefix);
        $baseUrl = $this->urlResolver->resolve($this->config->prefix);

        // Preserve multisite subdirectory (e.g., /sites/2/)
        $siteSubdir = '';
        if (is_multisite()) {
            $blogId = get_current_blog_id();
            if ($blogId > 1) {
                $siteSubdir = '/sites/' . $blogId;
            }
        }

        // subdir contains date-based subdirectory (e.g., /2024/01)
        $subdir = $dirs['subdir'] ?? '';

        $dirs['path'] = $basePath . $siteSubdir . $subdir;
        $dirs['url'] = $baseUrl . $siteSubdir . $subdir;
        $dirs['basedir'] = $basePath . $siteSubdir;
        $dirs['baseurl'] = $baseUrl . $siteSubdir;

        return $dirs;
    }
}
