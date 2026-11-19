<?php

/**
 * @file plugins/importexport/csv/classes/validations/RequiredUserHeaders.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredUserHeaders
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Class to validate headers in the user CSV files
 */

namespace APP\plugins\importexport\csv\classes\validations;

class RequiredUserHeaders
{
    static $userHeaders = [
        'pressPath',
        'firstname',
        'lastname',
        'email',
        'affiliation',
        'country',
        'username',
        'tempPassword',
        'roles',
        'reviewInterests',
        'orcid',
    ];

    static $userRequiredHeaders = [
        'pressPath',
        'firstname',
        'email',
        'roles',
    ];

    public static function validateRowHasAllFields(array $row): bool
    {
        return count($row) === count(self::$userHeaders);
    }

    /**
     * @return string[] Required column names that are empty on this row
     */
    public static function missingRequiredFields(object $row): array
    {
        $missing = [];
        foreach (self::$userRequiredHeaders as $requiredHeader) {
            if (!$row->{$requiredHeader}) {
                $missing[] = $requiredHeader;
            }
        }

        return $missing;
    }

    public static function validateRowHasAllRequiredFields(object $row): bool
    {
        return self::missingRequiredFields($row) === [];
    }
}
