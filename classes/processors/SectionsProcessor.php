<?php

/**
 * @file plugins/importexport/csv/classes/processors/SectionsProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the section (series) data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\shared\processors\SectionsProcessor as SharedSectionsProcessor;
use APP\publication\Publication;
use Illuminate\Support\Str;

class SectionsProcessor extends SharedSectionsProcessor
{
    /**
     * Path for a new series named $title: its slug, with "-2", "-3"… appended when the press already uses it.
     *
     * @param string[] $existingPaths Lower-cased paths already used in the press
     */
    public static function generateUniqueSeriesPath(string $title, array $existingPaths): string
    {
        $base = Str::slug($title) ?: 'series';
        $existing = array_fill_keys($existingPaths, true);

        $candidate = $base;
        $suffix = 1;
        while (isset($existing[$candidate])) {
            $candidate = $base . '-' . ++$suffix;
        }

        return $candidate;
    }

    /**
     * Create a series from the row's seriesTitle and seriesPath, and assign it to the publication.
     * An empty title takes the path; an empty path is generated from the title, so no series has an empty path.
     *
     * @overrides shared method
     */
    public static function newSectionToPublication(object $data, int $contextId, Publication $publication): void
    {
        $seriesPath = mb_strtolower(trim($data->seriesPath ?? ''));
        $seriesTitle = trim($data->seriesTitle ?? '') ?: $seriesPath;

        if ($seriesTitle === '') {
            return;
        }

        if ($seriesPath === '') {
            $seriesPath = static::generateUniqueSeriesPath($seriesTitle, CachedEntities::getSectionPaths($contextId));
        }

        $section = Repo::section()->newDataObject();

        $section->setContextId($contextId);
        $section->setSequence(REALLY_BIG_NUMBER);
        $section->setEditorRestricted(false);
        $section->setIsInactive(false);
        $section->setTitle($seriesTitle, $data->locale);
        $section->setPath($seriesPath);

        $sectionId = Repo::section()->add($section);

        $createdSection = Repo::section()->get($sectionId, $contextId);
        CachedEntities::indexSection($createdSection, $contextId);

        PublicationProcessor::updateSeriesId($publication, $sectionId);
    }

    /**
     * Assign the row's series to the publication, creating it when the press has none that matches.
     *
     * The path is unique in a press, so a row with a seriesPath is matched by path alone (its title may be
     * in another locale). A row with only a seriesTitle is matched by title in the row locale.
     * A row with neither inherits the series of the base publication, if any.
     */
    public static function process(object $data, int $pressId, Publication $publication, ?Publication $basePublication = null): void
    {
        $seriesTitle = trim($data->seriesTitle ?? '');
        $seriesPath = trim($data->seriesPath ?? '');

        if ($seriesTitle === '' && $seriesPath === '') {
            $baseSeriesId = $basePublication?->getData('seriesId');
            if (!is_null($baseSeriesId)) {
                $locale = $basePublication->getData('locale') ?? $data->locale;
                $section = CachedEntities::getCachedSectionById($baseSeriesId, $pressId, $locale);

                if (!is_null($section)) {
                    PublicationProcessor::updateSeriesId($publication, $section->getId());
                }
            }

            return;
        }

        $section = CachedEntities::getCachedSection(
            $seriesPath === '' ? $seriesTitle : '',
            $seriesPath,
            $data->locale,
            $pressId
        );

        if (!is_null($section)) {
            PublicationProcessor::updateSeriesId($publication, $section->getId());
            return;
        }

        static::newSectionToPublication($data, $pressId, $publication);
    }
}
