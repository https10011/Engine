<?php
declare(strict_types=1);

/**
 * Build a labelled contact sheet from image files.
 *
 * Usage: php tools/contact_sheet.php out.png 4 200 file1 file2 ...
 * Images are cover-cropped into square cells and labelled with their basename.
 */

$out = $argv[1] ?? 'contact.png';
$cols = max(1, (int) ($argv[2] ?? 4));
$cell = max(60, (int) ($argv[3] ?? 180));
$files = array_slice($argv, 4);
if ($files === []) {
    fwrite(STDERR, "No input files\n");
    exit(1);
}

$rows = (int) ceil(count($files) / $cols);
$labelH = 20;
$sheet = imagecreatetruecolor($cols * $cell, $rows * ($cell + $labelH));
$bg = imagecolorallocate($sheet, 18, 18, 22);
imagefilledrectangle($sheet, 0, 0, imagesx($sheet), imagesy($sheet), $bg);
$ink = imagecolorallocate($sheet, 235, 235, 240);

foreach ($files as $i => $file) {
    $data = @file_get_contents($file);
    if ($data === false) {
        continue;
    }
    $src = @imagecreatefromstring($data);
    if ($src === false) {
        continue;
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = max($cell / $sw, $cell / $sh);
    $dw = (int) round($sw * $scale);
    $dh = (int) round($sh * $scale);
    $dx = (int) (($i % $cols) * $cell + ($cell - $dw) / 2);
    $dy = (int) (intdiv($i, $cols) * ($cell + $labelH) + ($cell - $dh) / 2);
    imagecopyresampled($sheet, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);

    $label = basename($file);
    if (strlen($label) > 26) {
        $label = substr($label, 0, 25) . '…';
    }
    imagestring($sheet, 2, (int) (($i % $cols) * $cell + 4), (int) (intdiv($i, $cols) * ($cell + $labelH) + $cell + 4), $label, $ink);
}

imagepng($sheet, $out);
imagedestroy($sheet);
echo "wrote {$out} (" . imagesx(imagecreatefrompng($out)) . "px wide)\n";
