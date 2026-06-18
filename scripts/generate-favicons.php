<?php
/**
 * Regenerate favicon assets from assets/images/logonew.png
 * Usage: php scripts/generate-favicons.php
 */

$root = dirname(__DIR__);
$logoPath = $root . '/assets/images/logonew.png';
$outDir = $root . '/assets/images/favicons/';

if (!is_file($logoPath)) {
    fwrite(STDERR, "Logo not found: {$logoPath}\n");
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "PHP GD extension is required.\n");
    exit(1);
}

function resizeLogoToPng(string $logoPath, string $outputPath, int $size): void
{
    $source = imagecreatefrompng($logoPath);
    if (!$source) {
        throw new RuntimeException('Failed to read logo PNG.');
    }

    $target = imagecreatetruecolor($size, $size);
    imagealphablending($target, false);
    imagesavealpha($target, true);

    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefilledrectangle($target, 0, 0, $size, $size, $transparent);

    $srcW = imagesx($source);
    $srcH = imagesy($source);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $size, $size, $srcW, $srcH);
    imagepng($target, $outputPath);

    imagedestroy($source);
    imagedestroy($target);
}

function writeIcoFromPngs(array $pngPaths, string $outputPath): void
{
    $images = [];
    foreach ($pngPaths as $pngPath) {
        $im = imagecreatefrompng($pngPath);
        if (!$im) {
            throw new RuntimeException('Failed to read PNG for ICO: ' . $pngPath);
        }
        $images[] = $im;
    }

    $count = count($images);
    $offset = 6 + (16 * $count);
    $entries = [];

    foreach ($images as $im) {
        $width = imagesx($im);
        $height = imagesy($im);

        $xor = '';
        for ($y = $height - 1; $y >= 0; $y--) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($im, $x, $y);
                $alpha = ($color >> 24) & 0x7F;
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;
                $xor .= chr($blue) . chr($green) . chr($red) . chr($alpha < 127 ? 0 : 255);
            }
            $xor .= str_repeat("\0", (4 - ($width % 4)) % 4);
        }

        $and = '';
        for ($y = $height - 1; $y >= 0; $y--) {
            $bits = '';
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($im, $x, $y);
                $alpha = ($color >> 24) & 0x7F;
                $bits .= ($alpha < 127) ? '1' : '0';
                if (strlen($bits) === 8) {
                    $and .= chr(bindec($bits));
                    $bits = '';
                }
            }
            if ($bits !== '') {
                $and .= chr(bindec(str_pad($bits, 8, '0', STR_PAD_RIGHT)));
            }
            $rowBytes = (int) ceil($width / 8);
            $and .= str_repeat("\0", (4 - ($rowBytes % 4)) % 4);
        }

        $header = pack('VvvVVVVvvVV', 40, $width, $height * 2, 1, 32, 0, 0, 0, 0, 0, 0);
        $data = $header . $xor . $and;

        $entries[] = [
            'width' => $width >= 256 ? 0 : $width,
            'height' => $height >= 256 ? 0 : $height,
            'size' => strlen($data),
            'offset' => $offset,
            'data' => $data,
        ];
        $offset += strlen($data);
    }

    $ico = pack('vvv', 0, 1, $count);
    foreach ($entries as $entry) {
        $ico .= pack('CCCCvvVV', $entry['width'], $entry['height'], 0, 0, 1, 32, $entry['size'], $entry['offset']);
    }
    foreach ($entries as $entry) {
        $ico .= $entry['data'];
    }

    file_put_contents($outputPath, $ico);

    foreach ($images as $im) {
        imagedestroy($im);
    }
}

$sizes = [
    'favicon-16x16.png' => 16,
    'favicon-32x32.png' => 32,
    'apple-touch-icon.png' => 180,
    'android-chrome-192x192.png' => 192,
    'android-chrome-512x512.png' => 512,
];

foreach ($sizes as $filename => $size) {
    resizeLogoToPng($logoPath, $outDir . $filename, $size);
    echo "Wrote {$filename}\n";
}

writeIcoFromPngs([
    $outDir . 'favicon-16x16.png',
    $outDir . 'favicon-32x32.png',
], $outDir . 'favicon.ico');
echo "Wrote favicon.ico\n";

copy($outDir . 'favicon.ico', $root . '/favicon.ico');
echo "Copied favicon.ico to site root\n";

echo "Done.\n";
