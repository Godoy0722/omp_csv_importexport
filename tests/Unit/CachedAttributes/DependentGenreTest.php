<?php

/**
 * @file plugins/importexport/csv/tests/Unit/CachedAttributes/DependentGenreTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DependentGenreTest
 *
 * @brief An HTML file's dependent files get the genre an editor would pick: Style for stylesheets and fonts, Image otherwise
 */

namespace APP\plugins\importexport\csv\tests\Unit\CachedAttributes;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CachedEntities::class)]
class DependentGenreTest extends BaseTestCase
{
    private const PRESS_ID = 5;
    private const IMAGE = 74;
    private const STYLE = 75;

    protected function tearDown(): void
    {
        CachedEntities::$dependentGenreIds = [];
        parent::tearDown();
    }

    public static function dependentFiles(): array
    {
        return [
            'stylesheet' => ['html-style.css', self::STYLE],
            'uppercase stylesheet' => ['STYLE.CSS', self::STYLE],
            'woff font' => ['fonts/body.woff', self::STYLE],
            'woff2 font' => ['body.woff2', self::STYLE],
            'truetype font' => ['body.ttf', self::STYLE],
            'opentype font' => ['body.otf', self::STYLE],
            'embedded opentype font' => ['body.eot', self::STYLE],
            'svg image' => ['chart.svg', self::IMAGE],
            'png image' => ['figure.png', self::IMAGE],
            'script' => ['viewer.js', self::IMAGE],
        ];
    }

    #[DataProvider('dependentFiles')]
    public function testDependentFileGetsStyleOrImageGenre(string $filename, int $expectedGenreId): void
    {
        CachedEntities::$dependentGenreIds[self::PRESS_ID] = ['IMAGE' => self::IMAGE, 'STYLE' => self::STYLE];

        $this->assertSame($expectedGenreId, CachedEntities::getCachedDependentGenreId(self::PRESS_ID, $filename));
    }

    public function testFallsBackToFirstDependentGenreWhenTheExpectedOneIsMissing(): void
    {
        CachedEntities::$dependentGenreIds[self::PRESS_ID] = ['IMAGE' => self::IMAGE];

        $this->assertSame(self::IMAGE, CachedEntities::getCachedDependentGenreId(self::PRESS_ID, 'html-style.css'));
    }

    public function testReturnsNullWhenThePressHasNoDependentGenre(): void
    {
        CachedEntities::$dependentGenreIds[self::PRESS_ID] = [];

        $this->assertNull(CachedEntities::getCachedDependentGenreId(self::PRESS_ID, 'html-style.css'));
    }
}
