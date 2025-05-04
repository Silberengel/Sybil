# MIME Types and Nostr Categorizations

Sybil implements NKBIP-06 for MIME types and Nostr-specific categorizations. All events include appropriate `m` and `M` tags:

- `m`: Standard MIME type (e.g., `text/plain`, `application/json`)
- `M`: Nostr-specific categorization (e.g., `note/microblog/nonreplaceable`)

## Supported Event Types

| Kind | Description | MIME Type | Nostr Category |
|------|-------------|-----------|----------------|
| 0 | Profile Metadata | application/json | profile/metadata/replaceable |
| 1 | Text Note | text/plain | note/microblog/nonreplaceable |
| 5 | Event Deletion | application/json | event/delete/nonreplaceable |
| 6 | Boost | application/json | event/boost/nonreplaceable |
| 30-33 | Citations | application/json | citation/*/nonreplaceable |
| 30023 | Longform | text/markdown | article/longform/replaceable |
| 30040 | Publication Index | application/json | meta-data/index/replaceable |
| 30041 | Publication Content | text/asciidoc | article/publication-content/replaceable |
| 30818 | Wiki | text/asciidoc | article/wiki/replaceable |
| 9802 | Highlight | text/plain | highlight/quote/nonreplaceable |

## Git Events

| Kind | Description | MIME Type | Nostr Category |
|------|-------------|-----------|----------------|
| 30617 | Repository | application/json | git/repository/replaceable |
| 30618 | State | application/json | git/state/replaceable |
| 1617 | Patch | text/plain | git/patch/nonreplaceable |
| 1621 | Issue | text/plain | git/issue/nonreplaceable |
| 1630-1633 | Status | text/plain | git/status/nonreplaceable |

## Usage in Commands

Each command automatically adds the appropriate MIME type and Nostr category tags. For example:

```bash
# Create a note (kind 1)
sybil note "Hello, Nostr!"
# MIME type: text/plain
# Nostr category: note/microblog/nonreplaceable

# Create a longform article (kind 30023)
sybil longform article.md
# MIME type: text/markdown
# Nostr category: article/longform/replaceable

# Create a wiki article (kind 30818)
sybil wiki wiki.adoc
# MIME type: text/asciidoc
# Nostr category: article/wiki/replaceable
```

## Implementation Details

The MIME types and Nostr categorizations are managed by the `MimeTypeUtility` class, which provides:

1. Mapping of event kinds to MIME types
2. Mapping of event kinds to Nostr categories
3. Validation of MIME types and categories
4. Helper methods for working with MIME types

For more information about the implementation, see the [MimeTypeUtility class](../src/Utility/MimeType/MimeTypeUtility.php). 


Return to the [Read Me](./../README.md)