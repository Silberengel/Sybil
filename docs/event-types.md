# Event Types Guide

This guide covers the different types of Nostr events that Sybil supports, or that are commonly found on Nostr, and how to work with them. Each event type has specific requirements and use cases.

## Core Event Types

### Kind 1: Text Notes, also called "Microblogs"
Basic text messages, similar to tweets or posts. These are the most common type of event in Nostr.

### Kind 0: Metadata
User profile information and settings. This event type is used to store user profile data and preferences.

### Kind 3: Contacts
List of followed users and relay information. This event type maintains your social graph and relay preferences.

## Articles

### Kind 30023: Long-form Articles
Extended content with title, summary, and content. This event type is used for magazine entries, articles, and other long-form content.

### Kind 30040: Curated Publication Indexes
(Kind 30041 are a form of publication content.)
Long documents, books, magazines, blogs, journals, etc.

## Citations and References

### Kind 30017: Citations
References to external content. This event type is used to cite external sources and maintain bibliographic information.

## Feed Management

### Kind 30001: Lists
Curated lists of events or users. This event type allows you to create and manage custom lists.

## Relay Management

### Kind 10002: Relay List
Relay configuration and preferences. This event type manages your relay connections and preferences.

## Event Properties

All events share these common properties:
```json
{
  "id": "event_id",          // Unique identifier for the event
  "pubkey": "author_public_key", // Public key of the event creator
  "created_at": 1234567890,  // Unix timestamp of event creation
  "kind": 1,                 // Event type identifier
  "tags": [],               // Array of event tags
  "content": "event_content", // Main content of the event
  "sig": "event_signature"   // Cryptographic signature
}
```

### Tags
Common tag types and their purposes:
- `e`: Event references - Links to other events
- `p`: Public key references - Links to other users
- `t`: Hashtags - Content categorization
- `r`: URL references - External links
- `c`: category or type
- `a`: Kind+pubkey+identifier references - Complex event references

Return to the [Read Me](./../README.md)