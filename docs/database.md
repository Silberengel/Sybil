# Database Documentation

## Overview

Sybil uses SQLite as its database backend, managed through Doctrine ORM. This provides a lightweight, file-based database solution that's perfect for development and small to medium deployments.

## Configuration

The database configuration is managed through several files:

- `config/packages/doctrine.yaml`: Main Doctrine configuration
- `config/services.php`: Service configuration for database connections
- `src/Kernel.php`: Kernel-level database setup

## Database Location

The SQLite database file is stored at:
```
var/data.db
```

## Schema Management

The database schema is managed through Doctrine ORM. To create or update the schema, use the following command:

```bash
php bin/console doctrine:schema:create
```

## Entity Structure

### NostrEventEntity

The main entity in the system is `NostrEventEntity`, which represents a Nostr event. It has the following structure:

- `id` (string, 64 chars): Unique event identifier
- `pubkey` (string, 64 chars): Public key of the event author
- `created_at` (integer): Unix timestamp of event creation
- `kind` (integer): Event kind
- `content` (text): Event content
- `tags` (json): Event tags
- `sig` (string, 128 chars): Event signature
- `published` (boolean): Whether the event has been published to relays

## Repository Layer

The `NostrEventRepository` class provides methods for:

- Finding events by various criteria (time range, kind, author, tags)
- Saving and removing events
- Syncing events with relays
- Managing event publication status

## Testing

Database tests are located in `tests/Integration/Database/`. These tests ensure:

- Database connection works correctly
- Schema creation and updates function properly
- Entity operations (CRUD) work as expected
- Repository methods function correctly

## Best Practices

1. Always use the repository layer for database operations
2. Validate entities before saving
3. Use transactions for multiple operations
4. Handle exceptions appropriately
5. Log database operations for debugging

## Common Operations

### Finding Events

```php
// Find by time range
$events = $repository->findByTimeRange($since, $until);

// Find by kind
$events = $repository->findByKind($kind);

// Find by author
$events = $repository->findByAuthor($pubkey);

// Find by filter
$events = $repository->findByFilter($filter);
```

### Saving Events

```php
$event = new NostrEventEntity();
// Set event properties...
$repository->save($event);
```

### Removing Events

```php
$repository->remove($event);
```

### Syncing with Relays

```php
$repository->syncFromRelays($relays, $filter);
```

## Troubleshooting

Common issues and solutions:

1. **Database file not found**
   - Ensure the `var` directory exists and is writable
   - Run `doctrine:schema:create` to create the database

2. **Schema errors**
   - Clear the database file and recreate the schema
   - Check entity annotations for errors

3. **Connection errors**
   - Verify database file permissions
   - Check Doctrine configuration

4. **Performance issues**
   - Consider adding indexes for frequently queried fields
   - Use appropriate query methods (e.g., `findBy` vs custom queries)

## Security Considerations

1. **File Permissions**
   - Ensure the database file is not world-readable
   - Set appropriate permissions (e.g., 600 for the database file)

2. **Input Validation**
   - Always validate data before saving to the database
   - Use prepared statements for all queries

3. **Error Handling**
   - Never expose database errors to end users
   - Log errors appropriately for debugging

## Backup and Recovery

1. **Regular Backups**
   - Back up the database file regularly
   - Consider using SQLite's backup API for large databases

2. **Recovery Procedures**
   - Keep a copy of the schema for recovery
   - Document the recovery process

## Performance Optimization

1. **Indexing**
   - Add indexes for frequently queried fields
   - Monitor query performance

2. **Query Optimization**
   - Use appropriate query methods
   - Limit result sets when possible

3. **Connection Management**
   - Close connections when not in use
   - Use connection pooling for high-load scenarios 