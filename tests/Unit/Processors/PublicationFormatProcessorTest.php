<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/PublicationFormatProcessorTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationFormatProcessorTest
 *
 * @brief Tests for the publication formats and format files created by the monograph import
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\plugins\importexport\csv\classes\processors\PublicationFormatProcessor;
use APP\plugins\importexport\csv\shared\tests\BaseTestCase;
use APP\publicationFormat\PublicationFormat;
use APP\publicationFormat\PublicationFormatDAO;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

#[CoversClass(PublicationFormatProcessor::class)]
class PublicationFormatProcessorTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        CachedDaos::$cachedDaos = [];
        parent::tearDown();
    }

    /**
     * The row DOI belongs to the publication. The format must not store a copy of it, which would add a row to dois.
     */
    public function testCreatePublicationFormatStoresNoDoi(): void
    {
        $format = Mockery::mock(PublicationFormat::class)->makePartial();
        $format->shouldReceive('setStoredPubId')->never();

        $dao = Mockery::mock(PublicationFormatDAO::class);
        $dao->shouldReceive('newDataObject')->andReturn($format);
        $dao->shouldReceive('insertObject')->once()->andReturnUsing(function (PublicationFormat $inserted) {
            $inserted->setId(7);
            return 7;
        });
        $dao->shouldReceive('updateObject')->never();
        CachedDaos::$cachedDaos['PublicationFormatDAO'] = $dao;

        $this->assertSame(7, PublicationFormatProcessor::createPublicationFormat(3));
        $this->assertSame(3, $format->getData('publicationId'));
    }

    /**
     * A file has no language of its own in OMP 3.5 ("locale" is read-only and comes from the submission);
     * the row locale is the language of the file name.
     */
    public function testCreateSubmissionFileNamesTheFileInTheRowLocale(): void
    {
        $captured = null;
        $repository = $this->mockSubmissionFileRepository();
        $repository->shouldReceive('add')->andReturnUsing(function (SubmissionFile $file) use (&$captured) {
            $captured = $file;
            return 42;
        });

        $user = Mockery::mock(User::class);
        $user->shouldReceive('getId')->andReturn(1);

        $submissionFileId = PublicationFormatProcessor::createSubmissionFile(10, 7, 99, 2, 'pt_BR', $user, 'book.html', 'text/html');

        $this->assertSame('book.html', $captured->getData('name', 'pt_BR'));
        $this->assertSame(7, $captured->getData('assocId'));
        // HTML dependent files are attached to this submission file ID, not to the stored file's ID
        $this->assertSame(42, $submissionFileId);
    }

    /**
     * The main monograph file is named too: without a name it shows up unnamed in the publication format.
     */
    public function testCreateSubmissionFileNamesThePdfAndDefaultsItsMimetype(): void
    {
        $captured = null;
        $repository = $this->mockSubmissionFileRepository();
        $repository->shouldReceive('add')->andReturnUsing(function (SubmissionFile $file) use (&$captured) {
            $captured = $file;
            return 1;
        });

        $user = Mockery::mock(User::class);
        $user->shouldReceive('getId')->andReturn(1);

        PublicationFormatProcessor::createSubmissionFile(10, 7, 99, 2, 'en', $user, 'submission.pdf');

        $this->assertSame('submission.pdf', $captured->getData('name', 'en'));
        $this->assertSame('application/pdf', $captured->getData('mimetype'));
    }
}
