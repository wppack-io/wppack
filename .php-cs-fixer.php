<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->notPath('templates/')
    ->notPath('Debug/src/Toolbar/Panel/ToolbarIcons.php')
    ->notPath('Mime/src/MimeTypeMap.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        'declare_strict_types' => true,
        'header_comment' => [
            'header' => <<<'EOF'
This file is part of the WpPack package.

(c) Tsuyoshi Tsurushima

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF,
            'comment_type' => 'comment',
            'location' => 'after_open',
            'separate' => 'bottom',
        ],
    ])
    ->setFinder($finder);
