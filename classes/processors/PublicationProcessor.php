<?php

/**
 * @file plugins/importexport/csv/classes/processors/PublicationProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the publication data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\press\Press;
use APP\plugins\importexport\csv\shared\processors\PublicationProcessor as SharedPublicationProcessor;
use APP\publication\Publication;
use APP\submission\Submission;

class PublicationProcessor extends SharedPublicationProcessor
{
    private const LOCALIZED_FIELDS = [
        'title' => 'monographTitle',
        'subtitle' => 'monographSubtitle',
        'abstract' => 'monographAbstract',
        'prefix' => 'monographPrefix',
        'coverage' => 'coverage',
        'copyrightHolder' => 'copyrightHolder',
    ];

    private const NON_LOCALIZED_FIELDS = ['copyrightYear', 'licenseUrl'];

    /**
     * Create a temporary Publication without association with Submission.
     * This Publication will be used to create the Submission and then updated.
     */
    public static function createInitialPublication(object $data): Publication
    {
        $publication = parent::createInitialPublication($data);

        $publication->setData('datePublished', $data->datePublished);
        $publication->setData('title', $data->monographTitle, $data->locale);

        return $publication;
    }

    /** Update the Publication with all necessary data after the Submission is created. */
    public static function process(Submission $submission, object $data, Press $press, string $sourceDir): Publication
    {
        $submissionPublication = parent::processCommons($submission, $data, $press, $sourceDir);

        if (!empty($data->monographSubtitle)) {
            $submissionPublication->setData('subtitle', $data->monographSubtitle, $data->locale);
        }

        if (!empty($data->monographAbstract)) {
            $normalizedAbstract = static::normalizeAbstractToHtml($data->monographAbstract);
            $submissionPublication->setData('abstract', $normalizedAbstract, $data->locale);
        }

        if (!empty($data->monographPrefix)) {
            $submissionPublication->setData('prefix', $data->monographPrefix, $data->locale);
        }

        $oldPublication = Repo::publication()->get($submissionPublication->getId());
        Repo::publication()->dao->update($submissionPublication, $oldPublication);

        return $submissionPublication;
    }

    /** Update the series (section) ID on the publication. */
    public static function updateSeriesId(Publication $publication, int $seriesId): void
    {
        $publication->setData('seriesId', $seriesId);
        Repo::publication()->dao->update($publication);
    }

    static function updatePublicationAttribute(Publication $publication, string $attribute, mixed $data, ?string $locale = null)
    {
        $publication->setData($attribute, $data, $locale);
        Repo::publication()->dao->update($publication);
    }

    /**
     * Create a new publication version manually to avoid CLI context dependency.
     */
    public static function createPublicationVersion(Publication $basePublication, object $data, Press $press): Publication
    {
        $newPublication = parent::createPublicationVersionCommons($basePublication, $data, $press);

        $coverImage = $basePublication->getData('coverImage');
        if (!empty($coverImage)) {
            $localizedCoverImage = [];
            $localizedCoverImage['coverImage'] = $coverImage;
            $updatedPublication = Repo::publication()->newDataObject(array_merge($newPublication->_data, $localizedCoverImage));
            $updatedPublication->stampModified();
            Repo::publication()->dao->update($updatedPublication, $newPublication);
            $newPublication = Repo::publication()->get($newPublication->getId());
        }

        Repo::publication()->dao->update($newPublication);

        $newPublication = Repo::publication()->get($newPublication->getId());

        return $newPublication;
    }

    /**
     * Process a versioned publication with CSV data.
     */
    public static function processVersionedPublication(
        Publication $publication,
        object $data,
        Publication $basePublication,
        string $sourceDir
    ): Publication {
        parent::processVersionedPublicationCommons(
            $publication,
            $data,
            $basePublication,
            $sourceDir,
            static::LOCALIZED_FIELDS,
            static::NON_LOCALIZED_FIELDS
        );

        $publication->setData('version', (int)$data->version);
        $publication->setData('status', Submission::STATUS_PUBLISHED);

        $datePublished = !empty($data->datePublished) ? $data->datePublished : $basePublication->getData('datePublished');
        $publication->setData('datePublished', $datePublished);

        $oldPublication = Repo::publication()->get($publication->getId());
        Repo::publication()->dao->update($publication, $oldPublication);

        return $publication;
    }

    /**
     * Process multi-locale publication data (adds new locale to existing publication).
     */
    public static function processMultiLocalePublication(Publication $publication, object $data, Press $press): Publication
    {
        return parent::processMultiLocalePublicationCommons($publication, $data, $press, static::LOCALIZED_FIELDS, static::NON_LOCALIZED_FIELDS);
    }
}
