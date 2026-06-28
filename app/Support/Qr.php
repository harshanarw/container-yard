<?php

namespace App\Support;

/**
 * Tiny wrapper around simplesoftwareio/simple-qrcode that returns an SVG
 * data-URI suitable for embedding in dompdf-rendered PDFs (dompdf renders SVG
 * via php-svg-lib and cannot run JavaScript QR generators).
 *
 * Degrades gracefully: if the QR package is not installed, returns null so the
 * document still renders without a QR (and without errors).
 *
 *   composer require simplesoftwareio/simple-qrcode
 */
class Qr
{
    /**
     * Build an `data:image/svg+xml;base64,...` QR image for the given payload,
     * or null when generation is unavailable.
     */
    public static function svgDataUri(?string $data, int $size = 110): ?string
    {
        if (empty($data)) {
            return null;
        }

        $facade = \SimpleSoftwareIO\QrCode\Facades\QrCode::class;
        if (! class_exists($facade)) {
            return null;
        }

        try {
            $svg = (string) $facade::format('svg')
                ->size($size)
                ->margin(0)
                ->errorCorrection('M')
                ->generate($data);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable) {
            return null;
        }
    }
}
