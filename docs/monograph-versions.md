# Monograph Versions

[← Prev: Multi-Locale Support](multi-locale.md) | [README](../README.md) | [Next: Publication Formats →](publication-formats.md)

The CSV import plugin supports creating multiple versions of the same monograph in a single import operation. This feature allows you to track revisions, corrections, and updates to published monographs while maintaining a complete version history.

## How It Works

The multiversion system uses two key fields to manage monograph versions:

- **versionIdentifier**: A unique string that links multiple versions of the same monograph together
- **version**: A positive integer indicating the version number (1, 2, 3, etc.)

When you provide these fields in your CSV:
1. Monographs with the same `versionIdentifier` are treated as different versions of the same submission
2. Each version can have updated content, metadata, or files
3. The system automatically sets the highest version number as the current published version
4. All versions remain accessible in the system's version history

## Version Management Rules

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

## Practical Examples

### Example 1: Single Monograph Without Versions

For monographs that don't need version tracking, simply leave `versionIdentifier` and `version` empty.

```csv
pressPath,locale,...,monographTitle,...
liv,en,...,My Monograph,...
```

### Example 2: Multi Version Monographs

For monographs with multiple versions, set the `versionIdentifier` and `version` fields. The `versionIdentifier` tracks the same monograph and the `version` handles the different versions.

```csv
pressPath,locale,versionIdentifier,version,...,monographTitle,...
liv,en,monograph-001,1,...,Original Edition,...
liv,en,monograph-001,2,...,Revised Edition,...
liv,en,monograph-001,3,...,Final Edition,...
```

### Example 3: Mixed Monographs

You can mix single-version and multi-version monographs in the same CSV file. Take a look at the [Monograph CSV file](../examples/monographs/monographs_example.csv).

## Important Notes

- All versions of a monograph share the same submission ID but have different publication IDs
- Each version can have its own DOI if needed
- Each version gets its own Publication Format with ONIX metadata
- Readers can access previous versions through the monograph's version history
- The import process validates that no duplicate versions exist (same identifier + version number)
- Versions must be imported in sequence within a single CSV file (version 1 before version 2, etc.)

[← Prev: Multi-Locale Support](multi-locale.md) | [README](../README.md) | [Next: Publication Formats →](publication-formats.md)
