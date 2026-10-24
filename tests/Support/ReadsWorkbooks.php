<?php

namespace Tests\Support;

/**
 * Reading the text out of a generated .xlsx.
 *
 * Two traps, and a test that writes this loop by hand will usually fall into
 * both. They are separate, and only the first one has been written down before.
 *
 * **Every XML part, not just shared strings.** openspout writes cell text
 * *inline* into `xl/worksheets/sheet1.xml` and leaves `xl/sharedStrings.xml` an
 * empty stub. A test reading only the latter gets an empty haystack and every
 * `assertStringContainsString` against it fails, on any host that can write the
 * workbook at all.
 *
 * **The parts are XML, so the text in them is escaped.** A customer called
 * `O'Kon LLC` is written `O&#039;Kon LLC`, and asserting the raw name against
 * the raw bytes fails. This is the nastier of the two because it is invisible
 * until a fixture happens to contain an apostrophe or an ampersand — Faker
 * company names produce both — so the test passes for months and then fails on
 * a run that changed nothing. Decoded once, here, where every caller gets it.
 */
trait ReadsWorkbooks
{
    /**
     * The readable text of every XML part of an .xlsx, concatenated.
     *
     * Not XML afterwards — entity-decoded, so it is a haystack for assertions
     * about what the sheet *says*, which is what these tests are about.
     */
    protected function workbookText(string $path): string
    {
        return $this->readXlsxParts($path);
    }

    /** The same, for a streamed download, cleaning up the temp file it needs. */
    protected function workbookTextFromResponse($response, string $prefix = 'workbook-test-'): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        file_put_contents($path, $response->streamedContent());

        try {
            return $this->readXlsxParts($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * The actual read, under a name a test is unlikely to have taken.
     *
     * Both methods above delegate here rather than to each other, and that is
     * not tidiness. A class's own method always wins over a trait's, with no
     * error and no signature check — so a test that already had a private
     * `workbookText()` of its own silently kept it, and
     * `workbookTextFromResponse()` calling `$this->workbookText()` reached the
     * test's version instead. That version fetched the export and called back
     * in: mutual recursion about 2,670 levels deep, which surfaced first as a
     * 1 GB memory exhaustion and then as `tempnam()` complaining, because the
     * `finally` that removes the temp file never runs on a call that never
     * returns.
     *
     * Nothing can stop a class shadowing a trait method. What helps is not
     * routing the trait's internals through a name a test would plausibly use.
     */
    private function readXlsxParts(string $path): string
    {
        $zip = new \ZipArchive();

        $this->assertTrue($zip->open($path) === true, 'The workbook must be a readable xlsx.');

        $text = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (str_ends_with($name, '.xml')) {
                $text .= $zip->getFromName($name);
            }
        }

        $zip->close();

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
