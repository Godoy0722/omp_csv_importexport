<?php

/**
 * @file plugins/importexport/csv/classes/validations/InvalidRowValidations.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvalidRowValidations
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief OMP row validations: the shared ones, plus the OMP forms of those that take values OMP cannot store.
 */

namespace APP\plugins\importexport\csv\classes\validations;

use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations as SharedInvalidRowValidations;
use APP\press\Press;

class InvalidRowValidations extends SharedInvalidRowValidations
{
    /**
     * Validates the htmlGalley column: the first file is .html or .htm, and every file exists inside the source directory.
     *
     * OMP form of validateHtmlGalleys(), without its htmlGalleyLocale: OMP publication formats and their files have
     * no language of their own, so the monograph CSV has no htmlGalleyLocale column. (PHP does not allow an override
     * to drop a parameter, hence the separate name.)
     *
     * @throws RowValidationException
     */
    public static function validateHtmlGalley(?string $htmlGalley, string $sourceDir, Press $press, string $contextName): void
    {
        parent::validateHtmlGalleys($htmlGalley, null, $sourceDir, $press, $contextName);
    }
}
