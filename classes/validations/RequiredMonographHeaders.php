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
        'monographTitle',
        'monographSubtitle',
        'monographAbstract',
        'authors',
        'submissionFiles', // Reserved: counted, not read yet
        'filename',
        'fileLocale', // Reserved: counted, not read yet
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
        'submissionStatus', // Reserved: counted, not read yet
        'datePublished',
        'copyrightYear',
        'copyrightHolder',
        'licenseUrl',
        'references',
        'htmlGalley',
        'htmlGalleyLocale', // Reserved: counted, not read yet
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

    /**
     * Whether the row may inherit its required fields: it is a later version (version > 1),
     * or it adds a new locale to a version already imported in this run.
     */
    public static function isMultiVersionOrLocale(object $row, array $processedMonographs = []): bool
    {
        if (!empty($row->version) && !empty($row->versionIdentifier) && (int) $row->version > 1) {
            return true;
        }

        if (!empty($row->versionIdentifier) && !empty($row->version)) {
            $identifier = $row->versionIdentifier;
            $version = (int) $row->version;

            if (isset($processedMonographs[$identifier][$version]) && !empty($processedMonographs[$identifier][$version])) {
                return !isset($processedMonographs[$identifier][$version][$row->locale]);
            }
        }

        return false;
    }

    /**
     * @return string[] Required column names that are empty on this row
     */
    public static function missingRequiredFields(object $row, array $processedMonographs = []): array
    {
        if (self::isMultiVersionOrLocale($row, $processedMonographs)) {
            return [];
        }

        $missing = [];
        foreach (self::$monographRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                $missing[] = $requiredHeader;
            }
        }

        return $missing;
    }

    public static function validateRowHasAllRequiredFields(object $row, array $processedMonographs = []): bool
    {
        return self::missingRequiredFields($row, $processedMonographs) === [];
    }
}
