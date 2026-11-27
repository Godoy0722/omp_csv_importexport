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

use APP\monograph\Chapter;
use APP\press\Press;
use APP\section\Section;
use PKP\db\DAORegistry;
use PKP\submission\GenreDAO;
use APP\plugins\importexport\csv\shared\cachedAttributes\CachedEntities as SharedCachedEntities;

class CachedEntities extends SharedCachedEntities
{
    /** @var array<string,Press|null> */
    static array $presses = [];

    /** @var array<string,bool> Press-scoped cache of existing DOIs from the database */
    static array $existingDoisByPress = [];

    /** @var array<string,Chapter> Cache of chapters keyed by publicationId, title, and locale */
    static array $chapters = [];

    /** @var array<int,array<string,int>> Enabled dependent genre IDs per press, keyed by genre key in display order */
    static array $dependentGenreIds = [];

    /** File extensions whose dependent files get the Style genre; every other dependent file gets Image */
    private const STYLE_EXTENSIONS = ['css', 'woff', 'woff2', 'ttf', 'otf', 'eot'];

    /** Resets all cached entities. Used after dry-mode rollback to clear stale IDs. */
    public static function reset(): void
    {
        parent::reset();

        static::$presses = [];
        static::$existingDoisByPress = [];
        static::$chapters = [];
        static::$dependentGenreIds = [];
    }

    /**
     * Retrieves the dependent genre for an HTML file's dependent file, as an editor would pick it:
     * Style for stylesheets and fonts, Image for anything else. Falls back to the press's first
     * enabled dependent genre, or null when the press has none.
     * OMP only serves an HTML file's dependent files when their genre is a dependent one.
     */
    public static function getCachedDependentGenreId(int $pressId, string $filename): ?int
    {
        if (!isset(static::$dependentGenreIds[$pressId])) {
            $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
            $genres = $genreDao->getByDependenceAndContextId(true, $pressId);
            static::$dependentGenreIds[$pressId] = [];
            while ($genre = $genres->next()) {
                static::$dependentGenreIds[$pressId][$genre->getKey()] = $genre->getId();
            }
        }

        $genreIds = static::$dependentGenreIds[$pressId];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $genreKey = in_array($extension, self::STYLE_EXTENSIONS, true) ? 'STYLE' : 'IMAGE';

        return $genreIds[$genreKey] ?? (reset($genreIds) ?: null);
    }

    /**
     * Retrieves all existing DOIs for a given press from the database.
     * Results are cached statically per press ID.
     *
     * @return array<string,bool> Associative map keyed by normalized DOI
     */
    public static function getExistingDois(int $pressId): array
    {
        if (isset(static::$existingDoisByPress[$pressId])) {
            return static::$existingDoisByPress[$pressId];
        }

        $dois = \Illuminate\Support\Facades\DB::table('dois')
            ->where('context_id', $pressId)
            ->whereNotNull('doi')
            ->distinct()
            ->pluck('doi');

        $doiMap = [];
        foreach ($dois as $rawDoi) {
            $normalized = \APP\plugins\importexport\csv\shared\validations\InvalidRowValidations::normalizeVorDoi($rawDoi);
            if ($normalized !== null) {
                $doiMap[$normalized] = true;
            }
        }

        return static::$existingDoisByPress[$pressId] = $doiMap;
    }

    /**
     * Retrieves a cached Chapter of a publication by its title in a given locale.
     * Returns null if no chapter of that publication has that title in that locale.
     */
    static function getCachedChapter(string $chapterTitle, string $locale, int $publicationId): ?Chapter
    {
        $cacheKey = "{$publicationId}_{$chapterTitle}_{$locale}";

        if (isset(static::$chapters[$cacheKey])) {
            return static::$chapters[$cacheKey];
        }

        $chapters = CachedDaos::getChapterDao()->getByPublicationId($publicationId)->toArray();

        foreach ($chapters as $chapter) {
            if ($chapter->getData('title', $locale) === $chapterTitle) {
                return static::$chapters[$cacheKey] = $chapter;
            }
        }

        return null;
    }

    /** Stores a Chapter in the cache so subsequent rows reuse it without a database scan. */
    static function cacheChapter(Chapter $chapter, string $chapterTitle, string $locale, int $publicationId): void
    {
        static::$chapters["{$publicationId}_{$chapterTitle}_{$locale}"] = $chapter;
    }

    /** Retrieves a cached Press by its path. Returns null if not found. */
    static function getCachedPress(string $pressPath): ?Press
    {
        $pressDao = CachedDaos::getPressDao();
        return self::$presses[$pressPath] ?? self::$presses[$pressPath] = $pressDao->getByPath($pressPath);
    }

    /**
     * OMP series have no abbreviation: a row identifies a series by its path.
     *
     * @overrides SharedCachedEntities::sectionAbbrevOf()
     */
    protected static function sectionAbbrevOf(Section $section, string $locale): string|array|null
    {
        return $section->getPath();
    }

    /**
     * Paths of the press's series, lower-cased (paths are unique per press).
     *
     * @return string[]
     */
    static function getSectionPaths(int $contextId): array
    {
        return array_values(array_filter(array_map(
            fn (Section $section) => mb_strtolower(trim((string) $section->getPath())),
            static::getSectionsForContext($contextId)
        )));
    }
}
