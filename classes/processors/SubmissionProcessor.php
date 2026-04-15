<?php

/**
 * @file plugins/importexport/csv/classes/processors/SubmissionProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Processes the submission data into the database.
 */

namespace APP\plugins\importexport\csv\classes\processors;

use APP\facades\Repo;
use APP\press\Press;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\plugins\importexport\csv\shared\processors\SubmissionProcessor as SharedSubmissionProcessor;

class SubmissionProcessor extends SharedSubmissionProcessor
{
    public static function process(object $data, Publication $publication, Press $press): Submission
    {
        $normalizedAbstract = PublicationProcessor::normalizeAbstractToHtml($data->monographAbstract ?? '');
        $submission = parent::processCommons($data->locale, $publication, $press, $normalizedAbstract, $data->datePublished);

        $submission->setData('workType', $data->isEditedVolume == 1
            ? Submission::WORK_TYPE_EDITED_VOLUME
            : Submission::WORK_TYPE_AUTHORED_WORK);
        Repo::submission()->edit($submission, []);

        return Repo::submission()->get($submission->getId());
    }
}
