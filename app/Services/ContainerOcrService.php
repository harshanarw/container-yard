<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ContainerOcrService
{
    /**
     * Extract container data from an image file using Tesseract OCR.
     *
     * @return array{container_no: string|null, iso_type: string|null, tare_kg: int|null, max_gross_kg: int|null, raw_text: string}
     */
    public function extractFromImage(UploadedFile $image): array
    {
        $tmpPath  = $image->getPathname();
        $enhanced = $this->enhanceImage($tmpPath);

        // If GD enhancement failed, ensure Tesseract gets a file with a proper image
        // extension — PHP upload temp files on Windows use .tmp which Tesseract may reject.
        $ownsCopy = false;
        if (!$enhanced) {
            $mime    = mime_content_type($tmpPath) ?: 'image/jpeg';
            $ext     = str_contains($mime, 'png') ? 'png' : 'jpg';
            $copy    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . uniqid() . '.' . $ext;
            if (@copy($tmpPath, $copy)) {
                $enhanced  = $copy;
                $ownsCopy  = true;
            }
        }

        $workPath = $enhanced ?? $tmpPath;
        $text     = $this->runTesseract($workPath);

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

        // Scale up small images — Tesseract works better at ~300 DPI equivalent
        if ($w < 1200) {
            $scale  = 1200 / $w;
            $newW   = (int) ($w * $scale);
            $newH   = (int) ($h * $scale);
            $scaled = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($scaled, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($src);
            $src = $scaled;
        }

        imagefilter($src, IMG_FILTER_GRAYSCALE);
        imagefilter($src, IMG_FILTER_BRIGHTNESS, 10);
        imagefilter($src, IMG_FILTER_CONTRAST, -20);
        imageconvolution($src, [[0,-1,0],[-1,5,-1],[0,-1,0]], 1, 0);

        // Write to a proper .png temp file and verify it was created
        $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . uniqid() . '.png';
        $ok  = imagepng($src, $out);
        imagedestroy($src);

        if (!$ok || !file_exists($out)) {
            return null;
        }

        return $out;
    }

    // ── Tesseract invocation ─────────────────────────────────────────────────

    private function runTesseract(string $imagePath): string
    {
        $escaped = escapeshellarg($imagePath);
        $nullDev = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';

        // PSM 11 = sparse text, good for container plates with mixed content
        $cmd    = "tesseract {$escaped} stdout -l eng --psm 11 --oem 3 {$nullDev}";
        $output = shell_exec($cmd);

        if ($output === null || trim($output) === '') {
            // Fallback: PSM 6 = single uniform block of text
            $cmd    = "tesseract {$escaped} stdout -l eng --psm 6 --oem 3 {$nullDev}";
            $output = shell_exec($cmd) ?? '';
        }

        return strtoupper(trim($output));
    }

    // ── Container number extraction ──────────────────────────────────────────

    private function extractContainerNo(string $text): ?string
    {
        // Collapse to alphanumeric only — removes spaces that OCR inserts between
        // the prefix, serial, and boxed check digit on container plates.
        // e.g. "SEGU 111192 3" and "SEGU 1111923" and "SEGU1111923" all → "SEGU11111923"
        $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper($text));

        // Find every 4-letter prefix (3 owner + 1 category) followed by 6–9 digits,
        // then slide a 7-digit window over those digits and validate the check digit.
        // This handles OCR merging the serial and boxed check as 8 consecutive digits.
        if (preg_match_all('/([A-Z]{3}[UJZRLTE])(\d{6,9})/', $compact, $sets, PREG_SET_ORDER)) {
            foreach ($sets as $set) {
                $prefix = $set[1];
                $digits = $set[2];
                $len    = strlen($digits);
                // Try each 7-digit window; check digit is last in each candidate
                for ($offset = 0; $offset <= $len - 7; $offset++) {
                    $candidate = $prefix . substr($digits, $offset, 7);
                    if ($this->validateCheckDigit($candidate)) {
                        return $candidate;
                    }
                }
                // No window validated — return the rightmost 7 digits as best guess
                // (check digit is usually printed last / rightmost)
                return $prefix . substr($digits, -7);
            }
        }

        // Broader fallback: any 4 letters + 7 digits
        if (preg_match('/([A-Z]{4})(\d{6,9})/', $compact, $m)) {
            $digits = $m[2];
            $len    = strlen($digits);
            for ($offset = 0; $offset <= $len - 7; $offset++) {
                $candidate = $m[1] . substr($digits, $offset, 7);
                if ($this->validateCheckDigit($candidate)) {
                    return $candidate;
                }
            }
            return $m[1] . substr($digits, -7);
        }

        return null;
    }

    // ── ISO type code extraction ─────────────────────────────────────────────

    private function extractIsoType(string $text): ?string
    {
        // Labelled: "SIZE/TYPE 22G1" or "ISO 22G1" or "TYPE: 45R1"
        if (preg_match('/(?:SIZE[\s\/]?TYPE|ISO|TYPE)\s*[:\-]?\s*([0-9]{2}[A-Z0-9]{2})\b/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        // Standalone code on its own line or surrounded by whitespace: 22G1, 45G1, 45R1
        if (preg_match('/\b([24][025][A-Z][0-9A-Z])\b/', $text, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    // ── Weight extraction ────────────────────────────────────────────────────

    private function extractTareKg(string $text): ?int
    {
        // Handles: "TARE  2 180 KGS", "TARE: 2,180 KG", "TARE 2.180 KG", "T: 2180KG"
        // Space/comma/dot are all valid thousands separators on container plates.
        if (preg_match('/\bTARE\b[\s:]*([0-9][0-9\s,\.]+)\s*KGS?/i', $text, $m)) {
            return (int) preg_replace('/[^0-9]/', '', $m[1]);
        }
        if (preg_match('/\bT\s*:\s*([0-9][0-9\s,\.]+)\s*KGS?\b/i', $text, $m)) {
            return (int) preg_replace('/[^0-9]/', '', $m[1]);
        }
        return null;
    }

    private function extractMaxGrossKg(string $text): ?int
    {
        // Handles: "MGW  30 480 KGS", "MAX. GROSS 30,480 KG", "MAX GROSS: 30.480 KG"
        if (preg_match('/\bMGW\b[\s:]*([0-9][0-9\s,\.]+)\s*KGS?/i', $text, $m)) {
            return (int) preg_replace('/[^0-9]/', '', $m[1]);
        }
        if (preg_match('/\bMAX\.?\s*GROSS\b[\s:]*([0-9][0-9\s,\.]+)\s*KGS?/i', $text, $m)) {
            return (int) preg_replace('/[^0-9]/', '', $m[1]);
        }
        if (preg_match('/\bGROSS\s*WEIGHT\b[\s:]*([0-9][0-9\s,\.]+)\s*KGS?/i', $text, $m)) {
            return (int) preg_replace('/[^0-9]/', '', $m[1]);
        }
        return null;
    }

    // ── ISO 6346 check digit validation ──────────────────────────────────────

    public function validateCheckDigit(string $containerNo): bool
    {
        if (strlen($containerNo) !== 11) {
            return false;
        }

        $values = [
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

        return (int) $containerNo[10] === ($sum % 11) % 10;
    }
}
