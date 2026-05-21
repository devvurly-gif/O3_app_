<?php
/**
 * Convert social media SVG files to PNG using Imagick or GD
 */

$socialDir = __DIR__ . '/public/images/social';
$svgFiles = glob($socialDir . '/*.svg');

echo "Found " . count($svgFiles) . " SVG files to convert\n\n";

foreach ($svgFiles as $svgFile) {
    $pngFile = str_replace('.svg', '.png', $svgFile);
    $basename = basename($svgFile);

    // Read SVG content
    $svgContent = file_get_contents($svgFile);

    // Extract dimensions from SVG
    preg_match('/width="(\d+)"/', $svgContent, $wMatch);
    preg_match('/height="(\d+)"/', $svgContent, $hMatch);
    $width = (int)($wMatch[1] ?? 1080);
    $height = (int)($hMatch[1] ?? 1080);

    echo "Converting: $basename ({$width}x{$height})... ";

    // Try Imagick first
    if (extension_loaded('imagick')) {
        try {
            $im = new Imagick();
            $im->setResolution(150, 150);
            $im->readImageBlob($svgContent);
            $im->setImageFormat('png');
            $im->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
            $im->writeImage($pngFile);
            $im->destroy();
            echo "OK (Imagick)\n";
            continue;
        } catch (Exception $e) {
            echo "Imagick failed: " . $e->getMessage() . "\n";
        }
    }

    // Fallback: create PNG with GD using the gradient colors
    $img = imagecreatetruecolor($width, $height);

    // Create gradient background
    $color1R = 0x1e; $color1G = 0x3a; $color1B = 0x8a; // #1e3a8a
    $color2R = 0x25; $color2G = 0x63; $color2B = 0xeb; // #2563eb

    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int)($color1R + ($color2R - $color1R) * $ratio);
        $g = (int)($color1G + ($color2G - $color1G) * $ratio);
        $b = (int)($color1B + ($color2B - $color1B) * $ratio);
        $color = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $width - 1, $y, $color);
    }

    // Add top accent bar
    $gold = imagecolorallocate($img, 0xf5, 0x9e, 0x0b);
    imagefilledrectangle($img, 0, 0, $width, 5, $gold);

    // Add bottom accent bar
    imagefilledrectangle($img, 0, $height - 6, $width, $height, $gold);

    $white = imagecolorallocate($img, 255, 255, 255);
    $whiteAlpha = imagecolorallocatealpha($img, 255, 255, 255, 50);
    $yellow = imagecolorallocate($img, 0xfb, 0xbf, 0x24);

    // O3 Logo circle
    $logoX = ($width == 1080) ? (int)($width / 2) : 100;
    $logoY = ($width == 1080) ? (int)($height * 0.15) : 70;
    $logoR = ($width == 1080) ? 50 : 35;
    imagefilledellipse($img, $logoX, $logoY, $logoR * 2, $logoR * 2, $white);

    // O3 text on logo
    $blue = imagecolorallocate($img, 0x25, 0x63, 0xeb);
    $fontFile = null;
    $fontPaths = [
        'C:/Windows/Fonts/arialbd.ttf',
        'C:/Windows/Fonts/arial.ttf',
    ];
    foreach ($fontPaths as $fp) {
        if (file_exists($fp)) {
            $fontFile = $fp;
            break;
        }
    }

    if ($fontFile) {
        $logoFontSize = ($width == 1080) ? 30 : 20;
        $bbox = imagettfbbox($logoFontSize, 0, $fontFile, "O3");
        $textW = $bbox[2] - $bbox[0];
        $textH = $bbox[1] - $bbox[7];
        imagettftext($img, $logoFontSize, 0, $logoX - (int)($textW / 2), $logoY + (int)($textH / 2), $blue, $fontFile, "O3");

        // Determine which visual this is based on filename
        $isInstagram = ($width == 1080);

        // Main title varies by file
        if (strpos($basename, 'launch') !== false) {
            imagettftext($img, 38, 0, 90, (int)($height * 0.4), $white, $fontFile, "Vous gerez encore");
            imagettftext($img, 38, 0, 90, (int)($height * 0.47), $white, $fontFile, "votre entreprise");
            imagettftext($img, 38, 0, 90, (int)($height * 0.54), $yellow, $fontFile, "avec Excel ?");

            // CTA button
            imagefilledroundrect($img, 90, (int)($height * 0.78), 430, (int)($height * 0.87), 28, $gold);
            $darkBlue = imagecolorallocate($img, 0x1e, 0x3a, 0x8a);
            imagettftext($img, 16, 0, 140, (int)($height * 0.84), $darkBlue, $fontFile, "Essai gratuit 14 jours");
        } elseif (strpos($basename, 'citation') !== false) {
            $italicFont = str_replace('arialbd', 'ariali', $fontFile);
            if (!file_exists($italicFont)) $italicFont = $fontFile;
            imagettftext($img, 28, 0, (int)($width * 0.15), (int)($height * 0.38), $white, $italicFont, "La meilleure facon de");
            imagettftext($img, 28, 0, (int)($width * 0.15), (int)($height * 0.43), $white, $italicFont, "predire l'avenir de");
            imagettftext($img, 28, 0, (int)($width * 0.15), (int)($height * 0.48), $white, $italicFont, "votre entreprise,");
            imagettftext($img, 28, 0, (int)($width * 0.15), (int)($height * 0.56), $yellow, $italicFont, "c'est de bien gerer");
            imagettftext($img, 28, 0, (int)($width * 0.15), (int)($height * 0.61), $yellow, $italicFont, "son present.");

            // Separator line
            imagefilledrectangle($img, (int)($width * 0.44), (int)($height * 0.67), (int)($width * 0.56), (int)($height * 0.67) + 3, $gold);

            // Brand text
            $semiWhite = imagecolorallocatealpha($img, 255, 255, 255, 40);
            imagettftext($img, 16, 0, (int)($width * 0.33), (int)($height * 0.84), $semiWhite, $fontFile, "O3 App - o3app.ma");
        } elseif (strpos($basename, 'pricing') !== false) {
            imagettftext($img, 34, 0, (int)($width * 0.18), (int)($height * 0.26), $white, $fontFile, "Quel plan O3 App");
            imagettftext($img, 34, 0, (int)($width * 0.18), (int)($height * 0.32), $yellow, $fontFile, "est fait pour vous ?");

            // Price boxes
            $boxY = (int)($height * 0.42);
            $boxH = (int)($height * 0.35);

            // Starter
            $semiWhiteBg = imagecolorallocatealpha($img, 255, 255, 255, 110);
            imagefilledroundrect($img, 100, $boxY, 350, $boxY + $boxH, 16, $semiWhiteBg);
            imagettftext($img, 14, 0, 175, $boxY + 40, $whiteAlpha, $fontFile, "STARTER");
            imagettftext($img, 32, 0, 180, $boxY + 90, $white, $fontFile, "499");
            imagettftext($img, 12, 0, 170, $boxY + 115, $whiteAlpha, $fontFile, "MAD / mois");

            // Business (highlighted)
            $goldBg = imagecolorallocatealpha($img, 245, 158, 11, 100);
            imagefilledroundrect($img, 390, $boxY - 20, 690, $boxY + $boxH + 10, 16, $goldBg);
            imagettftext($img, 14, 0, 490, $boxY + 30, $whiteAlpha, $fontFile, "BUSINESS");
            imagettftext($img, 32, 0, 495, $boxY + 80, $white, $fontFile, "999");
            imagettftext($img, 12, 0, 480, $boxY + 105, $whiteAlpha, $fontFile, "MAD / mois");

            // Enterprise
            imagefilledroundrect($img, 730, $boxY, 980, $boxY + $boxH, 16, $semiWhiteBg);
            imagettftext($img, 14, 0, 790, $boxY + 40, $whiteAlpha, $fontFile, "ENTERPRISE");
            imagettftext($img, 32, 0, 795, $boxY + 90, $white, $fontFile, "1999");
            imagettftext($img, 12, 0, 790, $boxY + 115, $whiteAlpha, $fontFile, "MAD / mois");

        } elseif (strpos($basename, 'comparatif') !== false) {
            imagettftext($img, 30, 0, (int)($width * 0.2), (int)($height * 0.2), $white, $fontFile, "Combien coute un ERP");
            imagettftext($img, 30, 0, (int)($width * 0.35), (int)($height * 0.26), $yellow, $fontFile, "au Maroc ?");

            // Price rows
            $red = imagecolorallocate($img, 0xef, 0x44, 0x44);
            $orange = imagecolorallocate($img, 0xf9, 0x73, 0x16);
            $green = imagecolorallocate($img, 0x22, 0xc5, 0x5e);

            imagettftext($img, 18, 0, 200, 380, $whiteAlpha, $fontFile, "SAP Business One");
            imagettftext($img, 18, 0, 700, 380, $red, $fontFile, "15,000+ MAD");

            imagettftext($img, 18, 0, 200, 440, $whiteAlpha, $fontFile, "Odoo Enterprise");
            imagettftext($img, 18, 0, 700, 440, $orange, $fontFile, "5,000+ MAD");

            imagettftext($img, 18, 0, 200, 500, $whiteAlpha, $fontFile, "Sage");
            imagettftext($img, 18, 0, 700, 500, $yellow, $fontFile, "3,000+ MAD");

            // O3 highlighted
            $greenBg = imagecolorallocatealpha($img, 34, 197, 94, 100);
            imagefilledroundrect($img, 150, 530, 930, 610, 12, $greenBg);
            imagettftext($img, 20, 0, 200, 580, $white, $fontFile, "O3 App Starter");
            imagettftext($img, 24, 0, 680, 580, $green, $fontFile, "499 MAD");

            // Savings CTA
            $darkBlue = imagecolorallocate($img, 0x1e, 0x3a, 0x8a);
            imagefilledroundrect($img, 280, 660, 800, 730, 30, $gold);
            imagettftext($img, 20, 0, 360, 702, $darkBlue, $fontFile, "Jusqu'a 30x moins cher !");

        } elseif (strpos($basename, 'offre') !== false) {
            // Offre badge
            $darkBlue = imagecolorallocate($img, 0x1e, 0x3a, 0x8a);
            imagefilledroundrect($img, 290, 80, 790, 140, 30, $gold);
            imagettftext($img, 20, 0, 370, 118, $darkBlue, $fontFile, "OFFRE DE LANCEMENT");

            imagettftext($img, 32, 0, (int)($width * 0.15), 370, $white, $fontFile, "Les 50 premiers inscrits");
            imagettftext($img, 32, 0, (int)($width * 0.2), 425, $yellow, $fontFile, "beneficient de :");

            $green = imagecolorallocate($img, 0x22, 0xc5, 0x5e);
            imagettftext($img, 18, 0, 240, 530, $white, $fontFile, "1 mois GRATUIT");
            imagettftext($img, 18, 0, 240, 610, $white, $fontFile, "Configuration assistee offerte");
            imagettftext($img, 18, 0, 240, 690, $white, $fontFile, "Support prioritaire 3 mois");

            // CTA
            imagefilledroundrect($img, 240, 800, 840, 870, 35, $gold);
            imagettftext($img, 22, 0, 370, 843, $darkBlue, $fontFile, "S'inscrire maintenant");

        } elseif (strpos($basename, 'carousel-slide1') !== false) {
            imagettftext($img, 80, 0, (int)($width * 0.42), (int)($height * 0.38), $yellow, $fontFile, "5");
            imagettftext($img, 34, 0, (int)($width * 0.18), (int)($height * 0.47), $white, $fontFile, "raisons d'abandonner");
            imagettftext($img, 34, 0, (int)($width * 0.2), (int)($height * 0.52), $white, $fontFile, "Excel pour gerer");
            imagettftext($img, 34, 0, (int)($width * 0.22), (int)($height * 0.57), $yellow, $fontFile, "votre entreprise");
        } elseif (strpos($basename, 'pos') !== false) {
            imagettftext($img, 40, 0, 90, (int)($height * 0.32), $white, $fontFile, "Votre caisse");
            imagettftext($img, 40, 0, 90, (int)($height * 0.42), $yellow, $fontFile, "dans votre poche.");

            imagefilledroundrect($img, 90, (int)($height * 0.82), 430, (int)($height * 0.91), 28, $gold);
            $darkBlue = imagecolorallocate($img, 0x1e, 0x3a, 0x8a);
            imagettftext($img, 16, 0, 160, (int)($height * 0.88), $darkBlue, $fontFile, "Decouvrir le POS");
        } elseif (strpos($basename, 'stock') !== false) {
            imagettftext($img, 40, 0, 90, (int)($height * 0.3), $white, $fontFile, "Rupture de stock ?");
            imagettftext($img, 40, 0, 90, (int)($height * 0.4), $yellow, $fontFile, "Plus jamais.");

            imagefilledroundrect($img, 90, (int)($height * 0.82), 430, (int)($height * 0.91), 28, $gold);
            $darkBlue = imagecolorallocate($img, 0x1e, 0x3a, 0x8a);
            imagettftext($img, 16, 0, 130, (int)($height * 0.88), $darkBlue, $fontFile, "Essai gratuit 14 jours");
        } elseif (strpos($basename, 'linkedin') !== false) {
            imagettftext($img, 36, 0, 100, 200, $white, $fontFile, "Les PME marocaines meritent");
            imagettftext($img, 36, 0, 100, 255, $yellow, $fontFile, "des outils a la hauteur");
            imagettftext($img, 36, 0, 100, 310, $yellow, $fontFile, "de leurs ambitions.");

            $semiWhite2 = imagecolorallocatealpha($img, 255, 255, 255, 30);
            imagefilledroundrect($img, 100, 530, 420, 578, 24, $semiWhite2);
            imagettftext($img, 14, 0, 135, 560, $white, $fontFile, "Ne au Maroc, pour le Maroc");
        } else {
            // Generic
            imagettftext($img, 34, 0, (int)($width * 0.1), (int)($height * 0.45), $white, $fontFile, "O3 App");
            imagettftext($img, 20, 0, (int)($width * 0.1), (int)($height * 0.55), $yellow, $fontFile, "o3app.ma");
        }
    }

    // Save PNG
    imagepng($img, $pngFile, 5);
    imagedestroy($img);

    $size = round(filesize($pngFile) / 1024);
    echo "OK (GD, {$size}KB)\n";
}

echo "\nDone! All PNGs created in: $socialDir\n";

// Helper function for rounded rectangles
function imagefilledroundrect($img, $x1, $y1, $x2, $y2, $radius, $color) {
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}
