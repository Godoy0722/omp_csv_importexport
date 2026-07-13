# CSV Format

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)

## Users CSV Format

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

### Users CSV Example

You can take a look at the example we provide on the [User CSV file](../examples/users/users_example.csv).

Make sure to follow this CSV structure with all headers present, including the non-required ones. It is ok for non-required fields to have no values as long as the header is present.

## Monographs CSV Format

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

### Monographs CSV Example

You can take a look at the example we provide on the [Monograph CSV file](../examples/monographs/monographs_example.csv).

Make sure to follow this CSV structure with all headers present, including the non-required ones. It is ok for non-required fields to have no values as long as the header is present.

### Import File Structure

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

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)
