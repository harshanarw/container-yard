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

    /** The same, for a streamed download, cleaning up the temp file it needs. */
    protected function workbookTextFromResponse($response, string $prefix = 'workbook-test-'): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        file_put_contents($path, $response->streamedContent());

        try {
            return $this->workbookText($path);
        } finally {
            @unlink($path);
        }
    }
}
