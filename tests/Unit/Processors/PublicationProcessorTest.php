<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/PublicationProcessorTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationProcessorTest
 *
 * @brief Tests that the monograph metadata of the first locale is stored, and kept when a row adds another locale
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\facades\Repo;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\press\Press;
use APP\submission\Submission;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PublicationProcessor::class)]
class PublicationProcessorTest extends BaseTestCase
{
    private const PUBLICATION_ID = 5;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStoringPublicationRepository();
    }

    private function row(array $values): object
    {
        return (object) array_merge([
            'monographTitle' => '',
            'monographSubtitle' => '',
            'monographAbstract' => '',
            'coverage' => '',
            'copyrightHolder' => '',
            'copyrightYear' => '',
            'licenseUrl' => '',
            'references' => '',
            'doi' => '',
        ], $values);
    }

    /** Runs process() for the en row of a new monograph whose initial publication is stored. */
    private function importEnglishRow(): void
    {
        $this->storePublication(self::PUBLICATION_ID, ['title' => ['en' => 'Sustainable Urban Design']]);

        $submission = Mockery::mock(Submission::class);
        $submission->shouldReceive('getCurrentPublication')->andReturn(Repo::publication()->get(self::PUBLICATION_ID));

        PublicationProcessor::process(
            $submission,
            $this->row([
                'locale' => 'en',
                'monographTitle' => 'Sustainable Urban Design',
                'monographSubtitle' => 'Cities for people',
                'monographAbstract' => 'An urban design primer.',
                'coverage' => 'global',
                'copyrightHolder' => 'PKP',
            ]),
            Mockery::mock(Press::class),
            sys_get_temp_dir()
        );
    }

    /**
     * Coverage of a new monograph is stored by the en row itself. It used to be set in memory only and was
     * lost unless a later step (the series update) happened to save the publication.
     */
    public function testNewMonographStoresItsCoverage(): void
    {
        $this->importEnglishRow();

        $this->assertSame(['en' => 'global'], Repo::publication()->get(self::PUBLICATION_ID)->getData('coverage'));
    }

    /**
     * After the pt_BR row, every localized monograph field holds both locales.
     */
    public function testExtraLocaleRowKeepsTheFirstLocaleOfEveryLocalizedField(): void
    {
        $this->importEnglishRow();

        PublicationProcessor::processMultiLocalePublication(
            Repo::publication()->get(self::PUBLICATION_ID),
            $this->row([
                'locale' => 'pt_BR',
                'monographTitle' => 'Design Urbano Sustentável',
                'monographSubtitle' => 'Cidades para pessoas',
                'monographAbstract' => 'Um guia de design urbano.',
                'coverage' => 'mundial',
                'copyrightHolder' => 'PKP Brasil',
            ])
        );

        $stored = Repo::publication()->get(self::PUBLICATION_ID);
        $this->assertSame(['en' => 'Sustainable Urban Design', 'pt_BR' => 'Design Urbano Sustentável'], $stored->getData('title'));
        $this->assertSame(['en' => 'Cities for people', 'pt_BR' => 'Cidades para pessoas'], $stored->getData('subtitle'));
        $this->assertSame(['en' => 'global', 'pt_BR' => 'mundial'], $stored->getData('coverage'));
        $this->assertSame(['en' => 'PKP', 'pt_BR' => 'PKP Brasil'], $stored->getData('copyrightHolder'));
        $this->assertSame(['en', 'pt_BR'], array_keys($stored->getData('abstract')));
        $this->assertStringContainsString('An urban design primer.', $stored->getData('abstract', 'en'));
        $this->assertStringContainsString('Um guia de design urbano.', $stored->getData('abstract', 'pt_BR'));
    }
}
