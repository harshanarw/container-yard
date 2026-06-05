<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ContainerOcrService
{
    /**
     * Extract container data from an image file using Tesseract OCR.
     *
     * @return array{container_no: string|null, check_digit_valid: bool, iso_type: string|null, tare_kg: int|null, max_gross_kg: int|null, raw_text: string}
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

        [$containerNo, $checkDigitValid] = $this->extractContainerNo($text);

        return [
            'container_no'      => $containerNo,
            'check_digit_valid' => $checkDigitValid,
            'iso_type'          => $this->extractIsoType($text),
            'tare_kg'           => $this->extractTareKg($text),
            'max_gross_kg'      => $this->extractMaxGrossKg($text),
            'raw_text'          => $text,
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

        // Try three PSM modes and prefer whichever output already contains a
        // container-number pattern (4 letters + 7 digits) in its compacted form.
        // PSM 3 = full auto (good for complex door layouts),
        // PSM 11 = sparse text (good for mixed-content plates),
        // PSM 6  = uniform block (fallback for clean label images).
        $candidates = [];
        foreach ([3, 11, 6] as $psm) {
            $cmd = "tesseract {$escaped} stdout -l eng --psm {$psm} --oem 3 {$nullDev}";
            $out = shell_exec($cmd);
            if ($out !== null && trim($out) !== '') {
                $candidates[] = strtoupper(trim($out));
            }
        }

        foreach ($candidates as $up) {
            if (preg_match('/[A-Z]{4}\d{7}/', preg_replace('/[^A-Z0-9]/', '', $up))) {
                return $up;
            }
        }

        return $candidates[0] ?? '';
    }

    // ── Container number extraction ──────────────────────────────────────────

    /**
     * Returns [containerNo|null, checkDigitValid].
     * Uses any 4-letter prefix to match manual validation (^[A-Z]{4}[0-9]{7}$).
     */
    private function extractContainerNo(string $text): array
    {
        // Collapse to alphanumeric only — strips spaces OCR inserts between prefix,
        // serial, and boxed check digit: "SEGU 111192 3" → "SEGU1111923"
        $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper($text));

        // Any 4-letter prefix + 6–9 digits; slide 7-digit window, validate check digit
        if (preg_match_all('/([A-Z]{4})(\d{6,9})/', $compact, $sets, PREG_SET_ORDER)) {
            foreach ($sets as $set) {
                $prefix = $set[1];
                $digits = $set[2];
                $len    = strlen($digits);

                for ($offset = 0; $offset <= $len - 7; $offset++) {
                    $candidate = $prefix . substr($digits, $offset, 7);
                    if ($this->validateCheckDigit($candidate)) {
                        return [$candidate, true];
                    }
                }

                // No window validated — try correcting common OCR misreads in the prefix
                $sub = $this->tryPrefixSubstitution($prefix, $digits);
                if ($sub !== null) {
                    return [$sub, true];
                }

                // Best guess: rightmost 7 digits; check digit usually printed last
                return [$prefix . substr($digits, -7), false];
            }
        }

        return [null, false];
    }

    // ── OCR character-substitution fallback ──────────────────────────────────

    private function tryPrefixSubstitution(string $prefix, string $digits): ?string
    {
        // Single-character swaps for letters commonly confused in blocky plate fonts
        static $swaps = ['C' => 'G', 'G' => 'C', 'O' => 'Q', 'Q' => 'O', 'I' => 'L', 'L' => 'I'];
        $len = strlen($digits);
        for ($pos = 0; $pos < 4; $pos++) {
            $ch = $prefix[$pos];
            if (!isset($swaps[$ch])) {
                continue;
            }
            $alt = substr_replace($prefix, $swaps[$ch], $pos, 1);
            for ($offset = 0; $offset <= $len - 7; $offset++) {
                $candidate = $alt . substr($digits, $offset, 7);
                if ($this->validateCheckDigit($candidate)) {
                    return $candidate;
                }
            }
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
