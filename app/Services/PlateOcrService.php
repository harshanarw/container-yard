<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class PlateOcrService
{
    public function extractFromImage(UploadedFile $image): array
    {
        $tmpPath  = $image->getPathname();
        $enhanced = $this->enhanceImage($tmpPath);

        if (!$enhanced) {
            $mime = mime_content_type($tmpPath) ?: 'image/jpeg';
            $ext  = str_contains($mime, 'png') ? 'png' : 'jpg';
            $copy = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plate_' . uniqid() . '.' . $ext;
            if (@copy($tmpPath, $copy)) {
                $enhanced = $copy;
            }
        }

        $workPath = $enhanced ?? $tmpPath;
        [$plateNo, $rawText] = $this->runTesseract($workPath);

        if ($enhanced && file_exists($enhanced)) {
            @unlink($enhanced);
        }

        return [
            'plate_no' => $plateNo,
            'raw_text' => $rawText,
        ];
    }

    private function enhanceImage(string $path): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $info = @getimagesize($path);
        if (!$info) return null;

        $src = match($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default        => null,
        };
        if (!$src) return null;

        $w = imagesx($src);
        $h = imagesy($src);

        // Scale up to at least 1200px wide — plates need high-res for Tesseract accuracy
        if ($w < 1200) {
            $scale = 1200 / $w;
            $newW  = (int)round($w * $scale);
            $newH  = (int)round($h * $scale);
            $dst   = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        imagefilter($src, IMG_FILTER_GRAYSCALE);
        imagefilter($src, IMG_FILTER_BRIGHTNESS, 10);
        imagefilter($src, IMG_FILTER_CONTRAST, -25);
        // Sharpen edges so closed shapes (0) stay distinct from open ones (6, D, etc.)
        imageconvolution($src, [[0,-1,0],[-1,5,-1],[0,-1,0]], 1, 0);

        $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plate_enh_' . uniqid() . '.png';
        imagepng($src, $out);
        imagedestroy($src);

        return $out;
    }

    private function runTesseract(string $imagePath): array
    {
        $candidates = [];

        // ── Full-image passes ───────────────────────────────────────────────
        // Kept as fallback; will score low if emblem noise pollutes the text.
        foreach ([6, 7, 8] as $psm) {
            $c = $this->cleanPlate($this->callTesseract($imagePath, $psm));
            if (strlen($c) >= 3) $candidates[] = $c;
        }

        if (!extension_loaded('gd')) {
            return $this->pickBest($candidates);
        }

        // ── GD crop passes ──────────────────────────────────────────────────
        // SL plates put an emblem in the top-left ≈35% of the image.
        // Cropping it out lets PSM 6 read the two character rows cleanly.

        // Right-side crop (x: 35–100%, full height) — removes emblem entirely.
        // PSM 6 reads the block as "QL\n9904" → cleaned: "QL9904"
        $rightCrop    = $this->cropImage($imagePath, 0.35, 0.00, 1.0, 1.00);
        // Top-right crop (x: 35–100%, y: 0–56%) — letter row only
        $topRightCrop = $this->cropImage($imagePath, 0.35, 0.00, 1.0, 0.56);
        // Bottom crop (y: 50–100%) — digit row only
        $btmCrop      = $this->cropImage($imagePath, 0.00, 0.50, 1.0, 1.00);

        if ($rightCrop) {
            foreach ([6, 7] as $psm) {
                $c = $this->cleanPlate($this->callTesseract($rightCrop, $psm));
                if (strlen($c) >= 3) $candidates[] = $c;
            }
        }

        if ($btmCrop) {
            $c = $this->cleanPlate($this->callTesseract($btmCrop, 7));
            if (strlen($c) >= 3) $candidates[] = $c;
        }

        // Combination pass: letters extracted from top-right + digits from bottom.
        // Handles "QL" + "9904" → "QL9904" when PSM 6 block-read still fails.
        if ($topRightCrop && $btmCrop) {
            foreach ([7, 8] as $psm) {
                $letters = preg_replace('/[0-9]/',   '', $this->cleanPlate($this->callTesseract($topRightCrop, $psm)));
                $digits  = preg_replace('/[A-Z]/i', '', $this->cleanPlate($this->callTesseract($btmCrop,      $psm)));
                if (strlen($letters) >= 2 && strlen($digits) >= 4) {
                    $candidates[] = substr($letters, 0, 4) . substr($digits, 0, 4);
                }
            }
        }

        foreach ([$rightCrop, $topRightCrop, $btmCrop] as $f) {
            if ($f) @unlink($f);
        }

        return $this->pickBest($candidates);
    }

    private function cropImage(string $imagePath, float $x1, float $y1, float $x2, float $y2): ?string
    {
        $info = @getimagesize($imagePath);
        if (!$info) return null;

        $src = match($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($imagePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($imagePath),
            default        => null,
        };
        if (!$src) return null;

        $w   = imagesx($src);
        $h   = imagesy($src);
        $cx1 = (int)round($x1 * $w);
        $cy1 = (int)round($y1 * $h);
        $cw  = (int)round(($x2 - $x1) * $w);
        $ch  = (int)round(($y2 - $y1) * $h);

        if ($cw < 10 || $ch < 10) { imagedestroy($src); return null; }

        // Upscale narrow crops so Tesseract has enough pixels to work with
        $scale = max(1.0, 600.0 / $cw);
        $newW  = (int)round($cw * $scale);
        $newH  = (int)round($ch * $scale);

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, $cx1, $cy1, $newW, $newH, $cw, $ch);
        imagedestroy($src);

        $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plate_crop_' . uniqid() . '.png';
        imagepng($dst, $out);
        imagedestroy($dst);

        return (file_exists($out) && filesize($out) > 0) ? $out : null;
    }

    private function pickBest(array $candidates): array
    {
        $valid = array_values(array_filter($candidates, fn($c) => strlen($c) >= 3));
        $best  = null;
        foreach ($valid as $c) {
            if ($best === null || $this->plateScore($c) > $this->plateScore($best)) {
                $best = $c;
            }
        }
        return [$best, implode(' | ', $valid)];
    }

    private function callTesseract(string $imagePath, int $psm): string
    {
        $nullDev = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
        $cmd = sprintf(
            '%s %s stdout --psm %d -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 %s',
            $this->tesseractBin(),
            escapeshellarg($imagePath),
            $psm,
            $nullDev
        );
        return trim((string)@shell_exec($cmd));
    }

    private function tesseractBin(): string
    {
        return escapeshellarg(env('TESSERACT_PATH', 'tesseract'));
    }

    private function cleanPlate(string $raw): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $raw));
        return substr($clean, 0, 12);
    }

    private function plateScore(string $plate): int
    {
        $score      = 0;
        $hasLetters = (bool)preg_match('/[A-Z]/', $plate);
        $hasDigits  = (bool)preg_match('/[0-9]/', $plate);

        if ($hasLetters && $hasDigits) $score += 10;

        $len = strlen($plate);
        if ($len >= 5 && $len <= 9)   $score += 5;
        elseif ($len >= 3 && $len <= 12) $score += 2;

        // 4-letter prefix — new SL format: SPQL9904
        if (preg_match('/^[A-Z]{4}[0-9]{4}$/', $plate))           $score += 20;
        // 2–3 letter prefix — old SL / regional format: WQR1234
        elseif (preg_match('/^[A-Z]{2,3}[0-9]{4}$/', $plate))     $score += 20;
        elseif (preg_match('/^[A-Z]{2,4}[0-9]{2,5}$/', $plate))   $score += 8;

        return $score;
    }
}
