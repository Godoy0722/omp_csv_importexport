<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredMonographHeaders.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredMonographHeaders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate headers in the monograph CSV files
 */

namespace APP\plugins\importexport\csv\classes\validations;

class RequiredMonographHeaders
{
    static $monographHeaders = [
        'pressPath',
        'locale',
        'versionIdentifier',
        'version',
        'monographPrefix',
        'monographTitle',
        'monographSubtitle',
        'monographAbstract',
        'authors',
        'filename',
        'keywords',
        'subjects',
        'coverage',
        'categories',
        'doi',
        'coverImageFilename',
        'coverImageAltText',
        'seriesTitle',
        'seriesPath',
        'year',
        'isEditedVolume',
        'datePublished',
        'copyrightYear',
        'copyrightHolder',
        'licenseUrl',
        'references',
        'htmlGalley',
        'username',
        'funders',
        'supportingAgencies',
        'chapterTitle',
        'chapterFiles',
        'chapterSubtitle',
        'chapterAbstract',
        'chapterContributors',
    ];

    static $monographRequiredHeaders = [
        'pressPath',
        'locale',
        'monographTitle',
        'authors',
        'datePublished',
    ];

    public static function validateRowHasAllFields(array $row): bool
    {
        return count($row) === count(self::$monographHeaders);
    }

    public static function validateRowHasAllRequiredFields(object $row, array $processedMonographs = []): bool
    {
        $isMultiVersionOrLocale = !empty($row->versionIdentifier)
            && !empty($row->version)
            && isset($processedMonographs[$row->versionIdentifier]);

        if ($isMultiVersionOrLocale) {
            return true;
        }

        foreach (self::$monographRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                return false;
            }
        }

        return true;
    }
}
