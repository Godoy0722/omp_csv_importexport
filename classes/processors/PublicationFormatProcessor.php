<?php

/**
 * @file plugins/importexport/csv/classes/processors/PublicationFormatProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationFormatProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes OMP-specific publication format data (ONIX codes, publication dates).
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class PublicationFormatProcessor
{
    /**
     * Create a digital PDF publication format with ONIX codes.
     * The format gets no DOI: the row DOI belongs to the publication only.
     *
     * @return int The publication format ID
     */
    public static function createPublicationFormat(int $publicationId): int
    {
        $publicationFormatDao = CachedDaos::getPublicationFormatDao();

        $publicationFormat = $publicationFormatDao->newDataObject();
        $publicationFormat->setData('publicationId', $publicationId);
        $publicationFormat->setPhysicalFormat(false);
        $publicationFormat->setIsApproved(true);
        $publicationFormat->setIsAvailable(true);
        $publicationFormat->setProductAvailabilityCode('20'); // ONIX code for Available
        $publicationFormat->setEntryKey('DA'); // ONIX code for Digital
        $publicationFormat->setSequence(REALLY_BIG_NUMBER);
        $publicationFormatDao->insertObject($publicationFormat);

        return $publicationFormat->getId();
    }

    /**
     * Create a publication date entry for a publication format.
     */
    public static function createPublicationDate(int $publicationFormatId, string $year): void
    {
        $publicationDateDao = CachedDaos::getPublicationDateDao();

        $publicationDate = $publicationDateDao->newDataObject();
        $publicationDate->setDateFormat('05'); // List55: YYYY
        $publicationDate->setRole('01'); // List163: Publication Date
        $publicationDate->setDate($year);
        $publicationDate->setPublicationFormatId($publicationFormatId);
        $publicationDateDao->insertObject($publicationDate);
    }

    /**
     * Create and associate a submission file with a publication format.
     * The file is named $fileName in $locale, the row locale (a file has no language of its own in OMP).
     * Assumes open access with no price.
     */
    public static function createSubmissionFile(
        int $submissionId,
        int $publicationFormatId,
        int $fileId,
        int $genreId,
        string $locale,
        User $user,
        string $fileName,
        ?string $mimeType = null
    ): void {
        $submissionFile = Repo::submissionFile()->newDataObject();
        $submissionFile->setData('submissionId', $submissionId);
        $submissionFile->setData('uploaderUserId', $user->getId());
        $submissionFile->setData('genreId', $genreId);
        $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_PROOF);
        $submissionFile->setData('assocType', Application::ASSOC_TYPE_REPRESENTATION);
        $submissionFile->setData('assocId', $publicationFormatId);
        $submissionFile->setData('mimetype', $mimeType ?? 'application/pdf');
        $submissionFile->setData('fileId', $fileId);
        $submissionFile->setData('name', $fileName, $locale);

        // Assume open access, no price, viewable
        $submissionFile->setDirectSalesPrice(0);
        $submissionFile->setSalesType('openAccess');
        $submissionFile->setData('viewable', true);

        Repo::submissionFile()->add($submissionFile);
    }
}
