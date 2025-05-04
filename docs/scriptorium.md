# Scriptorium Converter

The Scriptorium converter is a tool for converting various document formats to AsciiDoc (.adoc) format, making them ready for publication as Nostr events.

## Supported Formats

- Plain Text (.txt)
- Rich Text Format (.rtf)
- HTML (.html)
- Markdown (.md)
- PDF (.pdf) - *Not yet implemented*

## Usage

```bash
sybil convert <input_file> [options]

Options:
  --output, -o    Output file path (defaults to input.adoc)
  --title, -t     Document title (defaults to filename)
  --force, -f     Force overwrite of existing output file
```

## Examples

Convert a Markdown file:
```bash
sybil convert document.md
```

Convert with custom output path and title:
```bash
sybil convert document.md -o output.adoc -t "My Document"
```

Force overwrite existing file:
```bash
sybil convert document.md -f
```

## Output Format

The converter ensures that all output files follow this structure:

1. A single level-1 header (using `=`) as the document title
2. At least one level-2 header (using `==`) for sections
3. Preserved image links in AsciiDoc format
4. Proper heading hierarchy for nested sections

Example output:
```asciidoc
= Document Title

== Introduction

This is the introduction section.

== First Section

Content of the first section.

== Second Section

Content of the second section.

image::path/to/image.jpg[Image description]
```

## Integration with Nostr Events

The converted AsciiDoc files are structured to be compatible with Nostr event types:

- The document title becomes a level-1 header (`=`) for the 30040 index event
- Each section becomes a level-2 header (`==`) for 30041 content events
- Image links are preserved for proper rendering in Nostr clients

## Best Practices

1. **File Organization**
   - Keep input files in a dedicated directory
   - Use descriptive filenames
   - Maintain a consistent naming convention

2. **Content Structure**
   - Ensure documents have a clear title
   - Use proper heading hierarchy
   - Include meaningful section titles

3. **Image Handling**
   - Use relative paths for images
   - Include descriptive alt text
   - Verify image links after conversion

4. **Conversion Process**
   - Review converted files before publishing
   - Check heading hierarchy
   - Verify image links and formatting

## Limitations

- PDF conversion is not yet implemented
- Complex formatting may not be perfectly preserved
- Some advanced HTML features may not convert correctly
- RTF conversion is basic and may lose some formatting

Return to the [Read Me](./../README.md)