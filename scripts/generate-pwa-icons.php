<?php

declare(strict_types=1);

$source = dirname(__DIR__).'/public/images/inhs-logo.png';
$outDir = dirname(__DIR__).'/public/images/pwa';

if (! is_file($source)) {
    fwrite(STDERR, "Missing logo: {$source}\n");
    exit(1);
}

if (! function_exists('imagecreatefrompng')) {
    fwrite(STDERR, "PHP GD extension is required.\n");
    exit(1);
}

if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Could not create {$outDir}\n");
    exit(1);
}

$src = imagecreatefrompng($source);
if (! $src) {
    fwrite(STDERR, "Could not read logo PNG.\n");
    exit(1);
}

imagealphablending($src, true);
imagesavealpha($src, true);

$srcW = imagesx($src);
$srcH = imagesy($src);

foreach ([192, 512] as $size) {
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);

    // Soft blue plate behind the crest for maskable/install icons.
    imagealphablending($canvas, true);
    $blue = imagecolorallocate($canvas, 13, 71, 161);
    $pad = (int) round($size * 0.06);
    imagefilledellipse($canvas, (int) ($size / 2), (int) ($size / 2), $size - $pad, $size - $pad, $blue);

    $inner = (int) round($size * 0.78);
    $offset = (int) round(($size - $inner) / 2);
    imagecopyresampled($canvas, $src, $offset, $offset, 0, 0, $inner, $inner, $srcW, $srcH);

    $path = "{$outDir}/icon-{$size}.png";
    imagepng($canvas, $path, 6);
    imagedestroy($canvas);
    echo "Wrote {$path}\n";
}

// Apple touch icon (180x180).
$appleSize = 180;
$apple = imagecreatetruecolor($appleSize, $appleSize);
imagealphablending($apple, false);
imagesavealpha($apple, true);
$transparent = imagecolorallocatealpha($apple, 0, 0, 0, 127);
imagefilledrectangle($apple, 0, 0, $appleSize, $appleSize, $transparent);
imagealphablending($apple, true);
$blue = imagecolorallocate($apple, 13, 71, 161);
imagefilledrectangle($apple, 0, 0, $appleSize, $appleSize, $blue);
$inner = (int) round($appleSize * 0.82);
$offset = (int) round(($appleSize - $inner) / 2);
imagecopyresampled($apple, $src, $offset, $offset, 0, 0, $inner, $inner, $srcW, $srcH);
$applePath = "{$outDir}/apple-touch-icon.png";
imagepng($apple, $applePath, 6);
imagedestroy($apple);
echo "Wrote {$applePath}\n";

imagedestroy($src);
echo "Done.\n";
