<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Validations/RequiredMonographHeadersTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredMonographHeadersTest
 *
 * @brief Tests for the OMP monograph column layout and its required columns
 */

namespace APP\plugins\importexport\csv\tests\Unit\Validations;

use APP\plugins\importexport\csv\classes\validations\RequiredMonographHeaders;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RequiredMonographHeaders::class)]
class RequiredMonographHeadersTest extends BaseTestCase
{
    /**
     * The layout has 36 columns: monographPrefix is gone and the two reserved columns are present.
     * There is no fileLocale or htmlGalleyLocale column: OMP formats and their files have no language of their own.
     */
    public function testLayoutHasThirtyEightColumnsWithReservedColumns(): void
    {
        $headers = RequiredMonographHeaders::$monographHeaders;

        $this->assertCount(36, $headers);
        $this->assertNotContains('monographPrefix', $headers);
        foreach (['submissionFiles', 'submissionStatus'] as $reserved) {
            $this->assertContains($reserved, $headers);
        }
        $this->assertNotContains('fileLocale', $headers);
        $this->assertNotContains('htmlGalleyLocale', $headers);
        $this->assertSame(count($headers), count(array_unique($headers)));
    }

    /**
     * Failed rows read versionIdentifier from $fields[2], so it must stay at index 2.
     */
    public function testVersionIdentifierStaysAtIndexTwo(): void
    {
        $this->assertSame('versionIdentifier', RequiredMonographHeaders::$monographHeaders[2]);
    }

    /**
     * The reserved columns sit next to the column they extend.
     */
    public function testReservedColumnsArePlacedNextToTheirColumns(): void
    {
        $headers = RequiredMonographHeaders::$monographHeaders;
        $position = array_flip($headers);

        $this->assertSame($position['filename'] - 1, $position['submissionFiles']);
        $this->assertSame($position['datePublished'] - 1, $position['submissionStatus']);
    }

    /**
     * Only the five required columns are reported, in header order; reserved columns are never required.
     */
    public function testMissingRequiredFieldsListsOnlyTheRequiredColumns(): void
    {
        $row = (object) array_fill_keys(RequiredMonographHeaders::$monographHeaders, '');

        $this->assertSame(
            ['pressPath', 'locale', 'monographTitle', 'authors', 'datePublished'],
            RequiredMonographHeaders::missingRequiredFields($row)
        );
    }

    /**
     * A filled row has no missing fields, even with every reserved column empty.
     */
    public function testMissingRequiredFieldsIsEmptyForACompleteRow(): void
    {
        $row = (object) array_fill_keys(RequiredMonographHeaders::$monographHeaders, '');
        $row->pressPath = 'press';
        $row->locale = 'en';
        $row->monographTitle = 'Title';
        $row->authors = 'Ana,Silva,ana@example.com,,,';
        $row->datePublished = '2024-01-15';

        $this->assertSame([], RequiredMonographHeaders::missingRequiredFields($row));
        $this->assertTrue(RequiredMonographHeaders::validateRowHasAllRequiredFields($row));
    }

    public function testLaterVersionIsMultiVersionWithoutProcessedRows(): void
    {
        $row = $this->row(['versionIdentifier' => 'climate-study', 'version' => '2']);

        $this->assertTrue(RequiredMonographHeaders::isMultiVersionOrLocale($row, []));
    }

    public function testExtraLocaleOfAnImportedVersionSkipsRequiredFields(): void
    {
        $processed = ['ml-book-001' => [1 => ['en' => ['submission' => 1]]]];
        $row = $this->row([
            'versionIdentifier' => 'ml-book-001',
            'version' => '1',
            'locale' => 'pt_BR',
            'monographTitle' => '',
            'authors' => '',
            'datePublished' => '',
        ]);

        $this->assertTrue(RequiredMonographHeaders::isMultiVersionOrLocale($row, $processed));
        $this->assertSame([], RequiredMonographHeaders::missingRequiredFields($row, $processed));
    }

    /**
     * A duplicate row for a version and locale already imported must still have its required fields.
     */
    public function testSameVersionAndLocaleStillRequiresFields(): void
    {
        $processed = ['ml-book-001' => [1 => ['en' => ['submission' => 1]]]];
        $row = $this->row(['versionIdentifier' => 'ml-book-001', 'version' => '1', 'monographTitle' => '']);

        $this->assertFalse(RequiredMonographHeaders::isMultiVersionOrLocale($row, $processed));
        $this->assertSame(['monographTitle'], RequiredMonographHeaders::missingRequiredFields($row, $processed));
    }

    public function testVersionOneIsNotWaivedBecauseAnotherVersionOfTheIdentifierExists(): void
    {
        $processed = ['climate-study' => [2 => ['en' => ['submission' => 1]]]];
        $row = $this->row(['versionIdentifier' => 'climate-study', 'version' => '1']);

        $this->assertFalse(RequiredMonographHeaders::isMultiVersionOrLocale($row, $processed));
    }

    public function testFirstVersionOneRowRequiresFields(): void
    {
        $row = $this->row(['versionIdentifier' => 'climate-study', 'version' => '1', 'authors' => '']);

        $this->assertFalse(RequiredMonographHeaders::isMultiVersionOrLocale($row, []));
        $this->assertFalse(RequiredMonographHeaders::validateRowHasAllRequiredFields($row, []));
    }

    public function testLaterVersionRowMayOmitRequiredFields(): void
    {
        $row = $this->row([
            'versionIdentifier' => 'climate-study',
            'version' => '3',
            'monographTitle' => '',
            'authors' => '',
            'datePublished' => '',
        ]);

        $this->assertTrue(RequiredMonographHeaders::validateRowHasAllRequiredFields($row, []));
    }

    public function testVersionWithoutIdentifierIsNotMultiVersion(): void
    {
        $row = $this->row(['versionIdentifier' => '', 'version' => '2']);

        $this->assertFalse(RequiredMonographHeaders::isMultiVersionOrLocale($row, []));
    }

    /**
     * A row in the old 35-column layout is rejected by the column count.
     */
    /**
     * A row still carrying the removed fileLocale and htmlGalleyLocale columns (38 fields) is rejected by the count.
     */
    public function testRowWithTheRemovedLocaleColumnsIsRejected(): void
    {
        $this->expectException(RowValidationException::class);
        $this->expectExceptionMessage('rowContainsTooManyFields');

        InvalidRowValidations::validateRowContainAllFields(
            array_fill(0, 38, ''),
            count(RequiredMonographHeaders::$monographHeaders)
        );
    }

    public function testOldThirtyFiveColumnRowIsRejected(): void
    {
        $this->expectException(RowValidationException::class);
        $this->expectExceptionMessage('rowDoesntContainAllFields');

        InvalidRowValidations::validateRowContainAllFields(
            array_fill(0, 35, ''),
            count(RequiredMonographHeaders::$monographHeaders)
        );
    }

    private function row(array $values): object
    {
        $row = (object) array_fill_keys(RequiredMonographHeaders::$monographHeaders, '');
        foreach (array_merge([
            'pressPath' => 'press',
            'locale' => 'en',
            'monographTitle' => 'Title',
            'authors' => 'Ana,Silva,ana@example.com,,,',
            'datePublished' => '2024-01-15',
        ], $values) as $key => $value) {
            $row->{$key} = $value;
        }

        return $row;
    }
}
