<?php

/**
 * @file plugins/importexport/csv/classes/processors/ChaptersProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChaptersProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the chapter data (title, subtitle, abstract, files, and
 * contributors) into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\monograph\Chapter;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\processors\AuthorsProcessor;
use APP\publication\Publication;
use APP\submissionFile\DAO;
use PKP\file\FileManager;
use PKP\services\PKPFileService;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class ChaptersProcessor
{
    /**
     * Processes chapter data for a CSV row.
     *
     * All chapter columns are positional lists: one row can define several
     * chapters, and chapterSubtitle/chapterAbstract/chapterFiles entries land
     * on the chapter at the same position. chapterContributors groups chapters
     * on `|`. Every field other than chapterTitle requires a chapterTitle on
     * the same position (enforced by validateChapterFields).
     *
     * @param array $fileContext File handling dependencies:
     *        fileManager, fileService, submissionId, pressId, genreId, user,
     *        locale, format
     */
    public static function process(object $data, Publication $publication, string $sourceDir, bool $dryMode, array $fileContext): ?Chapter
    {
        $chapterTitle = trim((string) ($data->chapterTitle ?? ''));
        $chapterSubtitle = trim((string) ($data->chapterSubtitle ?? ''));
        $chapterAbstract = trim((string) ($data->chapterAbstract ?? ''));
        $chapterFiles = trim((string) ($data->chapterFiles ?? ''));
        $chapterContributors = trim((string) ($data->chapterContributors ?? ''));

        if ($chapterTitle === '' && $chapterSubtitle === '' && $chapterAbstract === ''
            && $chapterFiles === '' && $chapterContributors === '') {
            return null;
        }

        [$titles, $subtitles, $abstracts, $files, $contributorGroups] = static::parseChapterData($data);

        $chapterDao = CachedDaos::getChapterDao();
        $lastChapter = null;

        foreach ($titles as $index => $title) {
            if ($title === '') {
                continue;
            }

            $cachedChapter = CachedEntities::getCachedChapter($title, $data->locale, $publication->getId());
            $isNewChapter = $cachedChapter === null;
            $chapter = $isNewChapter
                ? static::findOrCreateChapter($title, $data->locale, $publication)
                : $cachedChapter;

            $needsUpdate = false;

            $subtitle = $subtitles[$index] ?? '';
            if ($subtitle !== '') {
                $chapter->setSubtitle($subtitle, $data->locale);
                $needsUpdate = true;
            }

            $abstract = $abstracts[$index] ?? '';
            if ($isNewChapter) {
                // Chapter::getAbstract() has a strict string|array return type, so a
                // newly created chapter always carries an abstract, even when empty.
                $chapter->setAbstract($abstract, $data->locale);
                $needsUpdate = $needsUpdate || $abstract !== '';
            } elseif ($abstract !== '') {
                $chapter->setAbstract($abstract, $data->locale);
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $chapterDao->updateObject($chapter);
            }

            if (!$dryMode && ($files[$index] ?? '') !== '') {
                static::processChapterFiles($files[$index], $chapter, $sourceDir, $fileContext);
            }

            if (!empty($contributorGroups[$index])) {
                static::processChapterContributors($contributorGroups[$index], $chapter);
            }

            $lastChapter = $chapter;
        }

        return $lastChapter;
    }

    /**
     * Finds a chapter of the publication by title and locale, creating one when missing.
     * The result is cached in CachedEntities so repeated rows reuse it.
     */
    public static function findOrCreateChapter(string $chapterTitle, string $locale, Publication $publication): Chapter
    {
        $chapter = CachedEntities::getCachedChapter($chapterTitle, $locale, $publication->getId());

        if ($chapter !== null) {
            return $chapter;
        }

        $chapterDao = CachedDaos::getChapterDao();

        $chapter = $chapterDao->newDataObject();
        $chapter->setData('publicationId', $publication->getId());
        $chapter->setTitle($chapterTitle, $locale);
        // Chapter::getAbstract() has a strict string|array return type, so an
        // imported chapter needs an (empty) abstract for the edit form to load.
        $chapter->setAbstract('', $locale);
        $chapter->setSequence(REALLY_BIG_NUMBER);
        $chapterDao->insertChapter($chapter);
        $chapterDao->resequenceChapters($publication->getId());

        CachedEntities::cacheChapter($chapter, $chapterTitle, $locale, $publication->getId());

        return $chapter;
    }

    /**
     * Adds each semicolon-separated file to the submission as a proof file
     * and associates all of them with the chapter.
     *
     * @param array $fileContext See process().
     */
    public static function processChapterFiles(string $chapterFiles, Chapter $chapter, string $sourceDir, array $fileContext): void
    {
        /** @var FileManager $fileManager */
        $fileManager = $fileContext['fileManager'];
        /** @var PKPFileService $fileService */
        $fileService = $fileContext['fileService'];
        /** @var User $user */
        $user = $fileContext['user'];
        $submissionId = $fileContext['submissionId'];
        $pressId = $fileContext['pressId'];
        $genreId = $fileContext['genreId'];
        $locale = $fileContext['locale'];
        $format = $fileContext['format'];

        $filenames = array_filter(
            array_map('trim', explode(',', $chapterFiles)),
            fn(string $filename) => $filename !== ''
        );

        $chapterFileIds = [];
        foreach ($filenames as $filename) {
            $filePath = "{$sourceDir}/{$filename}";
            $extension = $fileManager->parseFileExtension($filename);
            $submissionDir = sprintf($format, $pressId, $submissionId);

            $fileId = $fileService->add(
                $filePath,
                $submissionDir . '/' . uniqid() . '.' . $extension
            );

            $submissionFile = Repo::submissionFile()->newDataObject();
            $submissionFile->setData('submissionId', $submissionId);
            $submissionFile->setData('uploaderUserId', $user->getId());
            $submissionFile->setData('submissionLocale', $locale);
            $submissionFile->setData('genreId', $genreId);
            $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY);
            $submissionFile->setData('mimetype', $fileManager->getDocumentType($filename) ?? 'application/octet-stream');
            $submissionFile->setData('fileId', $fileId);
            $submissionFile->setData('name', $filename, $locale);
            $submissionFile->setDirectSalesPrice(0);
            $submissionFile->setSalesType('openAccess');
            $submissionFile->setData('viewable', true);

            Repo::submissionFile()->add($submissionFile);

            $chapterFileIds[] = $submissionFile->getId();
        }

        /** @var DAO $submissionFileDao */
        $submissionFileDao = Repo::submissionFile()->dao;
        $submissionFileDao->updateChapterFiles($chapterFileIds, $chapter->getId());
    }

    /**
     * Links the authors of each contributor entry to the chapter, replacing its
     * current contributors (mirrors ChapterForm::execute). Contributors are matched
     * strictly against the CSV authors column via the entry-to-author map built by
     * AuthorsProcessor while it processed the authors column.
     *
     * @param string[] $contributors Raw contributor entries
     *
     * @throws RowValidationException When an entry has no matching author entry
     */
    public static function processChapterContributors(array $contributors, Chapter $chapter): void
    {
        $authorIds = [];
        foreach ($contributors as $contributor) {
            $authorId = AuthorsProcessor::$csvAuthorEntryToId[$contributor] ?? null;
            if ($authorId === null) {
                throw new RowValidationException(__('plugins.importexport.csv.invalidChapterContributor', ['contributor' => $contributor]));
            }
            $authorIds[] = $authorId;
        }

        Repo::author()->removeChapterAuthors($chapter);
        foreach ($authorIds as $sequence => $authorId) {
            Repo::author()->addToChapter($authorId, $chapter->getId(), false, $sequence);
        }
    }

    /**
     * Splits the five chapter columns into positional lists. Empty positions are
     * kept so entries land on the chapter at the same position. Contributor groups
     * are split on `|`; empty entries inside a group are dropped.
     */
    public static function parseChapterData(object $data): array
    {
        $titles = array_map('trim', explode(';', (string) ($data->chapterTitle ?? '')));
        $subtitles = array_map('trim', explode(';', (string) ($data->chapterSubtitle ?? '')));
        $abstracts = array_map('trim', explode(';', (string) ($data->chapterAbstract ?? '')));
        $files = array_map('trim', explode(';', (string) ($data->chapterFiles ?? '')));

        $groups = array_map('trim', explode('|', (string) ($data->chapterContributors ?? '')));
        $contributorGroups = array_map(
            fn(string $group) => array_values(array_filter(
                array_map('trim', explode(';', $group)),
                fn(string $entry) => $entry !== ''
            )),
            $groups
        );

        return [$titles, $subtitles, $abstracts, $files, $contributorGroups];
    }
}
