<?php
declare(strict_types=1);

/**
 * Inventory AH77_SOURCE without modifying it.
 *
 * Emits TSV: relative_path, bytes, ext, mime, width, height, md5, duplicate_of
 * Usage: php tools/inventory_source.php AH77_SOURCE > /tmp/inventory.tsv
 */

$root = $argv[1] ?? 'AH77_SOURCE';
if (!is_dir($root)) {
    fwrite(STDERR, "Not a directory: {$root}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$rows = [];
$seen = [];

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    $size = $file->getSize();
    $ext = strtolower($file->getExtension());
    $mime = 'application/octet-stream';
    $width = $height = '';
    $hash = md5_file($path) ?: '';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $path) ?: $mime;
            finfo_close($finfo);
        }
    }

    if (str_starts_with($mime, 'image/') || in_array($ext, ['avif', 'webp', 'png', 'jpg', 'jpeg', 'gif', 'svg'], true)) {
        $info = @getimagesize($path);
        if (is_array($info)) {
            $width = (string) $info[0];
            $height = (string) $info[1];
            $mime = $info['mime'] ?? $mime;
        }
    }

    $dupe = $seen[$hash] ?? '';
    if ($hash !== '' && $dupe === '') {
        $seen[$hash] = $path;
    }

    $rows[] = [$path, (string) $size, $ext, $mime, $width, $height, $hash, $dupe];
}

usort($rows, static fn(array $a, array $b): int => strcmp($a[0], $b[0]));

echo implode("\t", ['path', 'bytes', 'ext', 'mime', 'width', 'height', 'md5', 'duplicate_of']), "\n";
foreach ($rows as $row) {
    echo implode("\t", $row), "\n";
}
