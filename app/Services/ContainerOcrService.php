<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ContainerOcrService
{
    /**
     * Extract container data from an image file using Tesseract OCR.
     * Falls back to GD-preprocessed image for better accuracy.
     *
     * @return array{container_no: string|null, iso_type: string|null, tare_kg: int|null, max_gross_kg: int|null, raw_text: string}
     */
    public function extractFromImage(UploadedFile $image): array
    {
        $tmpPath  = $image->getPathname();
        $enhanced = $this->enhanceImage($tmpPath);
        $workPath = $enhanced ?? $tmpPath;

        $text = $this->runTesseract($workPath);

        if ($enhanced && file_exists($enhanced)) {
            @unlink($enhanced);
        }

        return [
            'container_no' => $this->extractContainerNo($text),
            'iso_type'     => $this->extractIsoType($text),
            'tare_kg'      => $this->extractTareKg($text),
            'max_gross_kg' => $this->extractMaxGrossKg($text),
            'raw_text'     => $text,
        ];
    }

    // ── Image preprocessing ──────────────────────────────────────────────────

    private function enhanceImage(string $path): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $mime = mime_content_type($path);
        $src  = match (true) {
            str_contains($mime, 'png')  => @imagecreatefrompng($path),
            str_contains($mime, 'gif')  => @imagecreatefromgif($path),
            default                     => @imagecreatefromjpeg($path),
        };

        if (!$src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);

        // Scale up small images — Tesseract works better with 300 DPI equivalent
        if ($w < 1200) {
            $scale  = 1200 / $w;
            $newW   = (int) ($w * $scale);
            $newH   = (int) ($h * $scale);
            $scaled = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($scaled, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($src);
            $src = $scaled;
            $w   = $newW;
            $h   = $newH;
        }

        // Greyscale
        imagefilter($src, IMG_FILTER_GRAYSCALE);
        // Boost contrast
        imagefilter($src, IMG_FILTER_BRIGHTNESS, 10);
        imagefilter($src, IMG_FILTER_CONTRAST, -20);
        // Sharpen
        $sharpen = [
            [0, -1, 0],
            [-1, 5, -1],
            [0, -1, 0],
        ];
        imageconvolution($src, $sharpen, 1, 0);

        $out = tempnam(sys_get_temp_dir(), 'ocr_') . '.png';
        imagepng($src, $out);
        imagedestroy($src);

        return $out;
    }

    // ── Tesseract invocation ─────────────────────────────────────────────────

    private function runTesseract(string $imagePath): string
    {
        // tesseract <image> stdout -l eng --psm 11 --oem 3
        // psm 11 = sparse text (good for container plates mixed with other content)
        // whitelist A-Z 0-9 space hyphen
        $escaped  = escapeshellarg($imagePath);
        $config   = escapeshellarg('tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 .-/\nKGT');
        $nullDev  = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';

        $cmd    = "tesseract {$escaped} stdout -l eng --psm 11 --oem 3 -c {$config} {$nullDev}";
        $output = shell_exec($cmd);

        if ($output === null || $output === '') {
            // Fallback: try without whitelist restriction for better coverage
            $cmd    = "tesseract {$escaped} stdout -l eng --psm 6 --oem 3 {$nullDev}";
            $output = shell_exec($cmd) ?? '';
        }

        return strtoupper(trim($output));
    }

    // ── Data extraction ──────────────────────────────────────────────────────

    private function extractContainerNo(string $text): ?string
    {
        // ISO 6346: 4 letters (owner+category) + 6 digits + 1 check digit
        // Pattern: AAAU 123456 7  (with optional spaces/dashes)
        if (preg_match('/\b([A-Z]{3}[UJZRLTE])[\s\-]?(\d{6})[\s\-]?(\d)\b/', $text, $m)) {
            $candidate = $m[1] . $m[2] . $m[3];
            if ($this->validateCheckDigit($candidate)) {
                return $candidate;
            }
            // Return without check digit validation — OCR may misread last digit
            return $m[1] . $m[2] . $m[3];
        }

        // Looser: 4 letters + 7 digits (no space)
        if (preg_match('/\b([A-Z]{4})(\d{7})\b/', $text, $m)) {
            return $m[1] . $m[2];
        }

        return null;
    }

    private function extractIsoType(string $text): ?string
    {
        // ISO 6346 size+type code: 4 chars, e.g. 22G1, 42G1, 45R1, 2200
        // Usually appears near SIZE/TYPE label or standalone
        if (preg_match('/(?:SIZE[\s\/]?TYPE|ISO|TYPE)[\s:]+([0-9]{2}[A-Z0-9]{2})\b/i', $text, $m)) {
            return $m[1];
        }
        // Common patterns like "22G1" or "45R1" anywhere in text
        if (preg_match('/\b([24][025][A-Z][0-9A-Z])\b/', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractTareKg(string $text): ?int
    {
        // "TARE: 2,200 KG" or "T: 2200KG" or "TARE 2200"
        if (preg_match('/TARE[\s:KG]*([0-9][0-9,\.]+)[\s]*K?G?/i', $text, $m)) {
            return (int) preg_replace('/[^0-9]/', '', $m[1]);
        }
        if (preg_match('/\bT[\s:]([0-9][0-9,\.]+)\s*KG\b/i', $text, $m)) {
            return (int) preg_replace('/[^0-9]/', '', $m[1]);
        }
        return null;
    }

    private function extractMaxGrossKg(string $text): ?int
    {
        // "MAX GROSS: 30,480 KG" or "MGW: 30480KG" or "GROSS 30480"
        if (preg_match('/(?:MAX\.?\s*GROSS|MGW|GROSS\s*WEIGHT)[\s:KG]*([0-9][0-9,\.]+)/i', $text, $m)) {
            return (int) preg_replace('/[^0-9]/', '', $m[1]);
        }
        if (preg_match('/\bG[\s:]([0-9][0-9,\.]+)\s*KG\b/i', $text, $m)) {
            return (int) preg_replace('/[^0-9]/', '', $m[1]);
        }
        return null;
    }

    // ── ISO 6346 check digit ─────────────────────────────────────────────────

    public function validateCheckDigit(string $containerNo): bool
    {
        if (strlen($containerNo) !== 11) {
            return false;
        }

        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $values  = [
            'A'=>10,'B'=>12,'C'=>13,'D'=>14,'E'=>15,'F'=>16,'G'=>17,'H'=>18,
            'I'=>19,'J'=>20,'K'=>21,'L'=>23,'M'=>24,'N'=>25,'O'=>26,'P'=>27,
            'Q'=>28,'R'=>29,'S'=>30,'T'=>31,'U'=>32,'V'=>34,'W'=>35,'X'=>36,
            'Y'=>37,'Z'=>38,
        ];

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $ch  = $containerNo[$i];
            $val = ctype_alpha($ch) ? ($values[$ch] ?? 0) : (int) $ch;
            $sum += $val * (2 ** $i);
        }

        $check = ($sum % 11) % 10;

        return (int) $containerNo[10] === $check;
    }
}
