<?php

namespace App\Support\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One way to turn a report into a downloadable file.
 *
 * Five places in this codebase hand-rolled the same twelve lines — open
 * `php://output`, write a heading row with `fputcsv`, walk the query, close —
 * and were consistent only by accident. Giving each of them a second format
 * would have turned five copies into ten, so the consolidation is what makes
 * adding Excel small rather than a tidy-up beside it.
 *
 * A report supplies three things: a base filename, the headings, and a callable
 * returning an iterable of rows. The callable, not an array: the existing
 * exports chunk their queries so a year of movements does not have to fit in
 * memory, and that has to survive. Whatever batching a report needs — the
 * container inquiry fetches gate-outs a chunk at a time to avoid an N+1 — it
 * does inside its own generator, and this class never sees it.
 *
 * What this class owns is only the framing: the response, the filename, the
 * content type, and the escaping. What a cell *says* stays with the report that
 * built it, so migrating an export onto this changes nothing about its content.
 */
class TabularExport
{
    public const CSV  = 'csv';
    public const XLSX = 'xlsx';

    /**
     * The formats this installation can actually produce.
     *
     * Excel joins the list when a spreadsheet writer is installed; until then
     * asking for it quietly yields CSV rather than an error, and callers can ask
     * this before offering the option at all. Same shape as {@see \App\Support\Qr},
     * which returns null when its QR package is absent so the document still
     * renders.
     *
     * @return array<int,string>
     */
    public static function availableFormats(): array
    {
        return [self::CSV];
    }

    public static function supports(?string $format): bool
    {
        return in_array(strtolower(trim((string) $format)), self::availableFormats(), true);
    }

    /**
     * @param ?string  $format   'csv', or anything unrecognised, which becomes CSV
     * @param string   $basename filename stem; the timestamp and extension are added here
     * @param array    $headings the header row
     * @param callable $rows     returns an iterable of arrays — a generator, ideally
     */
    public static function stream(?string $format, string $basename, array $headings, callable $rows): StreamedResponse
    {
        // An unknown or unavailable format falls back rather than failing: a
        // stale bookmark or a hand-edited URL should still produce the report.
        return match (self::supports($format) ? strtolower(trim((string) $format)) : self::CSV) {
            default => self::csv($basename, $headings, $rows),
        };
    }

    public static function csv(string $basename, array $headings, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_map([self::class, 'guard'], $headings));

            foreach ($rows() as $row) {
                fputcsv($out, array_map([self::class, 'guard'], $row));
            }

            fclose($out);
        }, self::filename($basename, self::CSV), ['Content-Type' => 'text/csv']);
    }

    /** `mr-status-20260405-101500.csv` — the shape all four exports already used. */
    public static function filename(string $basename, string $extension): string
    {
        return $basename . '-' . now()->format('Ymd-His') . '.' . $extension;
    }

    /**
     * Stop a cell being executed as a formula when the file is opened.
     *
     * A spreadsheet treats a cell beginning `=`, `+`, `-` or `@` as a formula,
     * and `=cmd|'/c calc'!A0` in a container remark is a working command
     * injection against whoever opens the export. These reports carry
     * operator-typed remarks and customer names, so the risk is real rather than
     * theoretical. Prefixing an apostrophe makes the spreadsheet read it as text.
     *
     * Two exclusions keep this from mangling ordinary data, which is why the
     * migrated exports produce the same bytes as before:
     *
     *  - **Numbers pass through.** Job margin prints negatives, and `-1250.00`
     *    must stay a number rather than becoming `'-1250.00`.
     *  - **Single characters pass through.** Several of these reports use a lone
     *    `-` as their "no value" placeholder. One character cannot be a formula.
     */
    public static function guard(mixed $value): string
    {
        $string = (string) ($value ?? '');

        if ($string === '' || is_numeric($string) || mb_strlen($string) < 2) {
            return $string;
        }

        return preg_match('/^[=+\-@\t\r]/', $string) === 1
            ? "'" . $string
            : $string;
    }
}
