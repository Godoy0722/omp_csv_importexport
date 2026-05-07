# CLI Usage

[← Prev: Web Interface Usage](web-interface.md) | [README](../README.md) | [Next: CSV Format →](csv-format.md)

## Importing Users

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

## Importing Monographs

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

[← Prev: Web Interface Usage](web-interface.md) | [README](../README.md) | [Next: CSV Format →](csv-format.md)
