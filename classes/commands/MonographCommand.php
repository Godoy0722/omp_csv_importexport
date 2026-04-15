<?php

/**
 * @file plugins/importexport/csv/classes/commands/MonographCommand.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MonographCommand
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles the monograph import when the user uses the monographs command
 */

namespace APP\plugins\importexport\csv\classes\commands;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos;
use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\PublicationFormatProcessor;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\classes\validations\RequiredMonographHeaders;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\shared\handlers\DryModeReporter;
use APP\plugins\importexport\csv\shared\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\shared\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\shared\processors\FundersProcessor;
use APP\plugins\importexport\csv\shared\processors\KeywordsProcessor;
use APP\plugins\importexport\csv\shared\processors\SubjectsProcessor;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\file\FileManager;
use PKP\services\PKPFileService;
use PKP\user\User;

class MonographCommand
{
    /** Expected row size for a CSV based on the command passed as argument */
    private int $expectedRowSize;

    /** The folder containing all CSV files that the command must go through */
    private string $sourceDir;

    private int $processedRows;

    private int $failedRows;

    private PublicFileManager $publicFileManager;

    private FileManager $fileManager;

    private PKPFileService $fileService;

    private User $user;

    /** @var string[] */
    private array $dirNames;

    private string $format;

    /**
     * Array to track processed monographs by identifier, version, and locale.
     *
     * @var array
     */
    private array $processedMonographs;

    /** @var array Track failed identifiers for cascaded failure detection */
    private array $failedIdentifiers;

    private bool $dryMode;

    public function __construct(string $sourceDir, User $user, bool $dryMode = false)
    {
        $this->expectedRowSize = count(RequiredMonographHeaders::$monographHeaders);
        $this->sourceDir = $sourceDir;
        $this->user = $user;
        $this->dryMode = $dryMode;
        $this->processedMonographs = [];
        $this->failedIdentifiers = [];
    }

