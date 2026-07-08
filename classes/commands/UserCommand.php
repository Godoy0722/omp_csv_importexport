<?php

/**
 * @file plugins/importexport/csv/classes/commands/UserCommand.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommand
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles the user import when the user uses the users command
 */

namespace APP\plugins\importexport\csv\classes\commands;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;
use APP\plugins\importexport\csv\shared\exceptions\RowValidationException;
use APP\plugins\importexport\csv\shared\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\shared\handlers\DryModeReporter;
use APP\plugins\importexport\csv\shared\handlers\OrcidHandler;
use APP\plugins\importexport\csv\shared\handlers\WelcomeEmailHandler;
use APP\plugins\importexport\csv\shared\processors\UserGroupsProcessor;
use APP\plugins\importexport\csv\shared\processors\UserInterestsProcessor;
use APP\plugins\importexport\csv\shared\processors\UsersProcessor;
use APP\plugins\importexport\csv\shared\validations\InvalidRowValidations;
use Illuminate\Support\Facades\DB;
use PKP\security\Validation;
use PKP\user\User;

class UserCommand
{
    /** Expected row size for a CSV based on the command passed as argument */
    private int $expectedRowSize;

    /** The folder containing all CSV files that the command must go through */
    private string $sourceDir;

    private int $processedRows;

    private int $failedRows;

    private bool $sendWelcomeEmail;

    private User $senderEmailUser;

    private bool $dryMode;

    public function __construct(string $sourceDir, User $user, bool $sendWelcomeEmail, bool $dryMode = false)
    {
        $this->expectedRowSize = count(RequiredUserHeaders::$userHeaders);
        $this->sourceDir = $sourceDir;
        $this->senderEmailUser = $user;
        $this->sendWelcomeEmail = $sendWelcomeEmail;
        $this->dryMode = $dryMode;
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
            'createdRows' => 0,
            'updatedRows' => 0,
            'failedRows' => 0,
            'perFile' => [],
        ];

        foreach (new \DirectoryIterator($this->sourceDir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'csv') {
                continue;
            }

            $basename = $fileInfo->getBasename();
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
            $fileUpdatedRows = 0;
            $fileUpdatedUsers = [];
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

                    $fieldsList = array_pad(array_map('trim', $fields), $this->expectedRowSize, null);
                    $data = (object) array_combine(RequiredUserHeaders::$userHeaders, $fieldsList);

                    InvalidRowValidations::validateRowHasAllRequiredFieldsCommons($data, [RequiredUserHeaders::class, 'validateRowHasAllRequiredFields']);

                    $press = CachedEntities::getCachedPress($data->pressPath);

                    InvalidRowValidations::validateContextIsValid($press, $data->pressPath, 'Press');
                    $existingUser = CachedEntities::getCachedUserByEmail($data->email);
                    $isNewUser = is_null($existingUser);

                    if ($isNewUser) {
                        InvalidRowValidations::validateUserAlreadyExistsWithThisEmail($data->email);

                        if ($data->username) {
                            InvalidRowValidations::validateUserAlreadyExistsWithThisUsername($data->username);
                        }

                        if (empty($data->username)) {
                            $data->username = UsersProcessor::getValidUsername($data->firstname, $data->lastname);
                        }
                    }

                    $roles = array_map('trim', explode(';', $data->roles));

                    InvalidRowValidations::validateAllUserGroupsAreValid($roles, $press->getId(), $press->getPrimaryLocale());

                    if (!empty($data->orcid)) {
                        OrcidHandler::validate($data->orcid);
                    }

                    if ($isNewUser && is_null($data->tempPassword)) {
                        $data->tempPassword = Validation::generatePassword();
                    }

                    $user = UsersProcessor::process($data, $press->getPrimaryLocale());
                    $userId = $user->getId();

                    if (!empty($data->reviewInterests)) {
                        $userInterests = array_map('trim', explode(';', $data->reviewInterests));
                        UserInterestsProcessor::process($userInterests, $userId);
                    }

                    if ($isNewUser) {
                        UserGroupsProcessor::process($roles, $userId, $press->getId(), $press->getPrimaryLocale());
                    } else {
                        $fileUpdatedRows++;
                        $fileUpdatedUsers[] = $data->email;
                    }

                    if ($this->sendWelcomeEmail && !$this->dryMode && $isNewUser) {
                        WelcomeEmailHandler::sendWelcomeEmail($press, $user, $this->senderEmailUser, $data->tempPassword);
                    }
                } catch (RowValidationException $e) {
                    if (is_null($invalidCsvFile)) {
                        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows(
                            $this->sourceDir,
                            "invalid_{$basename}",
                            RequiredUserHeaders::$userHeaders
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
            }

            $createdRows = $this->processedRows - $this->failedRows - $fileUpdatedRows;
            echo __('plugins.importexport.csv.fileProcessFinished', [
                'filename' => $fileInfo->getFilename(),
                'processedRows' => $this->processedRows,
                'createdRows' => $createdRows,
                'updatedRows' => $fileUpdatedRows,
                'failedRows' => $this->failedRows,
            ]) . "\n";

            $fileResult = [
                'filename' => $basename,
                'rows' => $this->processedRows,
                'successful' => $this->processedRows - $this->failedRows,
                'created' => $this->processedRows - $this->failedRows - $fileUpdatedRows,
                'updated' => $fileUpdatedRows,
                'updatedUsers' => $fileUpdatedUsers,
                'failed' => $this->failedRows,
                'errors' => $fileFailedRows,
                'invalidFile' => $this->failedRows > 0 ? "invalid_{$basename}" : null,
            ];
            $results['perFile'][] = $fileResult;
            $results['filesProcessed']++;
            $results['totalRows'] += $this->processedRows;
            $results['successfulRows'] += $this->processedRows - $this->failedRows;
            $results['createdRows'] = ($results['createdRows'] ?? 0) + $this->processedRows - $this->failedRows - $fileUpdatedRows;
            $results['updatedRows'] = ($results['updatedRows'] ?? 0) + $fileUpdatedRows;
            $results['failedRows'] += $this->failedRows;
        }

        if ($this->dryMode) {
            DryModeReporter::printGrandTotal($totalFiles, $totalPassed, $totalFailed);
            $results['exitCode'] = $totalFailed > 0 ? 1 : 0;
            return $results;
        }

        $results['exitCode'] = $results['failedRows'] > 0 ? 1 : 0;
        return $results;
    }
}
