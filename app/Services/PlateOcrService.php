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
        imagefilter($src, IMG_FILTER_CONTRAST, -35);
        imagefilter($src, IMG_FILTER_BRIGHTNESS, 5);

        $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plate_enh_' . uniqid() . '.png';
        imagepng($src, $out);
        imagedestroy($src);

        return $out;
    }

    private function runTesseract(string $imagePath): array
    {
        $psm7 = $this->callTesseract($imagePath, 7); // single text line
        $psm8 = $this->callTesseract($imagePath, 8); // single word

        $c7 = $this->cleanPlate($psm7);
        $c8 = $this->cleanPlate($psm8);

        $candidates = array_filter([$c7, $c8], fn($p) => strlen($p) >= 3);

        $best = null;
        foreach ($candidates as $c) {
            if ($best === null || $this->plateScore($c) > $this->plateScore($best)) {
                $best = $c;
            }
        }

        return [$best, $psm7 ?: $psm8];
    }

    private function callTesseract(string $imagePath, int $psm): string
    {
        $cmd = sprintf(
            'tesseract %s stdout --psm %d -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 2>/dev/null',
            escapeshellarg($imagePath),
            $psm
        );
        return trim((string)@shell_exec($cmd));
    }

    private function cleanPlate(string $raw): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $raw));
        return substr($clean, 0, 10);
    }

    private function plateScore(string $plate): int
    {
        $score      = 0;
        $hasLetters = (bool)preg_match('/[A-Z]/', $plate);
        $hasDigits  = (bool)preg_match('/[0-9]/', $plate);

        if ($hasLetters && $hasDigits) $score += 10;

        $len = strlen($plate);
        if ($len >= 5 && $len <= 8)  $score += 5;
        elseif ($len >= 3 && $len <= 10) $score += 2;

        // Common patterns: 2–3 letters + 4 digits (SL / regional format)
        if (preg_match('/^[A-Z]{2,3}[0-9]{4}$/', $plate))      $score += 20;
        elseif (preg_match('/^[A-Z]{2,3}[0-9]{2,5}$/', $plate)) $score += 8;

        return $score;
    }
}
