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

class SectionsProcessor extends SharedSectionsProcessor
{
    /**
     * @overrides shared method
     */
    public static function newSectionToPublication(object $data, int $contextId, Publication $publication): void
    {
        $section = Repo::section()->newDataObject();

        $section->setContextId($contextId);
        $section->setSequence(REALLY_BIG_NUMBER);
        $section->setEditorRestricted(false);
        $section->setIsInactive(false);
        $section->setTitle($data->sectionTitle, $data->locale);
        $section->setPath(mb_strtolower(trim($data->sectionAbbrev)));

        $sectionId = Repo::section()->add($section);

        $createdSection = Repo::section()->get($sectionId, $contextId);
        $customSectionKey = $data->sectionTitle . '_' . mb_strtoupper(trim($data->sectionAbbrev));
        CachedEntities::$sections[$customSectionKey] = $createdSection;

        PublicationProcessor::updateSeriesId($publication, $sectionId);
    }

    public static function process(object $data, int $pressId, Publication $publication, ?Publication $basePublication = null): void
    {
        // For versioned imports without series data, inherit from base publication
        if (empty($data->seriesTitle) && empty($data->seriesPath) && !is_null($basePublication)) {
            $baseSeriesId = $basePublication->getData('seriesId');
            $locale = $basePublication->getData('locale') ?? $data->locale;

            if (!is_null($baseSeriesId)) {
                $section = CachedEntities::getCachedSectionById($baseSeriesId, $pressId, $locale);

                if (!is_null($section)) {
                    PublicationProcessor::updateSeriesId($publication, $section->getId());
                    return;
                }
            }
        }

        // Try lookup by seriesPath first (OMP-specific)
        if (!empty($data->seriesPath)) {
            $section = CachedEntities::getCachedSectionByPath($data->seriesPath, $pressId);

            if (!is_null($section)) {
                PublicationProcessor::updateSeriesId($publication, $section->getId());
                return;
            }
        }

        // Try lookup by title + abbrev (shared pattern — seriesTitle used as sectionTitle)
        if (!empty($data->seriesTitle)) {
            $sectionAbbrev = $data->seriesPath ?? $data->seriesTitle;
            $section = CachedEntities::getCachedSection($data->seriesTitle, $sectionAbbrev, $data->locale, $pressId);

            if (!is_null($section)) {
                PublicationProcessor::updateSeriesId($publication, $section->getId());
                return;
            }

            $data->sectionTitle = $data->seriesTitle;
            $data->sectionAbbrev = $data->seriesPath ?? mb_strtolower(trim($data->seriesTitle));

            static::newSectionToPublication($data, $pressId, $publication);
        }
    }
}
