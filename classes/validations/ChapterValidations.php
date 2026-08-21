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

use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations;

class ChapterValidations
{
    /**
     * Validates monograph chapter fields.
     *
     * All chapter columns are positional lists split on `;` (contributors additionally
     * on `|` per chapter). Empty positions are kept — "Subtitle 1;;Subtitle 3" puts
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
        $titles = array_map('trim', explode(';', (string) ($data->chapterTitle ?? '')));
        $subtitles = array_map('trim', explode(';', (string) ($data->chapterSubtitle ?? '')));
        $abstracts = array_map('trim', explode(';', (string) ($data->chapterAbstract ?? '')));
        $files = array_map('trim', explode(';', (string) ($data->chapterFiles ?? '')));
        $contributorGroups = array_map('trim', explode('|', (string) ($data->chapterContributors ?? '')));

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
            if (count($values) > $chapterCount) {
                throw new RowValidationException(__(static::chapterFieldErrorKey($field)));
            }

            foreach ($values as $index => $value) {
                if ($value === '') {
                    continue;
                }

                if ($titles[$index] === '') {
                    throw new RowValidationException(__(static::chapterFieldErrorKey($field)));
                }

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

        if (count($contributorGroups) > $chapterCount) {
            throw new RowValidationException(__('plugins.importexport.csv.chapterContributorsWithoutChapterTitle'));
        }

        $authorEntries = array_filter(
            array_map('trim', explode(';', (string) ($data->authors ?? ''))),
            fn(string $entry) => $entry !== ''
        );

        foreach ($contributorGroups as $index => $group) {
            $groupEntries = array_filter(
                array_map('trim', explode(';', $group)),
                fn(string $entry) => $entry !== ''
            );

            if (empty($groupEntries)) {
                continue;
            }

            if ($titles[$index] === '') {
                throw new RowValidationException(__('plugins.importexport.csv.chapterContributorsWithoutChapterTitle'));
            }

            foreach ($groupEntries as $entry) {
                if (!in_array($entry, $authorEntries, true)) {
                    throw new RowValidationException(__('plugins.importexport.csv.invalidChapterContributor', ['contributor' => $entry]));
                }
            }
        }
    }

    /**
     * Locale key for a chapter field that requires a chapterTitle on the same position.
     */
    private static function chapterFieldErrorKey(string $field): string
    {
        return match ($field) {
            'chapterSubtitle' => 'plugins.importexport.csv.chapterSubtitleWithoutChapterTitle',
            'chapterAbstract' => 'plugins.importexport.csv.chapterAbstractWithoutChapterTitle',
            'chapterFiles' => 'plugins.importexport.csv.chapterFilesWithoutChapterTitle',
            default => 'plugins.importexport.csv.chapterFilesWithoutChapterTitle',
        };
    }
}
