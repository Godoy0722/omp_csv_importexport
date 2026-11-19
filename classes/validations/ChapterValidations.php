<?php

/**
 * @file plugins/importexport/csv/classes/validations/ChapterValidations.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChapterValidations
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Validates the chapter columns of a monograph CSV row
 */

namespace APP\plugins\importexport\csv\classes\validations;

use APP\plugins\importexport\csv\classes\processors\ChaptersProcessor;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations;

class ChapterValidations
{
    /**
     * Validates monograph chapter fields.
     *
     * All chapter columns are positional lists split on `;` (contributors are first
     * split on `#` per chapter, outside quotes). Empty positions are kept — "Subtitle 1;;Subtitle 3" puts
     * values on chapters 1 and 3 of "Title 1;Title 2;Title 3". Every chapter field
     * other than chapterTitle requires a non-empty chapterTitle on the same position,
     * and no list may have more positions than the chapterTitle list.
     * In chapterFiles, `,` separates the files of one chapter. Each contributor entry
     * must be a raw (trimmed) copy of an authors-column entry.
     *
     * @throws RowValidationException
     */
    public static function validateChapterFields(object $data, string $sourceDir): void
    {
        // Same split as the import itself, so validation and processing never disagree.
        [$titles, $subtitles, $abstracts, $files, $contributorGroups] = ChaptersProcessor::parseChapterData($data);

        $allEmpty = !array_filter($titles) && !array_filter($subtitles) && !array_filter($abstracts)
            && !array_filter($files) && !array_filter($contributorGroups);

        if ($allEmpty) {
            return;
        }

        $chapterCount = count($titles);

        foreach ([
            'chapterSubtitle' => $subtitles,
            'chapterAbstract' => $abstracts,
            'chapterFiles' => $files,
        ] as $field => $values) {
            static::assertNoMorePositionsThanTitles($field, count($values), $chapterCount);

            foreach ($values as $index => $value) {
                if ($value === '') {
                    continue;
                }

                static::assertTitleAtPosition($field, $titles, $index);

                if ($field === 'chapterFiles') {
                    // `;` separates chapters, `,` separates the files of one chapter.
                    foreach (array_filter(array_map('trim', explode(',', $value)), fn(string $filename) => $filename !== '') as $chapterFilename) {
                        InvalidRowValidations::validatePathWithinSourceDir($chapterFilename, $sourceDir);
                        $chapterPath = "{$sourceDir}/{$chapterFilename}";
                        if (!is_readable($chapterPath)) {
                            throw new RowValidationException(__('plugins.importexport.csv.invalidChapterFile', ['filename' => $chapterFilename]));
                        }
                    }
                }
            }
        }

        static::assertNoMorePositionsThanTitles('chapterContributors', count($contributorGroups), $chapterCount);

        $authorEntries = array_filter(
            AuthorsProcessor::splitAuthorEntries((string) ($data->authors ?? '')),
            fn(string $entry) => $entry !== ''
        );

        foreach ($contributorGroups as $index => $groupEntries) {
            if (empty($groupEntries)) {
                continue;
            }

            static::assertTitleAtPosition('chapterContributors', $titles, $index);

            foreach ($groupEntries as $entry) {
                if (!in_array($entry, $authorEntries, true)) {
                    throw new RowValidationException(__('plugins.importexport.csv.invalidChapterContributor', ['contributor' => $entry]));
                }
            }
        }
    }

    /**
     * @throws RowValidationException when a chapter list has more positions than chapterTitle
     */
    private static function assertNoMorePositionsThanTitles(string $field, int $count, int $expected): void
    {
        if ($count > $expected) {
            throw new RowValidationException(__('plugins.importexport.csv.chapterFieldHasMorePositions', [
                'field' => $field,
                'count' => $count,
                'expected' => $expected,
            ]));
        }
    }

    /**
     * @throws RowValidationException when chapterTitle is empty at the (zero-based) position of a filled value
     */
    private static function assertTitleAtPosition(string $field, array $titles, int $index): void
    {
        if ($titles[$index] === '') {
            throw new RowValidationException(__('plugins.importexport.csv.chapterFieldWithoutChapterTitle', [
                'field' => $field,
                'position' => $index + 1,
            ]));
        }
    }
}