    public function run(): array
    {
        $totalFiles = 0;
        $totalPassed = 0;
        $totalFailed = 0;
        $results = [
            'filesProcessed' => 0,
            'totalRows' => 0,
            'successfulRows' => 0,
            'failedRows' => 0,
            'perFile' => [],
        ];

        foreach (new \DirectoryIterator($this->sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'csv') {
                continue;
            }

            $basename = $fileInfo->getBasename();

            // Skip invalid_*.csv files created by previous failed imports
            if (str_starts_with($basename, 'invalid_')) {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $file = CSVFileHandler::createReadableCSVFile($filePath);

            if (is_null($file)) {
                continue;
            }
            $invalidCsvFile = null;

            $this->processedRows = 0;
            $this->failedRows = 0;
            $fileFailedRows = [];

            if ($this->dryMode) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                DB::beginTransaction();
            }

            foreach ($file as $index => $fields) {
                if (!$index || empty(array_filter($fields))) {
                    continue; // Skip headers or end of file
                }

                ++$this->processedRows;

                try {
                    InvalidRowValidations::validateRowContainAllFields($fields, $this->expectedRowSize);

                    $data = (object) array_combine(
                        RequiredMonographHeaders::$monographHeaders,
                        array_pad(array_map('trim', $fields), $this->expectedRowSize, null)
                    );

                    if (
                        !empty($data->versionIdentifier)
                        && !empty($data->version)
                        && !isset($this->processedMonographs[$data->versionIdentifier])
                        && isset($this->failedIdentifiers[$data->versionIdentifier])
                    ) {
                        throw new RowValidationException(
                            __('plugins.importexport.csv.baseRowFailedForIdentifier', [
                                'identifier' => $data->versionIdentifier,
                            ])
                        );
                    }

                    InvalidRowValidations::validateRowHasAllRequiredFieldsCommons($data, function($row) {
                        return RequiredMonographHeaders::validateRowHasAllRequiredFields($row, $this->processedMonographs);
                    });

                    InvalidRowValidations::validateContextVersioningFields($data);

                    if (!empty($data->versionIdentifier)) {
                        InvalidRowValidations::validateNoDuplicateVersion($data, $this->processedMonographs);
                    }

                    if ($data->references) {
                        InvalidRowValidations::validateReferencesFile($data->references, $this->sourceDir);
                    }

                    if ($data->funders) {
                        InvalidRowValidations::validateFunders($data->funders);
                    }

                    $fileUploadUser = $this->user;
                    $csvUser = null;
                    $usedDefaultUser = false;
                    if (!empty($data->username)) {
                        $csvUser = CachedEntities::getCachedUserByUsername($data->username, true);
                        $csvUser
                            ? $fileUploadUser = $csvUser
                            : $usedDefaultUser = true;
                    }
                    $hasValidCsvUser = !empty($data->username) && !$usedDefaultUser && isset($csvUser);

                    $press = CachedEntities::getCachedPress($data->pressPath);

                    InvalidRowValidations::validateContextIsValid($press, $data->pressPath, 'Press');
                    InvalidRowValidations::validateContextLocale($press, $data->locale, 'Press');

                    $genreName = 'MANUSCRIPT';
                    $genreId = CachedEntities::getCachedGenreId($genreName, $press->getId());

                    InvalidRowValidations::validateGenreIdValid($genreId, $genreName);

                    $userGroupId = CachedEntities::getCachedAuthorUserGroupId($data->pressPath, $press->getId());

                    InvalidRowValidations::validateUserGroupId($userGroupId, $data->pressPath, 'Press');

                    if ($data->funders) {
                        InvalidRowValidations::validateFundingPluginEnabled($data->funders, $press->getId(), 'Press');
                        InvalidRowValidations::validateFundersCrossrefRegistry($data->funders, $press->getId());
                    }

                    $this->initializeStaticVariables();

                    $coverImageUploadName = null;
                    if (!$this->dryMode && $data->coverImageFilename) {
                        InvalidRowValidations::validateCoverImageIsValid($data->coverImageFilename, $this->sourceDir);

                        $sanitizedCoverImageName = str_replace([' ', '_', ':'], '-', mb_strtolower($data->coverImageFilename));
                        $sanitizedCoverImageName = preg_replace('/[^a-z0-9\.\-]+/', '', $sanitizedCoverImageName);
                        $coverImageUploadName = uniqid() . '-' . basename($sanitizedCoverImageName);

                        $destFilePath = $this->publicFileManager->getContextFilesPath($press->getId()) . '/' . $coverImageUploadName;
                        $srcFilePath = "{$this->sourceDir}/{$data->coverImageFilename}";
                        $bookCoverImageSaved = $this->fileManager->copyFile($srcFilePath, $destFilePath);

                        if (!$bookCoverImageSaved) {
                            throw new RowValidationException(__('plugins.importexport.csv.erroWhileSavingBookCoverImage'));
                        }
                    }

                    $existingSubmission = null; /** @var null|Submission */
                    $basePublication = null; /** @var null|Publication */
                    $isMultiLocaleImport = false;

                    if (
                            !empty($data->versionIdentifier)
                            && InvalidRowValidations::versionExistsInAnyLocale($data, $this->processedMonographs)
                        ) {
                        $version = (int)$data->version;
                        $versionData = $this->processedMonographs[$data->versionIdentifier][$version];

                        $firstLocaleData = reset($versionData);
                        $existingSubmission = $firstLocaleData['submission'];
                        $basePublication = $firstLocaleData['publication'];

                        if (!isset($versionData[$data->locale])) {
                            $isMultiLocaleImport = true;
                        }
                    } elseif (!empty($data->versionIdentifier) && isset($this->processedMonographs[$data->versionIdentifier])) {
                        // Handle new version (not multi-locale)
                        $versions = $this->processedMonographs[$data->versionIdentifier];
                        $lastVersion = end($versions);
                        $lastVersionData = reset($lastVersion);
                        $existingSubmission = $lastVersionData['submission'];
                        $basePublication = $lastVersionData['publication'];
                    }

                    if ($isMultiLocaleImport) {
                        $submission = $existingSubmission;
                        $publication = $basePublication;

                        $publication = PublicationProcessor::processMultiLocalePublication($publication, $data, $press);
                    } elseif ($existingSubmission && $basePublication) {
                        // New version import
                        $submission = $existingSubmission;
                        $publication = PublicationProcessor::createPublicationVersion($basePublication, $data, $press);

                        $publication = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->sourceDir);
                    } else {
                        // New submission import
                        $initialPublication = PublicationProcessor::createInitialPublication($data);
                        $submission = SubmissionProcessor::process($data, $initialPublication, $press);
                        $publication = PublicationProcessor::process($submission, $data, $press, $this->sourceDir);
                    }

                    if (!$publication) {
                        throw new RowValidationException(__('plugins.importexport.csv.errorWhileCreatingPublication'));
                    }

                    if (!$this->dryMode && $data->coverImageFilename) {
                        PublicationProcessor::updateCoverImage($publication, $data, $coverImageUploadName);
                    }

                    // OMP-specific: Create publication format, date, and attach submission files
                    // In OMP, files are attached to PublicationFormats (not galleys)
                    // Create for new submissions AND new versions, but not multi-locale imports
                    if (!$isMultiLocaleImport) {
                        $publicationFormatId = PublicationFormatProcessor::createPublicationFormat(
                            $publication->getId(),
                            $data->doi ?? null
                        );

                        if (!empty($data->year)) {
                            PublicationFormatProcessor::createPublicationDate($publicationFormatId, $data->year);
                        }

                        // Attach PDF file to the publication format if filename provided
                        if (!$this->dryMode && !empty($data->filename)) {
                            $filePath = "{$this->sourceDir}/{$data->filename}";
                            $extension = $this->fileManager->parseFileExtension($data->filename);
                            $submissionDir = sprintf($this->format, $press->getId(), $submission->getId());

                            $fileId = $this->fileService->add(
                                $filePath,
                                $submissionDir . '/' . uniqid() . '.' . $extension
                            );

                            PublicationFormatProcessor::createSubmissionFile(
                                $submission->getId(),
                                $publicationFormatId,
                                $fileId,
                                $genreId,
                                $data->locale,
                                $fileUploadUser
                            );
                        }
                    }

                    if ($isMultiLocaleImport) {
                        if ($hasValidCsvUser) {
                            AuthorsProcessor::updateUsernameAuthorLocale($csvUser, $publication, $data->locale);
                        }
                        AuthorsProcessor::processMultiLocale($data, $press->getContactEmail(), $submission->getId(), $publication, $userGroupId);
                        KeywordsProcessor::processMultiLocale($data, $publication);
                        SubjectsProcessor::processMultiLocale($data, $publication);
                        FundersProcessor::processMultiLocale($data, $submission, $press->getId());
                        PublicationProcessor::processSupportingAgenciesMultiLocale($data, $publication);
                    } else {
                        $usernameAuthorAdded = false;
                        if ($hasValidCsvUser && (!empty($data->authors) || is_null($basePublication))) {
                            AuthorsProcessor::addAuthorFromUser($csvUser, $submission, $publication, $press, $userGroupId);
                            $usernameAuthorAdded = true;
                        }

                        AuthorsProcessor::process($data, $press->getContactEmail(), $submission->getId(), $publication, $userGroupId, $basePublication, $usernameAuthorAdded ? $csvUser : null);
                        KeywordsProcessor::process($data, $publication, $basePublication);
                        SubjectsProcessor::process($data, $publication, $basePublication);
                        FundersProcessor::process($data, $submission, $press->getId(), $basePublication);
                        PublicationProcessor::processSupportingAgencies($data, $publication, $basePublication);
                    }

                    if (
                        ((!empty($data->version) && (int) $data->version === 1) || empty($data->version))
                        && $data->coverage
                    ) {
                        PublicationProcessor::updateCoverage($publication, $data->coverage, $data->locale);
                    }

                    SectionsProcessor::process($data, $press->getId(), $publication, $basePublication);

                    if ($data->categories || $basePublication) {
                        if ($isMultiLocaleImport) {
                            CategoriesProcessor::processMultiLocale($data->categories, $data->locale, $press->getId(), $publication->getId());
                        } elseif ($existingSubmission && $basePublication) {
                            CategoriesProcessor::processForVersion($data->categories, $data->locale, $press->getId(), $publication->getId(), $basePublication);
                        } else {
                            CategoriesProcessor::process($data->categories, $data->locale, $press->getId(), $publication->getId());
                        }
                    }

                    // Refresh publication to retrieve all its data correctly
                    $publication = Repo::publication()->get($publication->getId());

                    if (!empty($data->versionIdentifier)) {
                        $this->trackProcessedMonograph($data, $submission, $publication);
                    }
                } catch (RowValidationException $e) {
                    $failedIdentifier = $fields[2] ?? null;
                    if (!empty($failedIdentifier)) {
                        $this->failedIdentifiers[$failedIdentifier] = true;
                    }

                    if (is_null($invalidCsvFile)) {
                        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows(
                            $this->sourceDir,
                            "invalid_{$basename}",
                            RequiredMonographHeaders::$monographHeaders
                        );
                        if (is_null($invalidCsvFile)) {
                            continue 2;
                        }
                    }

                    CSVFileHandler::processFailedRow(
                        $invalidCsvFile,
                        $fields,
                        $this->expectedRowSize,
                        $e->getMessage(),
                        $this->failedRows
                    );
                    if ($this->dryMode) {
                        $fileFailedRows[] = ['row' => $this->processedRows + 1, 'reason' => $e->getMessage()];
                    }

                    continue;
                }
            }

            if ($this->dryMode) {
                $passed = $this->processedRows - $this->failedRows;
                DryModeReporter::printFileHeader($basename);
                if (!empty($fileFailedRows)) {
                    DryModeReporter::printTableHeader();
                    foreach ($fileFailedRows as $failedRow) {
                        DryModeReporter::printFailedRow($failedRow['row'], $failedRow['reason']);
                    }
                }
                DryModeReporter::printFileSummary($passed, $this->failedRows, $this->processedRows);
                $totalFiles++;
                $totalPassed += $passed;
                $totalFailed += $this->failedRows;

                DB::rollBack();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                CachedEntities::reset();
                $this->processedMonographs = [];
                $this->failedIdentifiers = [];
            }

            echo __('plugins.importexpot.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->processedRows,
                'failedRows' => $this->failedRows,
            ]) . "\n";

