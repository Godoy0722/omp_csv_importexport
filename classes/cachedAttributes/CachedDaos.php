<?php

/**
 * @file plugins/importexport/csv/classes/cachedAttributes/CachedDaos.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedDaos
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief This class is responsible for retrieving cached DAOs.
 */

namespace APP\plugins\importexport\csv\classes\cachedAttributes;

use APP\press\PressDAO;
use APP\publicationFormat\PublicationFormatDAO;
use APP\publicationFormat\PublicationDateDAO;
use PKP\db\DAO;
use PKP\db\DAORegistry;

class CachedDaos
{
    /** @var array<string,DAO> */
    static array $cachedDaos = [];

    /** Retrieves the cached PressDAO instance. */
    public static function getPressDao(): PressDAO
    {
        return self::$cachedDaos['PressDAO'] ??= DAORegistry::getDAO('PressDAO');
    }

    /** Retrieves the cached PublicationFormatDAO instance. */
    public static function getPublicationFormatDao(): PublicationFormatDAO
    {
        return self::$cachedDaos['PublicationFormatDAO'] ??= DAORegistry::getDAO('PublicationFormatDAO');
    }

    /** Retrieves the cached PublicationDateDAO instance. */
    public static function getPublicationDateDao(): PublicationDateDAO
    {
        return self::$cachedDaos['PublicationDateDAO'] ??= DAORegistry::getDAO('PublicationDateDAO');
    }
}
