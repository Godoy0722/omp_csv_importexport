# Troubleshooting

[← Prev: Dry Mode](dry-mode.md) | [README](../README.md)

## Common Issues and Solutions

### File and Path Issues
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

### CSV Format Issues
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

### User Import Issues
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

### Monograph Import Issues
8. **Press or Locale Issues**
   - Error: `Unknown Press with path [path]` or `Unknown locale [locale]`
   - Solution:
     - Verify the press path in the CSV matches exactly
     - Check that the specified locale is enabled in the press **submission** settings (not just UI locale)
     - Ensure the press exists and is accessible

9. **Genre Issues**
   - Error: `There is no MANUSCRIPT genre.`
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

    - Error: `A row with identifier "[id]", version "[num]", and locale "[locale]" already exists in this import. Please check the row's version and locale combination.`
    - Solution:
      - Check for duplicate rows with the same versionIdentifier, version, and locale
      - Ensure each version+locale combination is unique
      - Remove duplicate entries from your CSV file

    - Error: `Version must be a positive integer greater than 0.`
    - Solution:
      - Ensure version numbers are positive integers
      - Don't use 0, negative numbers, or decimals

12. **Funder Issues**
    - Error: `The Funding plugin is not installed or not enabled for this Press`
    - Solution:
      - Install and enable the Funding plugin before importing rows with funder data
      - Or remove funder data from your CSV if the plugin is not needed

13. **References File Issues**
    - Error: `Invalid references file: "[filename]". Please verify the file exists and is readable.`
    - Solution:
      - Verify the references file exists in the same directory as the CSV file
      - Check file permissions (must be readable by the user running the import)
      - Ensure the filename is spelled correctly in the CSV

    - Error: `References file must have a .txt extension.`
    - Solution:
      - References files must be plain text with a `.txt` extension

### General Troubleshooting Tips
- Always back up your database before running imports
- Test with a small CSV file first
- Use `--dry-mode` to validate before committing
- Check the OMP error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
- When importing via web interface, ensure PHP `upload_max_filesize` and `post_max_size` are sufficient for your ZIP files

[← Prev: Dry Mode](dry-mode.md) | [README](../README.md)
