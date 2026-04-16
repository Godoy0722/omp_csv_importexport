# OMP CSV Import Plugin

This plugin allows administrators to import users and monographs with their associated metadata in CSV format into OMP 3.5.X. The plugin supports both command-line interface (CLI) and web interface operations.

## Table of Contents
- [OMP CSV Import Plugin](#omp-csv-import-plugin)
  - [Table of Contents](#table-of-contents)
  - [CLI Usage](#cli-usage)
    - [Importing Users](#importing-users)
    - [Importing Monographs](#importing-monographs)
  - [Web Interface Usage](#web-interface-usage)
  - [CSV General Rules](#csv-general-rules)
    - [Users CSV Format](#users-csv-format)
      - [Users CSV Example](#users-csv-example)
    - [Monographs CSV Format](#monographs-csv-format)
      - [Monographs CSV Example](#monographs-csv-example)
      - [Import File Structure](#import-file-structure)
  - [Multi-Locale Support](#multi-locale-support)
    - [How Multi-Locale Works](#how-multi-locale-works)
    - [Multi-Locale Management Rules](#multi-locale-management-rules)
    - [Multi-Locale Best Practices](#multi-locale-best-practices)
    - [Important Multi-Locale Notes](#important-multi-locale-notes)
    - [ORCiD in Multi-Locale and Multi-Version](#orcid-in-multi-locale-and-multi-version)
  - [Monograph Versions](#monograph-versions)
    - [How It Works](#how-it-works)
    - [Version Management Rules](#version-management-rules)
    - [Practical Examples](#practical-examples)
      - [Example 1: Single Monograph Without Versions](#example-1-single-monograph-without-versions)
      - [Example 2: Multi Version Monographs](#example-2-multi-version-monographs)
      - [Example 3: Mixed Monographs](#example-3-mixed-monographs)
    - [Important Notes](#important-notes)
  - [Publication Formats](#publication-formats)
  - [Dry Mode](#dry-mode)
    - [How Dry Mode Works](#how-dry-mode-works)
    - [Dry Mode Console Report](#dry-mode-console-report)
    - [Dry Mode Exit Codes](#dry-mode-exit-codes)
    - [Important Dry Mode Notes](#important-dry-mode-notes)
  - [Troubleshooting](#troubleshooting)
    - [Common Issues and Solutions](#common-issues-and-solutions)
      - [File and Path Issues](#file-and-path-issues)
      - [CSV Format Issues](#csv-format-issues)
      - [User Import Issues](#user-import-issues)
      - [Monograph Import Issues](#monograph-import-issues)
      - [General Troubleshooting Tips](#general-troubleshooting-tips)


## CLI Usage

### Importing Users

To import users from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin users [username] [pathToFolderWithCsvFiles] [--sendWelcomeEmail]
```

Parameters:
- `username`: The username of a valid Press manager. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the directory containing CSV file(s) with user data. Can be absolute or relative to the OMP root directory.
- `--sendWelcomeEmail`: (Optional) Flag to send welcome emails to imported users. If set, the sender email will be the user retrieved by the username on the CLI command.

Example:
```bash
php tools/importExport.php CSVImportExportPlugin users admin /path/to/folder_with_csv_user_files --sendWelcomeEmail
```

### Importing Monographs

To import monographs from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin monographs [username] [pathToFolderWithCsvFiles]
```

Parameters:
- `username`: The username of a valid Press manager. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the directory containing CSV file(s) with monograph data. Can be absolute or relative to the OMP root directory.

Example:
```bash
php tools/importExport.php CSVImportExportPlugin monographs admin /path/to/folder_with_csv_monograph_files
```

> **Important Notes**
>
>  - The user obtained through the username will be the same one assigned to the submission files. It's also recommended that a dedicated importUser is created for this purpose with the Author role so that it's separate from existing Press Manager and editor user accounts.
>  - The last CLI attribute must be the path to the directory containing CSV files, and not directly the CSV file itself.
>  - The CSV file and any referenced files (PDFs, images) must be readable by the user running the CLI script.
>  - The script must be executed from the OMP installation directory.
>  - Ensure you have proper permissions to execute PHP scripts and access the files.
>

## Web Interface Usage

The plugin also supports importing through the OMP web interface:

1. Navigate to **Tools → Import/Export → CSV Import Export Plugin**
2. Select the import type (**Monographs** or **Users**)
3. Upload your CSV file or a ZIP archive containing CSV files and associated assets
4. Optionally enable **Dry Mode** to validate without persisting changes
5. Optionally enable **Send Welcome Email** (for user imports)
6. Click **Import**

When using a ZIP file for monograph imports, place your CSV files along with any referenced assets (cover images, PDF files) either at the root of the archive or inside a single folder. If the ZIP contains exactly one folder and no CSV files at the root, that folder will be used automatically.

## CSV General Rules

### Users CSV Format

| Column | Required | Description | Example |
|--------|----------|-------------|---------|
| pressPath | Yes | Path of the press | liv |
| firstname | Yes | User's first name | Homer |
| lastname | Yes | User's last name | Simpson |
| email | Yes | User's email address | homer@example.com |
| affiliation | No | User's affiliation | University of British Columbia |
| country | No | Two-letter country code | CA |
| username | No | Username for login (auto-generated if empty) | hsimpson |
| tempPassword | No | Temporary password (auto-generated if empty) | temppassword123 |
| roles | Yes | Semicolon-separated list of roles | Reader;Author |
| reviewInterests | No | Semicolon-separated interests | interest one;interest two |
| orcid | No | User's ORCID identifier | 0000-0002-1825-0097 |

> **User Interests:** User interests in the users CSV use a semicolon-separated format:
>
> ```
> interest one; interest two; another interest
> ```
>
>  - Leading/trailing spaces are automatically trimmed
>  - Empty values are ignored
>  - Each interest will be associated with the created user's profile
>

> **ORCID:** The ORCID field accepts multiple formats and will be automatically normalized to the standard URL format:
>
> Accepted formats:
>  - **Full URL**: `https://orcid.org/0000-0002-1825-0097` or `https://sandbox.orcid.org/0000-0002-1825-0097`
>  - **Dashed format**: `0000-0002-1825-0097`
>  - **Numeric format**: `0000000218250097`
>
> Notes:
>  - The last character can be a digit (0-9) or the letter X (checksum character)
>  - The ORCID checksum is validated during import
>  - Invalid ORCIDs will cause the row to be rejected
>  - Leave empty if the user doesn't have an ORCID
>
> Examples:
>
> ```
> https://orcid.org/0000-0002-1825-0097
> 0000-0001-5109-3700
> 0000000256781235
> ```

#### Users CSV Example

You can take a look at the example we provide on the [User CSV file](./examples/users/users_example.csv).

### Monographs CSV Format

| Column | Required | Description | Example | Notes |
|--------|----------|-------------|---------|-------|
| pressPath | Yes | Path of the target press | liv | Must exist in the system |
| locale | Yes | Monograph locale | en | Must be enabled in the press |
| versionIdentifier | No | Unique identifier for monograph versions | monograph-001 | Links versions together. Leave empty for single-version monographs |
| version | No | Version number | 1 | Required if versionIdentifier is provided. Must be positive integer |
| monographPrefix | No | Monograph prefix | PREF | Optional |
| monographTitle | Yes | Monograph title | My Research Monograph | Required for version 1, optional for versions > 1 |
| monographSubtitle | No | Monograph subtitle | A Study of... | Optional |
| monographAbstract | No | Monograph abstract | This monograph examines... | Optional |
| authors | Yes | Author information | See [Authors Format](#authors-format) | Required for version 1, optional for versions > 1 |
| filename | No | PDF filename to attach | submission.pdf | Must be in same directory as CSV |
| keywords | No | Semicolon-separated keywords | science;research | Optional |
| subjects | No | Semicolon-separated subjects | Biology;Ecology | Optional |
| coverage | No | Coverage information | Global study | Optional |
| categories | No | Semicolon-separated categories | Research Monograph | Will be created if needed |
| doi | No | Digital Object Identifier | 10.1234/abc123 | Must be valid format |
| coverImageFilename | No | Cover image filename | cover.jpg | Must be in same directory |
| coverImageAltText | No | Alt text for cover | Book Cover | Required if cover image used |
| seriesTitle | No | Series name | Library & Information Studies | Will be created if needed |
| seriesPath | No | Series path identifier | lis | Used for lookup and creation |
| year | No | Publication year (YYYY) | 2024 | Used for ONIX publication date |
| isEditedVolume | No | Edited volume flag | 1 | 1 = edited volume, 0 = authored work |
| datePublished | Yes | Publication date | 2024-01-15 | Format: YYYY-MM-DD |
| copyrightYear | No | Copyright year | 2025 | Defaults to system setting if not provided |
| copyrightHolder | No | Copyright holder | Public Knowledge Project | Defaults to system setting if not provided |
| licenseUrl | No | License URL | https://creativecommons.org/licenses/by/4.0 | Defaults to system setting if not provided |
| references | No | Path to references file (.txt) | references.txt | Optional file containing monograph references |
| username | No | Username to assign as file uploader | admin | Falls back to CLI user if not found |
| funders | No | Funder information | See [Funders Format](#funders-format) | Requires Funding plugin |
| supportingAgencies | No | Semicolon-separated agencies | NSF;DOE | Optional |

> **Authors Format**
> The `authors` field in the monographs CSV must contain author information in the following format:
>
> ```
> GivenName,FamilyName,Email,ORCiD,Affiliation;GivenName2,FamilyName2,Email2,ORCiD2,Affiliation2
> ```
>
>  - Fields are separated by commas within each author
>  - Multiple authors are separated by semicolons
>  - All fields except `GivenName` are optional and can be left empty
>  - If `Email` is empty, the primary contact email of the press will be used
>  - `ORCiD` must be the author identifier and is optional; see input options below
>
> Examples:
>
> ```
> "John,Doe,john@example.com,0000-0002-1825-0097,University of Example; Jane,Smith,,https://orcid.org/0000-0002-1694-233X,Another University"
> "Maria,Silva,maria@example.com,0000000218250097,"
> "Carlos,,carlos@example.com,,Example Corp"
> ```

> **ORCiD Input Options**
> You may provide the ORCiD in any of the following forms:
>  - Full URL: `https://orcid.org/0000-0002-1825-0097`
>  - Hyphenated ID: `0000-0002-1825-0097`
>  - Digits only: `0000000218250097`
>
> Notes:
>  - The system normalizes the value to the canonical URL form `https://orcid.org/0000-0000-0000-0000`
>  - The last character may be `X` (checksum), e.g., `0000-0002-1694-233X`
>  - Invalid formats are ignored without blocking the import

> **Funders Format**
> The `funders` field uses the following format:
>
> ```
> FunderName,FunderDOI,GrantNumber1|GrantNumber2;FunderName2,FunderDOI2,GrantNumber3
> ```
>
>  - Multiple funders are separated by semicolons
>  - Within each funder: name, DOI, and grant numbers are comma-separated
>  - Multiple grant numbers are separated by pipes (`|`)
>  - Requires the Funding plugin to be installed and enabled for the press
>
> Example:
>
> ```
> "National Science Foundation,http://dx.doi.org/10.13039/100000001,GRANT-2024-001|GRANT-2024-002;Department of Energy,http://dx.doi.org/10.13039/100000015,DE-SC0021234"
> ```

> **Work Type:** The `isEditedVolume` field determines the monograph's work type:
>  - `1` = **Edited Volume** — a collection of chapters by different authors
>  - `0` or empty = **Authored Work** — a single work by the listed authors

#### Monographs CSV Example

You can take a look at the example we provide on the [Monograph CSV file](./examples/monographs/monographs_example.csv).

#### Import File Structure

When importing monographs, keep all assets in the same directory as the CSV file. Pass the directory path (not the CSV file directly) to the CLI command. Here's an example of the recommended structure:

```
import_directory/
├── monographs.csv
├── submission.pdf
├── another_monograph.pdf
├── cover.jpg
├── references.txt
└── ml_references.txt
```

You can also upload a ZIP archive through the web interface containing the CSV and all referenced files.

## Multi-Locale Support

The CSV import plugin supports importing monographs in multiple languages, allowing presses to publish content for international audiences. This feature enables you to create monographs with content in different locales while maintaining proper relationships between translations.

### How Multi-Locale Works

The multi-locale system uses three key fields to manage monograph translations:

- **versionIdentifier**: Links all versions of a monograph together
- **version**: Indicates the version number
- **locale**: Specifies the language/locale of the content (e.g., `en`, `pt_BR`, `fr_CA`)

When you provide multiple CSV rows with:
- Same `versionIdentifier`
- Same `version`
- Different `locale`

The system will:
1. Detect that you're adding a translation to an existing publication
2. Update the existing publication with the new locale data
3. Preserve all existing data in other locales

### Multi-Locale Management Rules

1. **Locale Codes**:
   - Must match locales enabled in your press submission settings
   - Common examples: `en` (English), `pt_BR` (Brazilian Portuguese), `fr_CA` (Canadian French)
   - Must be validated by the press before import

2. **Required Fields for Multi-Locale**:
   - First locale import (base): Requires ALL mandatory fields (`pressPath`, `locale`, `monographTitle`, `authors`, `datePublished`)
   - Additional locale imports: Only require `versionIdentifier`, `version`, and `locale` (you can include other fields you want to translate)
   - Fields not provided will remain empty for that locale (they won't inherit from other locales), with the exception of the coverImage, which if not passed on a second locale but present on the first one, will inherit it from the first one.

3. **Localized Fields**:
   The following fields support multi-locale data:
   - `monographTitle`
   - `monographSubtitle`
   - `monographAbstract`
   - `monographPrefix`
   - `coverage`
   - `copyrightHolder`
   - `keywords`
   - `subjects`
   - `categories` (category titles)
   - Author names (`givenName`, `familyName`)
   - Author affiliations

4. **Non-Localized Fields**:
   These fields are shared across all locales:
   - `copyrightYear`
   - `licenseUrl`
   - `doi`
   - `datePublished`
   - `year`
   - `isEditedVolume`
   - File attachments (`filename`)

### Multi-Locale Best Practices

1. **Import Order**:
   - Always import the primary/default locale first
   - Then add additional locales in subsequent rows
   - You can import all locales in a single CSV file

2. **Consistency**:
   - Keep `versionIdentifier` and `version` consistent across locales

3. **Validation**:
   - The system validates that `identifier` + `version` + `locale` is unique
   - Duplicate combinations will be rejected with error message
   - Check the `invalid_[filename].csv` file for any failed rows

### Important Multi-Locale Notes

- All locales for a version share the same publication ID
- Readers can switch between available locales in the frontend
- Categories can have different titles per locale
- Author names and affiliations can be provided in multiple locales
- Keywords and subjects are stored per locale
- Non-localized fields (DOI, dates, etc.) remain the same across all locales

For a comprehensive example of multi-locale monographs with versions, see the [Monograph CSV file](./examples/monographs/monographs_example.csv).

### ORCiD in Multi-Locale and Multi-Version

- **Multi-Locale**: ORCiD is non-localized. When importing another locale for the same version, if an ORCiD is provided in that row, it updates the existing author matched by email. If omitted, the existing value is preserved.
- **Multi-Version**: If the `authors` field is empty for a new version, authors (including ORCiD) are cloned from the previous version. If authors are provided, the ORCiD is read per author (as above) and saved for that version.

## Monograph Versions

The CSV import plugin supports creating multiple versions of the same monograph in a single import operation. This feature allows you to track revisions, corrections, and updates to published monographs while maintaining a complete version history.

### How It Works

The multiversion system uses two key fields to manage monograph versions:

- **versionIdentifier**: A unique string that links multiple versions of the same monograph together
- **version**: A positive integer indicating the version number (1, 2, 3, etc.)

When you provide these fields in your CSV:
1. Monographs with the same `versionIdentifier` are treated as different versions of the same submission
2. Each version can have updated content, metadata, or files
3. The system automatically sets the highest version number as the current published version
4. All versions remain accessible in the system's version history

### Version Management Rules

1. **Version Identifiers**:
   - Can be any unique string (e.g., "monograph-001", "ml-book-2024", "climate-study")
   - Leave empty for single-version monographs
   - Must be unique across different monographs (don't reuse identifiers)

2. **Version Numbers**:
   - Must be positive integers (1, 2, 3, ...)
   - Required when `versionIdentifier` is provided
   - Must be unique for each version of the same monograph
   - Version 1 is always the initial/base version

3. **Required Fields**:
   - **Version 1** must include ALL required fields: `monographTitle`, `authors`, `datePublished`, etc.
   - **Versions > 1** can include only the fields you want to update (partial updates)
   - Fields not provided in higher versions inherit values from the previous version

4. **Automatic Current Version**:
   - After import, the system automatically sets the highest version as "current"
   - All other versions remain in the system as historical versions
   - Readers will see the highest version by default

### Practical Examples

#### Example 1: Single Monograph Without Versions

For monographs that don't need version tracking, simply leave `versionIdentifier` and `version` empty.

```csv
pressPath,locale,...,monographTitle,...
liv,en,...,My Monograph,...
```

#### Example 2: Multi Version Monographs

For monographs with multiple versions, set the `versionIdentifier` and `version` fields. The `versionIdentifier` tracks the same monograph and the `version` handles the different versions.

```csv
pressPath,locale,versionIdentifier,version,...,monographTitle,...
liv,en,monograph-001,1,...,Original Edition,...
liv,en,monograph-001,2,...,Revised Edition,...
liv,en,monograph-001,3,...,Final Edition,...
```

#### Example 3: Mixed Monographs

You can mix single-version and multi-version monographs in the same CSV file. Take a look at the [Monograph CSV file](./examples/monographs/monographs_example.csv).

### Important Notes

- All versions of a monograph share the same submission ID but have different publication IDs
- Each version can have its own DOI if needed
- Each version gets its own Publication Format with ONIX metadata
- Readers can access previous versions through the monograph's version history
- The import process validates that no duplicate versions exist (same identifier + version number)
- Versions must be imported in sequence within a single CSV file (version 1 before version 2, etc.)

## Publication Formats

OMP uses **Publication Formats** to represent the different formats in which a monograph is available (e.g., PDF, EPUB, hardcover). The CSV import plugin automatically creates a digital PDF Publication Format for each imported monograph version with the following defaults:

| Property | Value | Description |
|----------|-------|-------------|
| Format Type | Digital | ONIX entry key `DA` (Digital) |
| Physical Format | No | Not a physical format |
| Approved | Yes | Format is approved for display |
| Available | Yes | Format is available for download |
| Product Availability | Available | ONIX code `20` |

When a `filename` is provided in the CSV, the referenced PDF file is uploaded and attached to the Publication Format as a proof file with:

| Property | Value |
|----------|-------|
| File Stage | Proof |
| Sales Type | Open Access |
| Direct Sales Price | 0 (free) |
| Viewable | Yes |

When a `year` is provided, a Publication Date entry is created with ONIX date format code `05` (YYYY) and role `01` (Publication Date).

If a `doi` is provided, it is assigned to the Publication Format.

## Dry Mode

The plugin supports a `--dry-mode` flag that runs the full import validation pipeline without persisting any data to the database. This allows operators to preview and fix issues in their CSV files before committing a real import.

### How Dry Mode Works

When `--dry-mode` is passed, the plugin:

1. **Reads and validates every CSV row** exactly the same way as a real import — structural checks (column count, required fields) and semantic checks (press existence, locale support, ORCID format, funder validation, etc.)
2. **Runs all processors inside a database transaction** — submissions, publications, authors, keywords, and all other entities are actually created in the database during processing, which means OMP-internal constraints (unique keys, foreign keys, etc.) are also validated
3. **Rolls back the transaction** at the end of each file — all database changes are undone, leaving the database in its original state
4. **Skips filesystem operations** — cover image uploads and PDF file uploads are not performed
5. **Produces a console report** — a per-file summary showing which rows passed and which failed, with the specific error reason for each failure
6. **Creates `invalid_*.csv` files** — failed rows are written to invalid files just like in a normal import, so you can use the same re-import workflow

Usage:
```bash
# Dry-mode for monographs
php tools/importExport.php CSVImportExportPlugin --dry-mode monographs admin /path/to/csv_files/

# Dry-mode for users
php tools/importExport.php CSVImportExportPlugin --dry-mode users admin /path/to/csv_files/

# Dry-mode for users with sendWelcomeEmail (emails are NOT sent in dry-mode)
php tools/importExport.php CSVImportExportPlugin --dry-mode users admin /path/to/csv_files/ --sendWelcomeEmail
```

### Dry Mode Console Report

The console output follows this structure for each CSV file:

```
=== monographs.csv ===
ROW  | STATUS | ERROR
   3  | FAILED | Unknown locale or locale not supported by this Press: "pt_BR". Supported locales: en
   5  | FAILED | Verify the required fields for this row.
Result: 8 passed, 2 failed (10 total)

=== users.csv ===
Result: 15 passed, 0 failed (15 total)

--- Dry-mode complete: 2 files, 23 passed, 2 failed ---
```

- **Row numbers** correspond to the actual CSV line numbers (row 1 is the header, data starts at row 2)
- Only **failed rows** are listed — passing rows are counted in the summary but not printed individually
- The **grand total** at the bottom summarizes results across all files in the directory
- If a file has no failures, only the summary line is printed (no table header or failed rows)

### Dry Mode Exit Codes

The process exit code indicates the overall result:

| Exit Code | Meaning |
|-----------|---------|
| `0` | All rows in all files passed validation |
| `1` | One or more rows failed validation |


### Important Dry Mode Notes

1. **No database side effects**: All database changes are rolled back after each file. Your database is left exactly as it was before the dry-mode run.

2. **No filesystem side effects**: Cover images and PDF files are not uploaded. File existence and format are still validated, but no files are copied or moved.

3. **Welcome emails are never sent**: Even if `--sendWelcomeEmail` is passed alongside `--dry-mode`, no emails will be sent.

4. **Read-only lookups work normally**: Entity lookups (presses, series, categories, genres, user groups, users) are performed against the real database so that validation results accurately reflect what would happen in a real import.

5. **Multi-locale and multi-version detection works**: The same routing logic that detects multi-locale and multi-version rows in a real import also works in dry-mode. However, if a base row fails validation, subsequent multi-locale or multi-version rows for the same identifier will also fail with a descriptive message: *"The base row for identifier 'X' failed validation. This row depends on it and cannot be processed."*

6. **Invalid files are created**: Failed rows are written to `invalid_*.csv` files in the same directory, following the same format as a normal import. This means you can fix the errors and re-import using the same workflow.

7. **Recommended workflow**:
   ```bash
   # Step 1: Validate with dry-mode
   php tools/importExport.php CSVImportExportPlugin --dry-mode monographs admin /path/to/csv_files/

   # Step 2: Fix any errors in the CSV files based on the report

   # Step 3: Run dry-mode again to verify fixes
   php tools/importExport.php CSVImportExportPlugin --dry-mode monographs admin /path/to/csv_files/

   # Step 4: When all rows pass, run the real import
   php tools/importExport.php CSVImportExportPlugin monographs admin /path/to/csv_files/
   ```

8. **`invalid_*` files from dry-mode**: Since dry-mode creates `invalid_*.csv` files, these will be automatically skipped on subsequent runs (both dry-mode and real imports). Delete or move them before re-running if you want a clean validation.

## Troubleshooting

### Common Issues and Solutions

#### File and Path Issues
1. **File Not Found**
   - Error: `Could not read file: [file]. Error: [error]`
   - Solution:
     - Verify the file exists and the path is correct
     - Use absolute paths for reliability
     - For relative paths, they are resolved from the OMP root directory
     - Check file permissions (must be readable by the user running the CLI script)
     - Ensure the file is not empty

2. **Invalid Source Directory**
   - Error: `Invalid source dir: [dir]`
   - Solution:
     - Verify the directory exists and is accessible
     - Check for typos in the path
     - Ensure the user running the CLI command has read permissions

#### CSV Format Issues
3. **Missing or Invalid Fields**
   - Error: `Row doesn't contain all fields` or `Verify the required fields for this row`
   - Solution:
     - Check that all required columns are present in the CSV header
     - Ensure all rows have the same number of fields as the header (29 columns for monographs, 11 for users)
     - Verify there are no empty lines in the CSV file
     - Check for proper CSV escaping of fields containing commas or quotes

4. **Invalid Date Formats**
   - Error: `Invalid datetime format`
   - Solution:
     - Ensure `datePublished` is in YYYY-MM-DD format
     - Verify dates are valid (e.g., no February 30)

#### User Import Issues
5. **User Already Exists**
   - Error: `User already exists with email/username [value]`
   - Solution:
     - Ensure usernames and emails are unique across the system
     - Check for case sensitivity in usernames/emails

6. **Role Issues**
   - Error: `Role "[role]" doesn't exist`
   - Solution:
     - Verify role names exactly match those in the system (e.g., `Reader`, `Author`, `Press manager`)

7. **ORCID Issues**
   - Error: `Invalid ORCID checksum for: [orcid]`
   - Solution:
     - The ORCID checksum validation failed, meaning the ORCID is malformed
     - Double-check the ORCID against the official ORCID record
     - Ensure you copied the complete ORCID without typos

#### Monograph Import Issues
8. **Press or Locale Issues**
   - Error: `Unknown Press with path [path]` or `Unknown locale [locale]`
   - Solution:
     - Verify the press path in the CSV matches exactly
     - Check that the specified locale is enabled in the press **submission** settings (not just UI locale)
     - Ensure the press exists and is accessible

9. **Genre Issues**
   - Error: `Genre MANUSCRIPT not found`
   - Solution:
     - Ensure the press has the default MANUSCRIPT genre configured
     - Check Settings → Workflow → Submission → Components

10. **Author and Metadata Issues**
    - Error: `There is no default author group in the press`
    - Solution:
      - Ensure the press has at least one default author user group configured
      - Verify author information follows the required format (comma-separated fields, semicolon between authors)

11. **Version Import Issues**
    - Error: `Version is required when versionIdentifier is provided`
    - Solution:
      - Always provide the `version` field when using `versionIdentifier`
      - Use positive integers (1, 2, 3, ...) for version numbers

    - Error: `Duplicate article version found for identifier [id], version [num]`
    - Solution:
      - Check for duplicate rows with the same versionIdentifier, version, and locale
      - Ensure each version+locale combination is unique
      - Remove duplicate entries from your CSV file

12. **Funder Issues**
    - Error: `The Funding plugin is not installed or not enabled for this Press`
    - Solution:
      - Install and enable the Funding plugin before importing rows with funder data
      - Or remove funder data from your CSV if the plugin is not needed

13. **References File Issues**
    - Error: `Invalid references file: [filename]`
    - Solution:
      - Verify the references file exists in the same directory as the CSV file
      - Check file permissions
      - Ensure the filename is spelled correctly in the CSV
      - References files must have a `.txt` extension

#### General Troubleshooting Tips
- Always back up your database before running imports
- Test with a small CSV file first
- Use `--dry-mode` to validate before committing
- Check the OMP error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
- When importing via web interface, ensure PHP `upload_max_filesize` and `post_max_size` are sufficient for your ZIP files
