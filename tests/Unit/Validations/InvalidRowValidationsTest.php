<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Validations/InvalidRowValidationsTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvalidRowValidationsTest
 *
 * @brief Tests for the OMP row validations
 */

namespace APP\plugins\importexport\csv\tests\Unit\Validations;

use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\press\Press;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvalidRowValidations::class)]
class InvalidRowValidationsTest extends BaseTestCase
{
    private string $sourceDir;

    /** A press with no expectations: any locale lookup would fail the test. */
    private Press $press;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sourceDir = sys_get_temp_dir() . '/csv_omp_validations_' . uniqid();
        mkdir($this->sourceDir);
        file_put_contents("{$this->sourceDir}/book.html", '<p>Book</p>');
        file_put_contents("{$this->sourceDir}/figure.png", 'png');
        $this->press = Mockery::mock(Press::class);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->sourceDir}/*"));
        rmdir($this->sourceDir);
        parent::tearDown();
    }

    /**
     * The HTML file and its dependent files are accepted without any locale check.
     */
    public function testValidateHtmlGalleyAcceptsTheHtmlFileAndItsDependentFiles(): void
    {
        InvalidRowValidations::validateHtmlGalley('book.html;figure.png', $this->sourceDir, $this->press, 'Press');

        $this->addToAssertionCount(1);
    }

    public function testValidateHtmlGalleyAcceptsAnEmptyCell(): void
    {
        InvalidRowValidations::validateHtmlGalley('', $this->sourceDir, $this->press, 'Press');

        $this->addToAssertionCount(1);
    }

    public function testValidateHtmlGalleyRejectsAFirstFileThatIsNotHtml(): void
    {
        $this->expectException(RowValidationException::class);
        $this->expectExceptionMessage('invalidHtmlGalleyFirstFile');

        InvalidRowValidations::validateHtmlGalley('figure.png;book.html', $this->sourceDir, $this->press, 'Press');
    }

    public function testValidateHtmlGalleyRejectsAMissingFile(): void
    {
        $this->expectException(RowValidationException::class);
        $this->expectExceptionMessage('invalidHtmlGalleyFile');

        InvalidRowValidations::validateHtmlGalley('book.html;missing.png', $this->sourceDir, $this->press, 'Press');
    }
}
