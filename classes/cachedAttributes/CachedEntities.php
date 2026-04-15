<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedEntities.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedEntities
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief This class is responsible for retrieving cached entities such as
 * presses, user groups, genres, categories, and sections.
 */

namespace APP\plugins\importexport\csv\classes\cachedAttributes;

use APP\facades\Repo;
use APP\press\Press;
use APP\section\Section;
use APP\plugins\importexport\csv\shared\cachedAttributes\CachedEntities as SharedCachedEntities;

class CachedEntities extends SharedCachedEntities
{
    /** @var array<string,Press|null> */
    static array $presses = [];

    /** Resets all cached entities. Used after dry-mode rollback to clear stale IDs. */
    public static function reset(): void
    {
        parent::reset();

        static::$presses = [];
    }

    /** Retrieves a cached Press by its path. Returns null if not found. */
    static function getCachedPress(string $pressPath): ?Press
    {
        $pressDao = CachedDaos::getPressDao();
        return self::$presses[$pressPath] ?? self::$presses[$pressPath] = $pressDao->getByPath($pressPath);
    }

    /** Retrieves a cached Section (series) by its path and contextId. Returns null if not found. */
    static function getCachedSectionByPath(string $seriesPath, int $contextId): ?Section
    {
        $cacheKey = "path_{$seriesPath}_{$contextId}";

        if (isset(static::$sections[$cacheKey])) {
            return static::$sections[$cacheKey];
        }

        $section = Repo::section()->getByPath($seriesPath, $contextId);

        if ($section) {
            static::$sections[$cacheKey] = $section;
            static::$sections["sectionId_{$section->getId()}"] = $section;
        }

        return static::$sections[$cacheKey] ?? null;
    }

    /**
     * Override shared method — OMP Section uses getPath() instead of getAbbrev().
     * Retrieves a cached Section by title and path, for a given locale and contextId.
     */
    static function getCachedSection(string $sectionTitle, string $sectionAbbrev, string $locale, int $contextId): ?Section
    {
        $customSectionKey = $sectionTitle . '_' . mb_strtoupper(trim($sectionAbbrev));

        if (isset(static::$sections[$customSectionKey])) {
            return static::$sections[$customSectionKey];
        }

        $sections = Repo::section()->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($sections as $section) {
            $sectionPath = $section->getPath() ?? '';
            if ($sectionPath === $sectionAbbrev && $section->getTitle($locale) === $sectionTitle) {
                static::$sections["sectionId_{$section->getId()}"] = $section;
                return static::$sections[$customSectionKey] = $section;
            }
        }

        return null;
    }

    /**
     * Override shared method — OMP Section uses getPath() instead of getAbbrev().
     */
    static function getCachedSectionById(int $baseSectionId, int $contextId, string $locale): ?Section
    {
        if (isset(static::$sections["sectionId_{$baseSectionId}"])) {
            return static::$sections["sectionId_{$baseSectionId}"];
        }

        $section = Repo::section()->get($baseSectionId, $contextId);
        if (!$section) {
            return null;
        }

        $sectionTitle = $section->getTitle($locale);
        $sectionPath = $section->getPath() ?? '';
        $customSectionKey = $sectionTitle . '_' . mb_strtoupper(trim($sectionPath));

        static::$sections["sectionId_{$baseSectionId}"] = $section;
        static::$sections[$customSectionKey] = $section;

        return $section;
    }
}
