<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;

#[RestRoute(route: '/s3/presigned-url', methods: HttpMethod::POST, namespace: 'wppack/v1')]
#[IsGranted('upload_files')]
final class PreSignedUrlController extends AbstractRestController
{
    public function __construct(
        private readonly PreSignedUrlGenerator $generator,
        private readonly UploadPolicy $policy,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getPayload();
        $filename = $payload->getString('filename');
        $contentType = $payload->getString('content_type');
        $contentLength = $payload->getString('content_length');

        if ($filename === '') {
            return $this->json(['error' => 'The "filename" parameter is required.'], 400);
        }

        if ($contentType === '') {
            return $this->json(['error' => 'The "content_type" parameter is required.'], 400);
        }

        if ($contentLength === '' || !ctype_digit($contentLength)) {
            return $this->json(['error' => 'The "content_length" parameter must be a positive integer.'], 400);
        }

        $contentLength = (int) $contentLength;

        if (!$this->policy->isAllowedType($contentType)) {
            return $this->json(['error' => sprintf('Content type "%s" is not allowed.', $contentType)], 400);
        }

        if (!$this->policy->isAllowedSize($contentLength)) {
            return $this->json([
                'error' => sprintf(
                    'Content length %d exceeds the maximum allowed size of %d bytes.',
                    $contentLength,
                    $this->policy->getMaxFileSize(),
                ),
            ], 400);
        }

        $result = $this->generator->generate($filename, $contentType, $contentLength);

        return $this->json([
            'url' => $result->url,
            'key' => $result->key,
            'expires_in' => $result->expiresIn,
        ]);
    }
}
