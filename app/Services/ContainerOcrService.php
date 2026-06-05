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

        // PSM 3 = full auto (complex door layouts),
        // PSM 11 = sparse text (mixed-content plates),
        // PSM 6  = uniform block (clean label images).
        $candidates = [];
        foreach ([3, 11, 6] as $psm) {
            $cmd = "tesseract {$escaped} stdout -l eng --psm {$psm} --oem 3 {$nullDev}";
            $out = shell_exec($cmd);
            if ($out !== null && trim($out) !== '') {
                $candidates[] = strtoupper(trim($out));
            }
        }

        // Focused crop pass: container numbers sit in the upper portion of the door.
        // Vertical locking rods bisect the prefix — PSM 3 column detection places
        // e.g. "TG" in one column and "HU 482917 3" in another, losing the first two
        // characters.  A wider crop (x from 25 %) + PSM 6 (uniform block, no column
        // detection) keeps both halves of the prefix in one reading region.  The rod
        // character is stripped during compaction so "TG|HU 482917 A" → "TGHU482917A".
        if (extension_loaded('gd')) {
            $cropText = $this->runTesseractOnCrop($imagePath, 0.20, 0.0, 1.0, 0.30, 6);
            if ($cropText !== '') {
                $candidates[] = $cropText;
            }
        }

        if (empty($candidates)) {
            return '';
        }

        // Rank candidates by how confidently each contains a container number:
        //   4 = valid ISO category (U/J/Z) + 7 digits  → perfect match
        //   3 = valid ISO category              + 6 digits  → check digit may be a misread letter
        //   2 = any 4-letter prefix             + 7 digits
        //   1 = any 4-letter prefix             + 6 digits
        // Highest score moves to index 0 so extractContainerNo() sees it first.
        $bestIdx   = 0;
        $bestScore = 0;
        foreach ($candidates as $i => $up) {
            $c     = preg_replace('/[^A-Z0-9]/', '', $up);
            $score = 0;
            if      (preg_match('/[A-Z]{3}[UJZ]\d{7}/', $c)) $score = 4;
            elseif  (preg_match('/[A-Z]{3}[UJZ]\d{6}/', $c)) $score = 3;
            elseif  (preg_match('/[A-Z]{4}\d{7}/',      $c)) $score = 2;
            elseif  (preg_match('/[A-Z]{4}\d{6}/',      $c)) $score = 1;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx   = $i;
                if ($score === 4) break;
            }
        }
        if ($bestIdx !== 0) {
            array_unshift($candidates, array_splice($candidates, $bestIdx, 1)[0]);
        }

        // Concatenate all PSM outputs so that multi-column layouts (where PSM 3 places
        // "TARE" in one column and "2 180 KGS" in another) still allow weight regexes
        // to match — PHP PCRE \s matches newlines, bridging the two blocks.
        return implode("\n\n", $candidates);
    }

    /**
     * Run Tesseract on a proportionally-cropped sub-region of the image.
     * Coordinates are fractions of width/height: (x1,y1) = top-left, (x2,y2) = bottom-right.
     * The crop is scaled to at least 800 px wide before OCR so small regions are readable.
     */
    private function runTesseractOnCrop(
        string $imagePath,
        float $x1, float $y1,
        float $x2, float $y2,
        int $psm
    ): string {
        $mime = mime_content_type($imagePath);
        $src  = match (true) {
            str_contains($mime, 'png')  => @imagecreatefrompng($imagePath),
            str_contains($mime, 'gif')  => @imagecreatefromgif($imagePath),
            default                     => @imagecreatefromjpeg($imagePath),
        };
        if (!$src) return '';

        $w = imagesx($src);
        $h = imagesy($src);

        $cropX = (int) ($w * $x1);
        $cropY = (int) ($h * $y1);
        $cropW = (int) ($w * ($x2 - $x1));
        $cropH = (int) ($h * ($y2 - $y1));

        if ($cropW < 50 || $cropH < 20) {
            imagedestroy($src);
            return '';
        }

        $dest = imagecreatetruecolor($cropW, $cropH);
        imagecopy($dest, $src, 0, 0, $cropX, $cropY, $cropW, $cropH);
        imagedestroy($src);

        // Scale so the cropped text reaches an OCR-friendly width
        if ($cropW < 800) {
            $scale  = 800 / $cropW;
            $newW   = (int) ($cropW * $scale);
            $newH   = (int) ($cropH * $scale);
            $scaled = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($scaled, $dest, 0, 0, 0, 0, $newW, $newH, $cropW, $cropH);
            imagedestroy($dest);
            $dest = $scaled;
        }

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_crop_' . uniqid() . '.png';
        $ok = imagepng($dest, $tmpPath);
        imagedestroy($dest);

        if (!$ok || !file_exists($tmpPath)) return '';

        $nullDev = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
        $escaped = escapeshellarg($tmpPath);
        $out     = shell_exec("tesseract {$escaped} stdout -l eng --psm {$psm} --oem 3 {$nullDev}");
        @unlink($tmpPath);

        return ($out !== null && trim($out) !== '') ? strtoupper(trim($out)) : '';
    }



    /**
     * Returns [containerNo|null, checkDigitValid].
     * Uses any 4-letter prefix to match manual validation (^[A-Z]{4}[0-9]{7}$).
     */
    private function extractContainerNo(string $text): array
    {
        // Collapse to alphanumeric only — strips spaces OCR inserts between prefix,
        // serial, and boxed check digit: "SEGU 111192 3" → "SEGU1111923"
        $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper($text));

        // Any 4-letter prefix + 6–9 digits + optional trailing letter.
        // ISO 6346 requires position 3 (category code) to be U, J, or Z; V→U is
        // applied first since V and U are indistinguishable in many stencil fonts.
        // The optional trailing letter captures OCR misreads of the check digit
        // (e.g. "TGHU 482917 A" where "A" is a misread of "3").
        if (preg_match_all('/([A-Z]{4})(\d{6,9})([A-Z])?/', $compact, $sets, PREG_SET_ORDER)) {
            foreach ($sets as $set) {
                $prefix         = $this->normalizeCategoryChar($set[1]);
                $digits         = $set[2];
                $trailingLetter = $set[3] ?? '';

                // Skip anything that isn't a real container category code — prevents
                // false positives like "LATA2177777" formed by compacting scattered
                // OCR fragments (ZM+TG+IL+AT+A → ZMTGILATA before a dataplate number).
                if (!in_array($prefix[3], ['U', 'J', 'Z'], true)) {
                    continue;
                }

                $len = strlen($digits);
                for ($offset = 0; $offset <= $len - 7; $offset++) {
                    $candidate = $prefix . substr($digits, $offset, 7);
                    if ($this->validateCheckDigit($candidate)) {
                        return [$candidate, true];
                    }
                }

                // 6 digits + trailing letter: OCR misread the check digit as a letter
                // (common for "3"→"A", "0"→"O", etc.). Try all 10 possible digits.
                if ($len === 6 && $trailingLetter !== '') {
                    for ($d = 0; $d <= 9; $d++) {
                        $candidate = $prefix . $digits . $d;
                        if ($this->validateCheckDigit($candidate)) {
                            return [$candidate, true];
                        }
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

        // Fallback: search raw OCR text for "ABCU 123456 7" as printed on the door.
        // Useful when PSM modes preserve spacing but compact-merging loses adjacency
        // between the prefix and serial (e.g. "TGH 482917 3" → validates as TGHU).
        return $this->extractFormattedContainerNo($text);
    }

    /** Normalise V→U and W→U at the ISO 6346 category-code position (index 3). */
    private function normalizeCategoryChar(string $prefix): string
    {
        static $map = ['V' => 'U', 'W' => 'U'];
        return isset($map[$prefix[3]])
            ? substr($prefix, 0, 3) . $map[$prefix[3]]
            : $prefix;
    }

    /**
     * Fallback: scan raw OCR text for "PREFIX SERIAL CHECK" as printed on the door.
     * Accepts 3-char raw prefixes where the category letter was cut off — tries U/J/Z.
     * Also handles V→U at the category position via normalizeCategoryChar.
     */
    private function extractFormattedContainerNo(string $text): array
    {
        // 3–4 letters (prefix), optional pipe/space separators, 6 serial digits,
        // optional separator, 1 check digit (digit or letter — OCR often misreads
        // check digits as letters, e.g. "3" → "A").
        if (!preg_match_all(
            '/\b([A-Z]{3,4})[\s|]+(\d{6})[\s|]+([A-Z0-9])\b/i',
            strtoupper($text),
            $sets,
            PREG_SET_ORDER
        )) {
            return [null, false];
        }

        foreach ($sets as $set) {
            $rawPrefix = strtoupper($set[1]);
            $serial    = $set[2];
            $checkChar = strtoupper($set[3]);

            // If the check position is a letter, the digit was misread; try all 10.
            $checks = ctype_digit($checkChar) ? [$checkChar] : array_map('strval', range(0, 9));

            if (strlen($rawPrefix) === 4) {
                $prefix = $this->normalizeCategoryChar($rawPrefix);
                if (!in_array($prefix[3], ['U', 'J', 'Z'], true)) continue;
                foreach ($checks as $check) {
                    $candidate = $prefix . $serial . $check;
                    if ($this->validateCheckDigit($candidate)) return [$candidate, true];
                }
            } else {
                // 3-char raw prefix — category letter may have been cut off by OCR;
                // try appending each valid category code and validate check digit.
                foreach (['U', 'J', 'Z'] as $cat) {
                    foreach ($checks as $check) {
                        $candidate = $rawPrefix . $cat . $serial . $check;
                        if ($this->validateCheckDigit($candidate)) return [$candidate, true];
                    }
                }
            }
        }

        return [null, false];
    }

    // ── OCR character-substitution fallback ──────────────────────────────────

    private function tryPrefixSubstitution(string $prefix, string $digits): ?string
    {
        // Single-character swaps for letters commonly confused in blocky plate fonts.
        // V↔U added: stencil V and U are often identical in shape.
        static $swaps = [
            'C' => 'G', 'G' => 'C',
            'O' => 'Q', 'Q' => 'O',
            'I' => 'L', 'L' => 'I',
            'V' => 'U', 'U' => 'V',
        ];
        $len = strlen($digits);
        for ($pos = 0; $pos < 4; $pos++) {
            $ch = $prefix[$pos];
            if (!isset($swaps[$ch])) {
                continue;
            }
            $alt = substr_replace($prefix, $swaps[$ch], $pos, 1);
            // Category code must remain valid after substitution
            if (!in_array($alt[3], ['U', 'J', 'Z'], true)) continue;
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
            $code = $this->normalizeIsoCode(strtoupper($m[1]));
            if ($this->isValidIsoTypeCode($code)) return $code;
        }
        // Standalone: also accept a digit at the type-letter position so that the very
        // common OCR misread of G→6 in blocky stencil fonts (22G1 → 2261) is corrected.
        if (preg_match('/\b([24L][025][A-Z0-9][0-9A-Z])\b/', $text, $m)) {
            $code = $this->normalizeIsoCode(strtoupper($m[1]));
            if ($this->isValidIsoTypeCode($code)) return $code;
        }
        return null;
    }

    /**
     * Fix common OCR digit→letter misreads at ISO code position 2 (the equipment-type letter).
     * Blocky door stencil fonts: G is frequently read as 6; R occasionally as 8.
     */
    private function normalizeIsoCode(string $code): string
    {
        static $swaps = ['6' => 'G', '8' => 'R'];
        if (strlen($code) === 4 && isset($swaps[$code[2]])) {
            return substr($code, 0, 2) . $swaps[$code[2]] . $code[3];
        }
        return $code;
    }

    /** Position 2 of an ISO 6346 size-type code must be an equipment-type letter. */
    private function isValidIsoTypeCode(string $code): bool
    {
        return strlen($code) === 4 && in_array($code[2], ['G','R','U','P','T','B','S'], true);
    }

    // ── Weight extraction ────────────────────────────────────────────────────

    private function extractTareKg(string $text): ?int
    {
        return $this->findLabelledWeightKg($text, 'TARE', 1500, 6000)
            ?? $this->findLabelledWeightKg($text, 'T\s*:', 1500, 6000)
            ?? $this->findReversedWeightKg($text, 'TARE', 1500, 6000);
    }

    private function extractMaxGrossKg(string $text): ?int
    {
        return $this->findLabelledWeightKg($text, 'MGW|MAX\.?\s*GROSS|GROSS\s*WEIGHT', 10001, 40000)
            ?? $this->findReversedWeightKg($text, 'MGW', 10001, 40000);
    }

    /**
     * Extract a weight (kg) that appears after a label keyword in the text.
     *
     * Resilient to common OCR artefacts on container door plates:
     *  – Pipe/colon separators between the label and value columns
     *  – KG unit misread as KE, KI, AG, RG … (K[A-Z] or [A-Z]G)
     *  – LBS unit misread as LES, LAS, LIS … (L[A-Z]S?)
     *  – Single-digit misread in the KG value (e.g. 3→2 giving 22 500 instead of
     *    32 500): cross-validates the KG/LBS ratio (should be ~2.20462); when the
     *    ratio deviates by more than 20 % it derives kg from the LBS figure instead,
     *    which is typically read more reliably on high-contrast stencilled plates.
     */
    private function findLabelledWeightKg(string $text, string $labelAlt, int $minKg, int $maxKg): ?int
    {
        if (!preg_match('/\b(?:' . $labelAlt . ')\b/i', $text, $lm, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $after = substr($text, $lm[0][1] + strlen($lm[0][0]), 200);

        // KG candidate: number + 2-char unit resembling "KG"
        // K[A-Z] covers KG/KE/KI/KA; [A-Z]G covers AG/RG etc.
        $kg = null;
        if (preg_match('/[\s:|]*([0-9][0-9\s,\.]+)\s*(?:K[A-Z]|[A-Z]G)S?\b/i', $after, $m)) {
            $v = (int) preg_replace('/[^0-9]/', '', $m[1]);
            if ($v >= $minKg && $v <= $maxKg) $kg = $v;
        }

        // LBS candidate: number + unit resembling "LBS"
        $lbs = null;
        if (preg_match('/([0-9][0-9\s,\.]+)\s*(?:LBS?|L[A-Z]S?)\b/i', $after, $m)) {
            $lbs = (int) preg_replace('/[^0-9]/', '', $m[1]);
        }

        // Cross-validate the KG/LBS pair — expected ratio ≈ 2.20462.
        // If the ratio deviates > 20 % a digit was likely misread in the KG value;
        // prefer the LBS-derived figure in that case.
        if ($kg !== null && $lbs !== null && $lbs > $kg) {
            $ratio = $lbs / $kg;
            if ($ratio < 1.75 || $ratio > 2.65) {
                $derived = (int) round($lbs / 2.20462);
                if ($derived >= $minKg && $derived <= $maxKg) return $derived;
            }
            return $kg;
        }

        if ($kg !== null) return $kg;

        // KG unit absent or fully garbled — convert from LBS as last resort
        if ($lbs !== null) {
            $derived = (int) round($lbs / 2.20462);
            if ($derived >= $minKg && $derived <= $maxKg) return $derived;
        }

        return null;
    }

    /**
     * Reversed-column fallback: PSM 3 sometimes places values before labels.
     */
    private function findReversedWeightKg(string $text, string $label, int $minKg, int $maxKg): ?int
    {
        if (preg_match(
            '/([0-9][0-9\s,\.]+)\s*(?:K[A-Z]|[A-Z]G)S?[\s\S]{0,80}?\b' . $label . '\b/i',
            $text, $m
        )) {
            $kg = (int) preg_replace('/[^0-9]/', '', $m[1]);
            if ($kg >= $minKg && $kg <= $maxKg) return $kg;
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
