<?php
declare(strict_types=1);
/**
 * Initial setup: fix passwords, generate seed images and PWA icons.
 * Run: php scripts/setup.php
 */

$baseDir = dirname(__DIR__);
$seedDir = $baseDir . '/public/uploads/seed';
$iconDir = $baseDir . '/public/assets/icons';

if (!is_dir($seedDir)) mkdir($seedDir, 0755, true);
if (!is_dir($iconDir)) mkdir($iconDir, 0755, true);

function createPlaceholderJpeg(string $path, int $width, int $height, string $label, string $bgColor = '2563eb'): void
{
    $img = imagecreatetruecolor($width, $height);
    $r = hexdec(substr($bgColor, 0, 2));
    $g = hexdec(substr($bgColor, 2, 2));
    $b = hexdec(substr($bgColor, 4, 2));
    $bg = imagecolorallocate($img, $r, $g, $b);
    imagefill($img, 0, 0, $bg);
    $white = imagecolorallocate($img, 255, 255, 255);
    $x = max(10, (int)(($width - strlen($label) * 9) / 2));
    imagestring($img, 5, $x, (int)($height / 2 - 8), $label, $white);
    imagejpeg($img, $path, 85);
    imagedestroy($img);
}

$seedFiles = [
    'doc1_ruhsat.jpg'    => ['Ruhsat', 'f59e0b'],
    'doc1_hasar.jpg'     => ['Hasar', 'dc2626'],
    'doc2_ehliyet.jpg'   => ['Ehliyet', '8b5cf6'],
    'doc2_tutanak.jpg'   => ['Tutanak', '3b82f6'],
    'doc2_hasar.jpg'     => ['Hasar', 'dc2626'],
    'doc3_ekspertiz.jpg' => ['Ekspertiz', '06b6d4'],
    'doc3_hasar.jpg'     => ['Hasar', 'dc2626'],
    'doc4_onarim.jpg'    => ['Onarim', '22c55e'],
    'doc4_hasar.jpg'     => ['Hasar', 'dc2626'],
    'doc5_onarim.jpg'    => ['Onarim', '22c55e'],
    'doc5_ruhsat.jpg'    => ['Ruhsat', 'f59e0b'],
    'doc6_diger.jpg'     => ['Diger', '64748b'],
];

foreach ($seedFiles as $filename => [$label, $color]) {
    $path = $seedDir . '/' . $filename;
    if (!file_exists($path)) {
        createPlaceholderJpeg($path, 400, 300, $label, $color);
        echo "Created seed: $filename\n";
    }
}

function createIcon(string $path, int $size): void
{
    $img = imagecreatetruecolor($size, $size);
    $navy = imagecolorallocate($img, 15, 23, 42);
    $blue = imagecolorallocate($img, 37, 99, 235);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $navy);
    imagefilledrectangle($img, (int)($size * .15), (int)($size * .15), (int)($size * .85), (int)($size * .85), $blue);
    $fontSize = 5;
    $text = 'OTO';
    $tw = imagefontwidth($fontSize) * strlen($text);
    imagestring($img, $fontSize, (int)(($size - $tw) / 2), (int)($size / 2 - 8), $text, $white);
    imagepng($img, $path);
    imagedestroy($img);
}

createIcon($iconDir . '/icon-192.png', 192);
createIcon($iconDir . '/icon-512.png', 512);
echo "Created PWA icons\n";

$hash = password_hash('1234', PASSWORD_BCRYPT);
echo "Password hash for 1234: $hash\n";

$configFile = $baseDir . '/config/config.local.php';
if (!file_exists($configFile)) {
    file_put_contents($configFile, "<?php\nreturn ['demo_password_hash' => '$hash'];\n");
}

echo "Setup complete.\n";
