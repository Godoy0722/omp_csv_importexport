# Publication Formats

[← Prev: Monograph Versions](monograph-versions.md) | [README](../README.md) | [Next: Dry Mode →](dry-mode.md)

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

### HTML Galleys

When an `htmlGalley` value is provided in the CSV, the HTML file and its dependent files are uploaded as dependent files on the same Publication Format. See [HTML Galleys](csv-format.md#html-galleys) in the CSV Format documentation for usage details.

[← Prev: Monograph Versions](monograph-versions.md) | [README](../README.md) | [Next: Dry Mode →](dry-mode.md)
