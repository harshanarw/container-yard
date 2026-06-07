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
        // Pass the original upload path alongside the enhanced copy so ISO crops
        // can run on the unsharpened image (the GD sharpen kernel creates ringing
        // artefacts around small stencil characters like "42G1").
        [$text, $cropCandidates, $fullCandidates] = $this->runTesseract($workPath, $tmpPath);

        if ($enhanced && file_exists($enhanced)) {
            @unlink($enhanced);
        }

        [$containerNo, $checkDigitValid] = $this->extractContainerNo($cropCandidates, $fullCandidates, $text);

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

    /**
     * Run all Tesseract passes and return:
     *   [0] string  — combined text of all passes (best-scored first) used for
     *                 weight / ISO-type / raw-text extraction
     *   [1] array   — focused crop candidates (PSM-6 / PSM-7 crops, no column
     *                 detection) preferred for container-number extraction
     *   [2] array   — full-image candidates (PSM-3 / PSM-11 / PSM-6 on the
     *                 whole image) used as fallback for container-number extraction
     *
     * All Tesseract processes are launched simultaneously via proc_open and results
     * are collected in one sweep, so total wall time ≈ slowest single call rather
     * than the sum of all calls.
     */
    private function runTesseract(string $imagePath, string $originalPath = ''): array
    {
        $nullDev  = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
        $alphanum = '-c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        // Use original (unsharpened) image for ISO crops when available.
        $isoPath = ($originalPath !== '' && file_exists($originalPath))
            ? $originalPath
            : $imagePath;

        // ── Step 1: define all jobs ──────────────────────────────────────────
        // Each row: [isFull, srcImage, x1, y1, x2, y2, psm, extraConfig, negate]
        // isFull=true  → run Tesseract directly on srcImage (no GD crop needed)
        // isFull=false → prepare a GD crop first, then run Tesseract on that temp file
        //
        // PSM 3 = full auto (complex door layouts)
        // PSM 11 = sparse text (mixed-content plates)
        // PSM 6  = uniform block (clean label images)
        $jobDefs = [
            // Full-image passes (A-equivalent)
            [true,  $imagePath, 0,    0,    0,    0,    3,  '',        false],
            [true,  $imagePath, 0,    0,    0,    0,    11, '',        false],
            [true,  $imagePath, 0,    0,    0,    0,    6,  '',        false],
        ];

        if (extension_loaded('gd')) {
            $jobDefs = array_merge($jobDefs, [
                // Container-number crops (A–G)
                // A: PSM 6, x:20–100%, y:0–30% — avoids far-left margin noise
                [false, $imagePath, 0.20, 0.0,  1.0,  0.30, 6,  '',        false],
                // B: PSM 6, full width, y:0–40% — catches "TG" at extreme left edge
                [false, $imagePath, 0.0,  0.0,  1.0,  0.40, 6,  '',        false],
                // C: PSM 7 single line, x:0–75%, y:0–22% — locking rod treated as whitespace
                [false, $imagePath, 0.0,  0.0,  0.75, 0.22, 7,  '',        false],
                // D: PSM 7 single line, full width, y:0–18%
                [false, $imagePath, 0.0,  0.0,  1.0,  0.18, 7,  '',        false],
                // E: PSM 3 auto, x:30–100%, full height — excludes left door panel
                [false, $imagePath, 0.30, 0.0,  1.0,  1.0,  3,  '',        false],
                // F: PSM 3 auto, x:50–100%, full height
                [false, $imagePath, 0.50, 0.0,  1.0,  1.0,  3,  '',        false],
                // G: PSM 6 block, x:35–100%, y:0–35% — right-panel complement
                [false, $imagePath, 0.35, 0.0,  1.0,  0.35, 6,  '',        false],

                // ISO size/type code crops (H–P)
                // "42G1" sits just below the container number (≈ y:19–32% on a door photo).
                // Two image sources: $imagePath (GD-enhanced, sharper contrast on some cameras)
                // and $isoPath (original upload, avoids sharpen-kernel ringing on small text).
                // H: enhanced PSM 7 single line
                [false, $imagePath, 0.38, 0.19, 0.92, 0.32, 7,  '',        false],
                // I: enhanced PSM 8 single word + whitelist
                [false, $imagePath, 0.38, 0.19, 0.92, 0.32, 8,  $alphanum, false],
                // J: enhanced PSM 8 + whitelist + negate
                [false, $imagePath, 0.38, 0.19, 0.92, 0.32, 8,  $alphanum, true],
                // K: original PSM 8 + whitelist — dark text on light background
                [false, $isoPath,   0.38, 0.19, 0.92, 0.32, 8,  $alphanum, false],
                // L: original PSM 8 + whitelist + negate — light text on dark/green panel
                [false, $isoPath,   0.38, 0.19, 0.92, 0.32, 8,  $alphanum, true],
                // M: original PSM 6 block + whitelist + negate
                [false, $isoPath,   0.38, 0.19, 0.92, 0.32, 6,  $alphanum, true],
                // N: original narrower x:45–75%, negate — removes left/right edge noise
                [false, $isoPath,   0.45, 0.18, 0.75, 0.35, 8,  $alphanum, true],
                // O: original wide y:16–46%, PSM 11 sparse + negate — safety net for
                //    ISO codes at unexpected vertical positions
                [false, $isoPath,   0.38, 0.16, 0.88, 0.46, 11, $alphanum, true],
                // P: original wide y:16–46%, PSM 11 sparse — dark-on-light complement
                [false, $isoPath,   0.38, 0.16, 0.88, 0.46, 11, $alphanum, false],
            ]);
        }

        // ── Step 2: prepare all crop images (GD, sequential — CPU-bound) ────
        $tmpFiles = [];
        foreach ($jobDefs as $i => $job) {
            [$isFull, $src, $x1, $y1, $x2, $y2, , , $negate] = $job;
            $tmpFiles[$i] = $isFull
                ? null
                : $this->prepareCropImage($src, $x1, $y1, $x2, $y2, $negate);
        }

        $fullCandidates = [];
        $cropCandidates = [];

        if (function_exists('proc_open') && !in_array('proc_open', array_map('trim', explode(',', ini_get('disable_functions'))), true)) {
            // ── Step 3a: launch ALL Tesseract processes simultaneously ───────
            // proc_open lets every process start without waiting for its predecessor,
            // so total wall-clock time ≈ max(individual durations) instead of their sum.
            $handles = [];
            foreach ($jobDefs as $i => $job) {
                [$isFull, $src, , , , , $psm, $extra] = $job;
                $path = $isFull ? $src : $tmpFiles[$i];
                if ($path === null) {
                    $handles[$i] = null;
                    continue;
                }
                $cfg  = $extra !== '' ? " {$extra}" : '';
                $cmd  = "tesseract " . escapeshellarg($path) . " stdout -l eng --psm {$psm} --oem 3{$cfg} {$nullDev}";
                $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w']];
                $pipes = [];
                $proc = proc_open($cmd, $desc, $pipes);
                if (is_resource($proc)) {
                    fclose($pipes[0]); // close stdin — Tesseract doesn't need it
                    $handles[$i] = [$proc, $pipes[1]];
                } else {
                    $handles[$i] = null;
                }
            }

            // ── Step 3b: collect results (blocking read per pipe) ────────────
            // By the time we reach the second pipe, the remaining processes are almost
            // certainly already done (they all started at the same time), so each read
            // returns immediately rather than blocking for a full Tesseract round-trip.
            foreach ($jobDefs as $i => [$isFull]) {
                $handle = $handles[$i];
                $tmp    = $tmpFiles[$i];

                if ($handle !== null) {
                    [$proc, $stdout] = $handle;
                    $raw = stream_get_contents($stdout);
                    fclose($stdout);
                    proc_close($proc);
                    $out = ($raw !== false && trim($raw) !== '') ? strtoupper(trim($raw)) : '';
                } else {
                    $out = '';
                }

                if ($tmp !== null) @unlink($tmp);
                if ($out === '') continue;

                if ($isFull) { $fullCandidates[] = $out; } else { $cropCandidates[] = $out; }
            }
        } else {
            // ── Step 3 (fallback): sequential shell_exec when proc_open is disabled ──
            foreach ($jobDefs as $i => $job) {
                [$isFull, $src, , , , , $psm, $extra] = $job;
                $path = $isFull ? $src : $tmpFiles[$i];
                $tmp  = $tmpFiles[$i];

                if ($path === null) {
                    if ($tmp !== null) @unlink($tmp);
                    continue;
                }

                $cfg = $extra !== '' ? " {$extra}" : '';
                $raw = shell_exec("tesseract " . escapeshellarg($path) . " stdout -l eng --psm {$psm} --oem 3{$cfg} {$nullDev}");
                if ($tmp !== null) @unlink($tmp);

                $out = ($raw !== null && trim($raw) !== '') ? strtoupper(trim($raw)) : '';
                if ($out === '') continue;

                if ($isFull) { $fullCandidates[] = $out; } else { $cropCandidates[] = $out; }
            }
        }

        $allCandidates = array_merge($fullCandidates, $cropCandidates);

        if (empty($allCandidates)) {
            return ['', [], []];
        }

        // Rank all candidates so the most informative one leads the combined text.
        // This ordering is used for weight / ISO-type extraction where label→value
        // proximity in the concatenated string matters.
        $bestIdx   = 0;
        $bestScore = 0;
        foreach ($allCandidates as $i => $up) {
            $c     = preg_replace('/[^A-Z0-9]/', '', $up);
            $score = 0;
            if (preg_match_all('/([A-Z]{3}[UJZ])(\d{6,9})/', $c, $vsets, PREG_SET_ORDER)) {
                foreach ($vsets as $vs) {
                    $vLen = strlen($vs[2]);
                    for ($vOff = 0; $vOff <= $vLen - 7; $vOff++) {
                        if ($this->validateCheckDigit($vs[1] . substr($vs[2], $vOff, 7))) {
                            $score = 5;
                            break 2;
                        }
                    }
                }
            }
            if ($score < 5) {
                if      (preg_match('/[A-Z]{3}[UJZ]\d{7}/', $c)) $score = 4;
                elseif  (preg_match('/[A-Z]{3}[UJZ]\d{6}/', $c)) $score = 3;
                elseif  (preg_match('/[A-Z]{4}\d{7}/',      $c)) $score = 2;
                elseif  (preg_match('/[A-Z]{4}\d{6}/',      $c)) $score = 1;
                elseif  (preg_match('/\d[A-Z]{2}[UJZ]\d{6}/', $c)) $score = 1;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx   = $i;
                if ($score === 5) break;
            }
        }
        if ($bestIdx !== 0) {
            array_unshift($allCandidates, array_splice($allCandidates, $bestIdx, 1)[0]);
        }

        return [
            implode("\n\n", $allCandidates), // combined text (best-scored first)
            $cropCandidates,
            $fullCandidates,
        ];
    }

    /**
     * Crop and scale a sub-region of an image, optionally negate it, and save to a
     * temporary PNG file. Returns the temp path, or null if GD fails.
     * Coordinates are fractions of width/height: (x1,y1) top-left, (x2,y2) bottom-right.
     * The crop is scaled to at least 800 px wide so Tesseract can resolve small text.
     */
    private function prepareCropImage(
        string $imagePath,
        float $x1, float $y1,
        float $x2, float $y2,
        bool $negate = false
    ): ?string {
        $mime = mime_content_type($imagePath);
        $src  = match (true) {
            str_contains($mime, 'png')  => @imagecreatefrompng($imagePath),
            str_contains($mime, 'gif')  => @imagecreatefromgif($imagePath),
            default                     => @imagecreatefromjpeg($imagePath),
        };
        if (!$src) return null;

        $w = imagesx($src);
        $h = imagesy($src);

        $cropX = (int) ($w * $x1);
        $cropY = (int) ($h * $y1);
        $cropW = (int) ($w * ($x2 - $x1));
        $cropH = (int) ($h * ($y2 - $y1));

        if ($cropW < 50 || $cropH < 20) {
            imagedestroy($src);
            return null;
        }

        $dest = imagecreatetruecolor($cropW, $cropH);
        imagecopy($dest, $src, 0, 0, $cropX, $cropY, $cropW, $cropH);
        imagedestroy($src);

        if ($cropW < 800) {
            $scale  = 800 / $cropW;
            $newW   = (int) ($cropW * $scale);
            $newH   = (int) ($cropH * $scale);
            $scaled = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($scaled, $dest, 0, 0, 0, 0, $newW, $newH, $cropW, $cropH);
            imagedestroy($dest);
            $dest = $scaled;
        }

        if ($negate) {
            // Grayscale + contrast before inversion so white-on-colour text (e.g.
            // white on green) becomes crisp dark-on-white after the negate.
            imagefilter($dest, IMG_FILTER_GRAYSCALE);
            imagefilter($dest, IMG_FILTER_BRIGHTNESS, 15);
            imagefilter($dest, IMG_FILTER_CONTRAST, -30);
            imagefilter($dest, IMG_FILTER_NEGATE);
        }

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_crop_' . uniqid() . '.png';
        $ok = imagepng($dest, $tmpPath);
        imagedestroy($dest);

        return ($ok && file_exists($tmpPath)) ? $tmpPath : null;
    }

    /**
     * Convenience wrapper: prepare a crop image then run Tesseract on it in one call.
     * Used for ad-hoc single-crop testing; the main OCR pipeline uses prepareCropImage
     * directly so all crops can be launched in parallel.
     */
    private function runTesseractOnCrop(
        string $imagePath,
        float $x1, float $y1,
        float $x2, float $y2,
        int $psm,
        string $extraConfig = '',
        bool $negate = false
    ): string {
        $tmpPath = $this->prepareCropImage($imagePath, $x1, $y1, $x2, $y2, $negate);
        if ($tmpPath === null) return '';

        $nullDev = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';
        $cfg = $extraConfig !== '' ? " {$extraConfig}" : '';
        $out = shell_exec("tesseract " . escapeshellarg($tmpPath) . " stdout -l eng --psm {$psm} --oem 3{$cfg} {$nullDev}");
        @unlink($tmpPath);

        return ($out !== null && trim($out) !== '') ? strtoupper(trim($out)) : '';
    }



    /**
     * Extract a container number by trying candidates in quality order.
     *
     * Phase 1 — focused crop candidates (PSM-6 / PSM-7, no column detection).
     *   If a crop produces a validated container number → return it immediately.
     *   If crops produce pattern matches with invalid check digits, collect ALL
     *   of them and return the most frequent reading (majority vote). This beats
     *   first-match when one PSM crop misreads a single digit (e.g. 6→8) while
     *   the remaining crops all read it correctly.
     *   Full-image passes are never reached when any crop produced a match — they
     *   contain column-split noise that can accidentally form a valid ISO 6346 number.
     *
     * Phase 2 — full-image passes (PSM-3 / PSM-11 / PSM-6), only reached when
     *   no crop produced any pattern match at all.
     *
     * Phase 3 — combined compact (container number may span two passes), then
     *   formatted text scan ("ABCU 123456 7" with spaces / pipes preserved).
     */
    private function extractContainerNo(array $cropCandidates, array $fullCandidates, string $fallback): array
    {
        $cropVotes = [];

        foreach ($cropCandidates as $text) {
            $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper($text));
            [$no, $valid] = $this->extractContainerNoFromCompact($compact);
            if ($valid) return [$no, true];
            if ($no !== null) {
                $cropVotes[$no] = ($cropVotes[$no] ?? 0) + 1;
            }
        }

        if (!empty($cropVotes)) {
            arsort($cropVotes);
            $topNo = array_key_first($cropVotes);
            // Apply single-digit substitution to the consensus reading.
            // Done here (not per-crop) so a spurious valid result from one bad crop
            // cannot bypass the majority vote by coincidentally passing check-digit.
            $corrected = $this->trySerialDigitSubstitution(substr($topNo, 0, 4), substr($topNo, 4, 7));
            if ($corrected !== null) return [$corrected, true];
            return [$topNo, false];
        }

        foreach ($fullCandidates as $text) {
            $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper($text));
            [$no, $valid] = $this->extractContainerNoFromCompact($compact);
            if ($valid) return [$no, true];
            if ($no !== null) return [$no, false];
        }

        $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper($fallback));
        [$no, $valid] = $this->extractContainerNoFromCompact($compact);
        if ($no !== null) return [$no, $valid];

        return $this->extractFormattedContainerNo($fallback);
    }

    /**
     * Try to extract a validated ISO 6346 container number from a compact
     * (all non-alphanumeric stripped) string.
     *
     * Returns [containerNo, true]  when check digit validates.
     * Returns [bestGuess,   false] when a recognisable pattern exists but
     *                              check digit is wrong/unknown.
     * Returns [null,        false] when no pattern found at all.
     */
    private function extractContainerNoFromCompact(string $compact): array
    {
        $bestGuess = null;

        // Any 4-letter prefix + 6–9 digits + optional trailing letter.
        // ISO 6346 requires position 3 (category code) to be U, J, or Z; V→U is
        // applied first since V and U are indistinguishable in many stencil fonts.
        if (preg_match_all('/([A-Z]{4})(\d{6,9})([A-Z])?/', $compact, $sets, PREG_SET_ORDER)) {
            foreach ($sets as $set) {
                $prefix         = $this->normalizeCategoryChar($set[1]);
                $digits         = $set[2];
                $trailingLetter = $set[3] ?? '';

                if (!in_array($prefix[3], ['U', 'J', 'Z'], true)) continue;

                $len        = strlen($digits);
                $firstSeven = $len >= 7 ? $prefix . substr($digits, 0, 7) : null;

                // Only validate at offset=0 (the as-printed position).
                // Shifting the window to offset>0 can coincidentally validate when
                // ISO-type code digits (e.g. "2261" from "22G1") are concatenated
                // directly after the check digit in the compact — the window then
                // finds a 7-char sequence that happens to pass the check-digit test.
                if ($firstSeven !== null && $this->validateCheckDigit($firstSeven)) {
                    return [$firstSeven, true];
                }

                // 6 digits + trailing letter: OCR misread check digit as a letter.
                if ($len === 6 && $trailingLetter !== '') {
                    for ($d = 0; $d <= 9; $d++) {
                        $candidate = $prefix . $digits . $d;
                        if ($this->validateCheckDigit($candidate)) return [$candidate, true];
                    }
                }

                $sub = $this->tryPrefixSubstitution($prefix, $digits);
                if ($sub !== null) return [$sub, true];

                // Pattern matched but check digit invalid — keep as best guess.
                // Use the first-7 digits (as-printed order) rather than the last 7;
                // extra trailing digits are most often ISO-type noise, not the serial.
                // Continue scanning — a later match in the same compact may validate.
                if ($bestGuess === null && $firstSeven !== null) {
                    $bestGuess = [$firstSeven, false];
                }
            }
        }

        // Spurious-letter recovery: door hardware (locking rod, hinge, rivet) between
        // the owner-code and the serial is sometimes OCR-read as a letter, producing e.g.
        // "MSCUJ1234567" instead of "MSCU1234567". The main regex above matches "SCUJ…"
        // at offset +1; this pass explicitly discards the 5th letter and uses the first 4.
        if (preg_match_all('/([A-Z]{4})[A-Z](\d{6,9})([A-Z])?/', $compact, $spurSets, PREG_SET_ORDER)) {
            foreach ($spurSets as $sset) {
                $sPfx  = $this->normalizeCategoryChar($sset[1]);
                $sDigs = $sset[2];
                $sLen  = strlen($sDigs);

                if (!in_array($sPfx[3], ['U', 'J', 'Z'], true)) continue;

                // All results from spurious-letter recovery are flagged valid=false
                // (check digit warning shown). We dropped a character — the prefix and
                // serial may still be correct, but the user must verify.
                $spurGuess = null;

                // Sliding window: use exact OCR-read digits.
                for ($off = 0; $off <= $sLen - 7; $off++) {
                    $candidate = $sPfx . substr($sDigs, $off, 7);
                    if ($this->validateCheckDigit($candidate)) {
                        $spurGuess = $candidate;
                        break;
                    }
                }

                // 6 serial digits only — check digit stripped as non-alphanumeric box
                // border noise (e.g. "7" printed in a box is OCR-read as ".//").
                // Brute-force the check digit to form a complete 11-char number.
                if ($spurGuess === null && $sLen === 6) {
                    for ($d = 0; $d <= 9; $d++) {
                        $candidate = $sPfx . $sDigs . $d;
                        if ($this->validateCheckDigit($candidate)) {
                            $spurGuess = $candidate;
                            break;
                        }
                    }
                }

                if ($spurGuess !== null) {
                    // Prefer the first-4-letter prefix result over any position-shifted
                    // false match from the main loop (e.g. MSCU1234566 > SCUJ1234566).
                    if ($bestGuess === null || str_ends_with($bestGuess[0], substr($spurGuess, 4))) {
                        $bestGuess = [$spurGuess, false];
                    }
                }
            }
        }

        // Digit-prefix recovery: stencil fonts frequently render "T" as "1", "G" as "6",
        // etc., so PSM 7 single-line crops may yield "1GHU482917…" instead of "TGHU482917…".
        static $digitSubs = [
            '1' => ['T', 'I', 'L'],
            '6' => ['G', 'C'],
            '0' => ['O', 'Q'],
            '8' => ['B'],
            '5' => ['S'],
        ];
        if (preg_match_all('/([0-9])([A-Z]{3})(\d{6,9})([A-Z])?/', $compact, $dsets, PREG_SET_ORDER)) {
            foreach ($dsets as $dset) {
                $dDigit  = $dset[1];
                $dRest3  = $dset[2];
                $dDigits = $dset[3];
                $dTrail  = $dset[4] ?? '';
                $dLen    = strlen($dDigits);

                foreach ($digitSubs[$dDigit] ?? [] as $sub) {
                    $prefix = $this->normalizeCategoryChar($sub . $dRest3);
                    if (!in_array($prefix[3], ['U', 'J', 'Z'], true)) continue;

                    for ($offset = 0; $offset <= $dLen - 7; $offset++) {
                        $candidate = $prefix . substr($dDigits, $offset, 7);
                        if ($this->validateCheckDigit($candidate)) return [$candidate, true];
                    }

                    if ($dLen === 6 && $dTrail !== '') {
                        for ($d = 0; $d <= 9; $d++) {
                            $candidate = $prefix . $dDigits . $d;
                            if ($this->validateCheckDigit($candidate)) return [$candidate, true];
                        }
                    }

                    if ($dLen >= 8) {
                        for ($offset = 0; $offset <= $dLen - 6; $offset++) {
                            $serial = substr($dDigits, $offset, 6);
                            for ($d = 0; $d <= 9; $d++) {
                                $candidate = $prefix . $serial . $d;
                                if ($this->validateCheckDigit($candidate)) return [$candidate, true];
                            }
                        }
                    }
                }
            }
        }

        return $bestGuess ?? [null, false];
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
        // One-directional: only fix towards the more common/visually dominant character.
        // O→Q is intentionally absent: OCR rarely misreads a printed O as Q (Q has a
        // visible tail), but the reverse happens. If O→Q were kept, a correctly-read
        // "OOLU" that fails its check digit would be silently swapped to "QOLU" whenever
        // QOLU happens to produce a valid check digit — the wrong owner code with no warning.
        static $swaps = [
            'C' => 'G', 'G' => 'C',
            'Q' => 'O',
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

    /**
     * Single-character digit substitution in the serial + check-digit portion.
     *
     * Handles OCR misreads of visually similar digits in stencil plate fonts.
     * Most common on container doors: 6 ↔ 8 (curved tops merge under low contrast),
     * 0 ↔ 8 (lower bowl of 8 can vanish), 1 ↔ 7 (upstroke of 7 faint in stencil).
     * Returns a corrected 11-char number that passes ISO 6346 check-digit validation,
     * or null when no single-digit substitution produces a valid result.
     */
    private function trySerialDigitSubstitution(string $prefix, string $digits): ?string
    {
        if (strlen($digits) < 7) return null;

        // High-confidence pairs only — visually unambiguous in stencil plate fonts.
        // Aggressive entries (3↔8, 5↔6, 8↔0) are omitted: a coincidental valid
        // check digit from a double-error reading is more likely than a true fix.
        static $swaps = [
            '6' => ['8'],  // most common: curved top of 6 merges with 8 under low contrast
            '8' => ['6'],
            '0' => ['8'],  // zero's oval can be read as 8 in worn stencil
            '1' => ['7'],
            '7' => ['1'],
        ];

        $window = substr($digits, 0, 7); // 6 serial digits + 1 check digit

        for ($pos = 0; $pos < 7; $pos++) {
            $ch = $window[$pos];
            if (!isset($swaps[$ch])) continue;
            foreach ($swaps[$ch] as $alt) {
                $candidate = $prefix . substr_replace($window, $alt, $pos, 1);
                if ($this->validateCheckDigit($candidate)) return $candidate;
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
        // Standalone 4-char code.
        // Second char uses [0-9] (all ISO height codes) rather than [025] so that
        // less common heights (e.g. '4' = higher frame, '6' = low height) are caught.
        // Lookahead/lookbehind keep the match boundary-safe without relying on \b.
        if (preg_match_all('/(?<![A-Z0-9])([24L][0-9][A-Z0-9][0-9A-Z])(?![A-Z0-9])/i', $text, $all)) {
            foreach ($all[1] as $raw) {
                $code = $this->normalizeIsoCode(strtoupper($raw));
                if ($this->isValidIsoTypeCode($code)) return $code;
            }
        }
        // Compact per-block fallback: Tesseract sometimes inserts a space inside the
        // 4-char code when the crop is scaled up (e.g. "4 2G1" or "42 G1").
        // Each OCR pass block is stripped of non-alphanumerics and re-searched.
        // Only applied to short blocks (4–15 compacted chars) to avoid false matches
        // from container-number digit strings or weight values in longer pass blocks.
        foreach (explode("\n\n", $text) as $block) {
            $compact = strtoupper(preg_replace('/[^A-Z0-9]/', '', $block));
            $cLen = strlen($compact);
            if ($cLen < 4 || $cLen > 15) continue;
            if (preg_match_all('/([24L][0-9][A-Z0-9][0-9A-Z])/', $compact, $cm)) {
                foreach ($cm[1] as $raw) {
                    $code = $this->normalizeIsoCode($raw);
                    if ($this->isValidIsoTypeCode($code)) return $code;
                }
            }
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
        // Scan every occurrence of the label — the combined OCR string may contain
        // several passes; early passes (e.g. a reversed-column right-panel crop) can
        // have the value BEFORE the label, while later passes have the normal order.
        // Using preg_match_all ensures we reach the pass whose value follows the label.
        // Window of 40 chars: tight enough to exclude a PAYLOAD KG value that appears
        // ~41 chars after a MAX GROSS label in reversed-column PSM 3 crops, yet wide
        // enough for a normal label-then-value layout (value is always within ~20 chars).
        if (!preg_match_all('/\b(?:' . $labelAlt . ')\b/i', $text, $allMatches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $lbsFallback = null;

        foreach ($allMatches[0] as $match) {
            $after = substr($text, $match[1] + strlen($match[0]), 40);

            // KG candidate — allow ) . : - between digits and unit (e.g. "3800)KG", "3650-KG")
            $kg = null;
            if (preg_match('/[\s:|]*([0-9][0-9\s,\.]+)[\s):.\-]*(?:K[A-Z]|[A-Z]G)S?\b/i', $after, $m)) {
                $v = (int) preg_replace('/[^0-9]/', '', $m[1]);
                if ($v >= $minKg && $v <= $maxKg) $kg = $v;
            }

            // LBS candidate — allow ) . : between digits and unit (e.g. "71650)LB")
            $lbs = null;
            if (preg_match('/([0-9][0-9\s,\.]+)[\s):.]*(?:LBS?|L[A-Z]S?)\b/i', $after, $m)) {
                $lbs = (int) preg_replace('/[^0-9]/', '', $m[1]);
            }

            // Cross-validate KG/LBS pair (ratio ≈ 2.20462; >20 % deviation means a
            // digit was likely misread in the KG value — prefer the LBS-derived figure).
            if ($kg !== null && $lbs !== null && $lbs > $kg) {
                $ratio = $lbs / $kg;
                if ($ratio < 1.75 || $ratio > 2.65) {
                    $derived = (int) round($lbs / 2.20462);
                    if ($derived >= $minKg && $derived <= $maxKg) return $derived;
                }
                return $kg;
            }

            // Direct KG reading — highest confidence; return immediately.
            if ($kg !== null) {
                return $kg;
            }

            // Only LBS available for this occurrence — save as fallback and keep
            // looking for an occurrence that has a direct KG reading.
            if ($lbs !== null && $lbsFallback === null) {
                $derived = (int) round($lbs / 2.20462);
                if ($derived >= $minKg && $derived <= $maxKg) {
                    $lbsFallback = $derived;
                }
            }
        }

        return $lbsFallback;
    }

    /**
     * Reversed-column fallback: PSM 3 sometimes places values before labels.
     */
    private function findReversedWeightKg(string $text, string $label, int $minKg, int $maxKg): ?int
    {
        if (preg_match(
            '/([0-9][0-9\s,\.]+)[\s):.]*(?:K[A-Z]|[A-Z]G)S?[\s\S]{0,80}?\b' . $label . '\b/i',
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