            $fileResult = [
                'filename' => $basename,
                'rows' => $this->processedRows,
                'successful' => $this->processedRows - $this->failedRows,
                'failed' => $this->failedRows,
                'errors' => $fileFailedRows,
                'invalidFile' => $this->failedRows > 0 ? "invalid_{$basename}" : null,
            ];
            $results['perFile'][] = $fileResult;
            $results['filesProcessed']++;
            $results['totalRows'] += $this->processedRows;
            $results['successfulRows'] += $this->processedRows - $this->failedRows;
            $results['failedRows'] += $this->failedRows;
        }

        if ($this->dryMode) {
            DryModeReporter::printGrandTotal($totalFiles, $totalPassed, $totalFailed);
            $results['exitCode'] = $totalFailed > 0 ? 1 : 0;
            return $results;
        }

        $this->syncCoverImagesForProcessedMonographs();
        $this->setCurrentVersionsForProcessedMonographs();

        $results['exitCode'] = $results['failedRows'] > 0 ? 1 : 0;
        return $results;
    }

    /** Insert static data that will be used for the submission processing */
    private function initializeStaticVariables(): void
    {
        $this->dirNames ??= Application::getFileDirectories();
        $this->format ??= trim($this->dirNames['context'], '/') . '/%d/' . trim($this->dirNames['submission'], '/') . '/%d';
        $this->fileManager ??= new FileManager();
        $this->publicFileManager ??= new PublicFileManager();
        $this->fileService ??= app()->get('file');
    }

    /**
     * Tracks a processed monograph for version management.
     */
    private function trackProcessedMonograph(object $data, Submission $submission, Publication $publication): void
    {
        $identifier = $data->versionIdentifier;
        $version = (int)$data->version;
        $locale = $data->locale;

        if (!isset($this->processedMonographs[$identifier])) {
            $this->processedMonographs[$identifier] = [];
        }

        if (!isset($this->processedMonographs[$identifier][$version])) {
            $this->processedMonographs[$identifier][$version] = [];
        }

        $this->processedMonographs[$identifier][$version][$locale] = [
            'data' => $data,
            'submission' => $submission,
            'publication' => $publication
        ];
    }

    private function syncCoverImagesForProcessedMonographs(): void
    {
        foreach ($this->processedMonographs as $identifier => $versions) {
            foreach ($versions as $versionNumber => $localeData) {
                $firstLocaleData = reset($localeData);
                $publication = $firstLocaleData['publication'];
                $publicationId = $publication->getId();

                $serverId = Repo::submission()->get($publication->getData('submissionId'))->getData('contextId');
                $pressDao = CachedDaos::getPressDao();
                $press = $pressDao->getById($serverId);
                if (!$press) {
                    continue;
                }
                $defaultLocale = $press->getPrimaryLocale();

                $coverImageSettings = DB::table('publication_settings')
                    ->where('publication_id', $publicationId)
                    ->where('setting_name', 'coverImage')
                    ->get();

                if ($coverImageSettings->isEmpty()) {
                    continue;
                }

                $coverImagesByLocale = [];
                foreach ($coverImageSettings as $setting) {
                    if (empty($setting->setting_value)) {
                        continue;
                    }

                    $coverImageData = json_decode($setting->setting_value, true);
                    if (!empty($coverImageData)) {
                        $coverImagesByLocale[$setting->locale] = $coverImageData;
                    }
                }

                if (empty($coverImagesByLocale)) {
                    continue;
                }

                $sourceCoverImage = null;

                if (isset($coverImagesByLocale[$defaultLocale])) {
                    $sourceCoverImage = $coverImagesByLocale[$defaultLocale];
                } else {
                    foreach ($coverImagesByLocale as $locale => $coverImageData) {
                        if (!empty($coverImageData) && isset($coverImageData['uploadName'])) {
                            $sourceCoverImage = $coverImageData;
                            break;
                        }
                    }
                }

                if (!$sourceCoverImage) {
                    continue;
                }

                $allPublicationLocales = DB::table('publication_settings')
                    ->where('publication_id', $publicationId)
                    ->whereNotNull('locale')
                    ->whereNot('locale', '')
                    ->distinct()
                    ->pluck('locale')
                    ->toArray();

                foreach ($allPublicationLocales as $locale) {
                    if (isset($coverImagesByLocale[$locale])) {
                        continue;
                    }

                    $reloadedPublication = Repo::publication()->get($publicationId);
                    if ($reloadedPublication) {
                        PublicationProcessor::updatePublicationAttribute($reloadedPublication, 'coverImage', $sourceCoverImage, $locale);
                    }
                }
            }
        }
    }

    /**
     * Set the highest version as current for each processed monograph identifier.
     */
    private function setCurrentVersionsForProcessedMonographs(): void
    {
        foreach ($this->processedMonographs as $identifier => $versions) {
            if (count($versions) <= 1) {
                continue;
            }

            $highestVersion = 0;
            $currentVersionData = null;

            foreach ($versions as $versionKey => $locales) {
                foreach($locales as $locale => $localeData) {
                    $versionNumber = (int)$localeData['data']->version;
                    if ($versionNumber > $highestVersion) {
                        $highestVersion = $versionNumber;
                        $currentVersionData = $localeData;
                    }
                }
            }

            if ($currentVersionData) {
                $submission = $currentVersionData['submission'];
                $publication = $currentVersionData['publication'];
                SubmissionProcessor::setCurrentPublicationId($submission, $publication->getId());
            }
        }
    }
}
