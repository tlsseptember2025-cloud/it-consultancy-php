<?php

/**
 * Demo CAPTCHA Helper
 *
 * 6-digit visual CAPTCHA
 * - 1-minute expiry
 * - Maximum 5 attempts
 * - Code stored as a password hash
 * - CAPTCHA rendered as PNG
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/**
 * Generate a new 6-digit CAPTCHA.
 */
function generateDemoCaptcha(): void
{
    $code = '';

    for ($i = 0; $i < 6; $i++) {
        $code .= (string) random_int(0, 9);
    }

    $_SESSION['demo_captcha_hash'] = password_hash(
        $code,
        PASSWORD_DEFAULT
    );

    $_SESSION['demo_captcha_expires'] = time() + 60;
    $_SESSION['demo_captcha_attempts'] = 0;

    $_SESSION['demo_captcha_code'] = $code;
}


/**
 * Verify submitted CAPTCHA.
 */
function verifyDemoCaptcha(string $submittedCode): bool
{
    if (
        empty($_SESSION['demo_captcha_hash']) ||
        empty($_SESSION['demo_captcha_expires'])
    ) {
        return false;
    }

    if (time() > (int) $_SESSION['demo_captcha_expires']) {
        clearDemoCaptcha();
        return false;
    }

    if (
        isset($_SESSION['demo_captcha_attempts']) &&
        (int) $_SESSION['demo_captcha_attempts'] >= 5
    ) {
        clearDemoCaptcha();
        return false;
    }

    $_SESSION['demo_captcha_attempts']++;

    $submittedCode = trim($submittedCode);

    if (!preg_match('/^\d{6}$/', $submittedCode)) {
        return false;
    }

    if (
        password_verify(
            $submittedCode,
            $_SESSION['demo_captcha_hash']
        )
    ) {
        clearDemoCaptcha();
        return true;
    }

    return false;
}


/**
 * Clear CAPTCHA session data.
 */
function clearDemoCaptcha(): void
{
    unset(
        $_SESSION['demo_captcha_hash'],
        $_SESSION['demo_captcha_expires'],
        $_SESSION['demo_captcha_attempts'],
        $_SESSION['demo_captcha_code']
    );
}


/**
 * Find a usable TrueType font.
 */
function getDemoCaptchaFont(): ?string
{
    $possibleFonts = [
        dirname(__DIR__, 2) . '/assets/fonts/DejaVuSans-Bold.ttf',

        'C:/Windows/Fonts/arialbd.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/calibrib.ttf',

        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
    ];

    foreach ($possibleFonts as $font) {
        if (is_file($font) && is_readable($font)) {
            return $font;
        }
    }

    return null;
}


/**
 * Output CAPTCHA PNG image.
 */
