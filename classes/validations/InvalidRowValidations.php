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
 * @brief Class to validate all necessary requirements for a CSV row to be valid
 */

namespace APP\plugins\importexport\csv\classes\validations;

use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations as SharedInvalidRowValidations;

class InvalidRowValidations extends SharedInvalidRowValidations
{
    // OMP has no subscription-specific validations.
    // All shared validations (context, locale, genre, user group, cover image,
    // galley, supplementary files, references, funders, DOI, etc.) are inherited.
}
