<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/SectionsProcessorTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionsProcessorTest
 *
 * @brief Tests for the OMP series lookup (title only, path only, or both) and series creation
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\publication\Publication;
use APP\section\Section;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SectionsProcessor::class)]
#[CoversClass(CachedEntities::class)]
class SectionsProcessorTest extends BaseTestCase
{
    private MockInterface $sectionRepository;

    /** @var Section[] Series created through the mocked repository */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPublicationRepository();
        $this->created = [];
    }

    /**
     * Registers the series of the press, loaded once, and records the series the processor creates.
     */
    private function givenSeries(array $series): void
    {
        $this->sectionRepository = $this->mockSectionRepository();
        $this->sectionRepository->shouldReceive('getCollector')
            ->andReturnUsing(fn () => $this->createMockSectionCollector($series));
        $this->sectionRepository->shouldReceive('add')->andReturnUsing(function (Section $section) {
            $section->setId(100 + count($this->created));
            $this->created[] = $section;
            return $section->getId();
        });
        $this->sectionRepository->shouldReceive('get')->andReturnUsing(
            fn (int $id) => current(array_filter($this->created, fn (Section $s) => $s->getId() === $id)) ?: null
        );
    }

    private function series(int $id, string $title, string $path, string $locale = 'en'): Section
    {
        $section = new Section();
        $section->setId($id);
        $section->setContextId(1);
        $section->setTitle($title, $locale);
        $section->setPath($path);

        return $section;
    }

    private function row(string $title, string $path, string $locale = 'en'): object
    {
        return (object) ['seriesTitle' => $title, 'seriesPath' => $path, 'locale' => $locale];
    }

    private function process(object $row, ?Publication $base = null): Publication
    {
        $publication = new Publication();
        SectionsProcessor::process($row, 1, $publication, $base);

        return $publication;
    }

    // ==================== Lookup ====================

    public function testTitleOnlyRowFindsSeriesByTitle(): void
    {
        $this->givenSeries([$this->series(3, 'Library Studies', 'lis')]);

        $this->assertSame(3, $this->process($this->row('  Library Studies ', ''))->getData('seriesId'));
        $this->assertSame([], $this->created);
    }

    public function testPathOnlyRowFindsSeriesByPathIgnoringCase(): void
    {
        $this->givenSeries([$this->series(3, 'Library Studies', 'lis')]);

        $this->assertSame(3, $this->process($this->row('', ' LIS '))->getData('seriesId'));
        $this->assertSame([], $this->created);
    }

    /**
     * The path is unique in a press, so a translated title on an extra-locale row still finds the series.
     */
    public function testRowWithPathMatchesByPathEvenWhenTitleDiffers(): void
    {
        $this->givenSeries([$this->series(3, 'Library Studies', 'lis')]);

        $this->assertSame(3, $this->process($this->row('Estudos de Biblioteca', 'lis', 'pt_BR'))->getData('seriesId'));
        $this->assertSame([], $this->created);
    }

    public function testLowestIdWinsWhenSeveralSeriesShareTheTitle(): void
    {
        $this->givenSeries([$this->series(7, 'Essays', 'essays-b'), $this->series(4, 'Essays', 'essays-a')]);

        $this->assertSame(4, $this->process($this->row('Essays', ''))->getData('seriesId'));
    }

    public function testRowWithoutSeriesInheritsTheBaseSeries(): void
    {
        $this->givenSeries([]);
        CachedEntities::indexSection($this->series(5, 'Base Series', 'base'), 1);
        $base = new Publication();
        $base->setData('seriesId', 5);

        $this->assertSame(5, $this->process($this->row('', ''), $base)->getData('seriesId'));
    }

    public function testRowWithoutSeriesAndWithoutBaseGetsNoSeries(): void
    {
        $this->givenSeries([]);

        $this->assertNull($this->process($this->row('', ''))->getData('seriesId'));
        $this->assertSame([], $this->created);
    }

    // ==================== Creation ====================

    public function testTitleOnlyRowCreatesSeriesWithSlugPath(): void
    {
        $this->givenSeries([]);

        $publication = $this->process($this->row('Library & Information Studies', ''));

        $this->assertCount(1, $this->created);
        $this->assertSame('library-information-studies', $this->created[0]->getPath());
        $this->assertSame('Library & Information Studies', $this->created[0]->getData('title', 'en'));
        $this->assertSame($this->created[0]->getId(), $publication->getData('seriesId'));
    }

    public function testGeneratedPathAvoidsExistingPaths(): void
    {
        $this->givenSeries([$this->series(3, 'Essays (old)', 'essays'), $this->series(4, 'Other', 'essays-2')]);

        $this->process($this->row('Essays', ''));

        $this->assertSame('essays-3', $this->created[0]->getPath());
    }

    public function testPathOnlyRowCreatesSeriesNamedAfterThePath(): void
    {
        $this->givenSeries([]);

        $this->process($this->row('', 'LIS'));

        $this->assertSame('lis', $this->created[0]->getPath());
        $this->assertSame('lis', $this->created[0]->getData('title', 'en'));
    }

    /**
     * A series created by one row is found by the next row without creating it again.
     */
    public function testSeriesCreatedByOneRowIsReusedByTheNext(): void
    {
        $this->givenSeries([]);

        $first = $this->process($this->row('Essays', ''));
        $second = $this->process($this->row('Essays', ''));
        $third = $this->process($this->row('', 'essays'));

        $this->assertCount(1, $this->created);
        $this->assertSame($first->getData('seriesId'), $second->getData('seriesId'));
        $this->assertSame($first->getData('seriesId'), $third->getData('seriesId'));
    }

    public function testUniqueSeriesPathFallsBackWhenTitleHasNoSlug(): void
    {
        $this->assertSame('series', SectionsProcessor::generateUniqueSeriesPath('!!!', []));
        $this->assertSame('series-2', SectionsProcessor::generateUniqueSeriesPath('???', ['series']));
    }
}