function outputDemoCaptchaImage(): void
{
    if (empty($_SESSION['demo_captcha_code'])) {
        generateDemoCaptcha();
    }

    $code = $_SESSION['demo_captcha_code'];

    $width = 320;
    $height = 100;

    $image = imagecreatetruecolor($width, $height);

    /*
     * Light background.
     */
    $background = imagecolorallocate(
        $image,
        247,
        248,
        250
    );

    imagefill(
        $image,
        0,
        0,
        $background
    );

    /*
     * Border.
     */
    $border = imagecolorallocate(
        $image,
        150,
        155,
        165
    );

    imagerectangle(
        $image,
        0,
        0,
        $width - 1,
        $height - 1,
        $border
    );

    /*
     * Fine background noise.
     */
    for ($i = 0; $i < 1000; $i++) {

        $noiseColor = imagecolorallocate(
            $image,
            random_int(155, 225),
            random_int(155, 225),
            random_int(155, 225)
        );

        imagesetpixel(
            $image,
            random_int(3, $width - 4),
            random_int(3, $height - 4),
            $noiseColor
        );
    }

    /*
     * Faint background lines.
     */
    for ($i = 0; $i < 15; $i++) {

        $lineColor = imagecolorallocatealpha(
            $image,
            random_int(90, 170),
            random_int(90, 170),
            random_int(90, 170),
            55
        );

        imageline(
            $image,
            random_int(0, $width),
            random_int(0, $height),
            random_int(0, $width),
            random_int(0, $height),
            $lineColor
        );
    }

    /*
     * CAPTCHA colors.
     *
     * These intentionally have different darkness levels.
     * Some characters will be stronger than others.
     */
    $digitBaseColors = [
        [25, 55, 110],
        [80, 35, 90],
        [35, 90, 65],
        [110, 65, 25],
        [55, 55, 110],
        [70, 45, 75],
    ];

    $fontPath = getDemoCaptchaFont();

    /*
     * Start positions.
     */
    $x = 6;

    for ($i = 0; $i < 6; $i++) {

        $digit = $code[$i];

        /*
         * Different darkness for each digit.
         */
        $baseColor = $digitBaseColors[
            random_int(
                0,
                count($digitBaseColors) - 1
            )
        ];

        $fade = random_int(0, 55);

        $red = min(
            255,
            $baseColor[0] + $fade
        );

        $green = min(
            255,
            $baseColor[1] + $fade
        );

        $blue = min(
            255,
            $baseColor[2] + $fade
        );

        /*
         * Slight transparency on some digits.
         */
        $alpha = random_int(0, 45);

        if ($fontPath !== null) {

            /*
             * Slightly different size per digit.
             */
            $fontSize = random_int(42, 52);

            /*
             * Stronger rotation.
             */
            $rotation = random_int(-32, 32);

            /*
             * Individual transparent canvas.
             */
            $digitImage = imagecreatetruecolor(
                82,
                90
            );

            imagealphablending(
                $digitImage,
                false
            );

            imagesavealpha(
                $digitImage,
                true
            );

            $transparent = imagecolorallocatealpha(
                $digitImage,
                255,
                255,
                255,
                127
            );

            imagefill(
                $digitImage,
                0,
                0,
                $transparent
            );

            imagealphablending(
                $digitImage,
                true
            );

            /*
             * Create the slightly faded digit color.
             */
            $digitColor = imagecolorallocatealpha(
                $digitImage,
                $red,
                $green,
                $blue,
                $alpha
            );

            /*
             * Draw the digit.
             */
            imagettftext(
                $digitImage,
                $fontSize,
                0,
                random_int(6, 13),
                random_int(58, 68),
                $digitColor,
                $fontPath,
                $digit
            );

            /*
             * Rotate the digit.
             */
            $rotated = imagerotate(
                $digitImage,
                $rotation,
                $transparent
            );

            /*
             * Variable position.
             *
             * Some digits sit higher/lower.
             * Some slightly overlap neighbors.
             */
            $drawX = $x + random_int(-10, 3);
            $drawY = random_int(0, 18);

            imagecopy(
                $image,
                $rotated,
                $drawX,
                $drawY,
                0,
                0,
                imagesx($rotated),
                imagesy($rotated)
            );

            imagedestroy($digitImage);
            imagedestroy($rotated);

            /*
             * Tighter variable spacing.
             */
            $x += random_int(44, 51);

        } else {

            /*
             * Fallback if no TrueType font exists.
             */
            $fallbackColor = imagecolorallocate(
                $image,
                $red,
                $green,
                $blue
            );

            imagestring(
                $image,
                5,
                $x + 5,
                random_int(28, 45),
                $digit,
                $fallbackColor
            );

            $x += 48;
        }
    }

    /*
     * Stronger foreground interference.
     *
     * These cross over the digits rather than
     * staying only in the background.
     */
    for ($i = 0; $i < 9; $i++) {

        $lineColor = imagecolorallocatealpha(
            $image,
            random_int(70, 155),
            random_int(70, 155),
            random_int(70, 155),
            random_int(15, 65)
        );

        imageline(
            $image,
            random_int(-20, 50),
            random_int(0, $height),
            random_int($width - 50, $width + 20),
            random_int(0, $height),
            $lineColor
        );
    }

    /*
     * Curved interference.
     */
    for ($i = 0; $i < 5; $i++) {

        $arcColor = imagecolorallocatealpha(
            $image,
            random_int(80, 160),
            random_int(80, 160),
            random_int(80, 160),
            random_int(20, 70)
        );

        imagearc(
            $image,
            random_int(10, $width - 10),
            random_int(10, $height - 10),
            random_int(90, 210),
            random_int(35, 90),
            random_int(0, 180),
            random_int(181, 360),
            $arcColor
        );
    }

    /*
     * A few larger noise marks.
     */
    for ($i = 0; $i < 35; $i++) {

        $noiseColor = imagecolorallocatealpha(
            $image,
            random_int(80, 170),
            random_int(80, 170),
            random_int(80, 170),
            random_int(25, 75)
        );

        imagefilledellipse(
            $image,
            random_int(0, $width),
            random_int(0, $height),
            random_int(1, 4),
            random_int(1, 4),
            $noiseColor
        );
    }

    /*
     * Output PNG.
     */
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    imagepng($image);

    imagedestroy($image);

    exit;
}