# Database Support

## MySQL

Full support including:
- Full-text search with `MATCH ... AGAINST`
- Natural language and boolean search modes
- JSON column support for metadata

## PostgreSQL

Full support including:
- Full-text search with `tsvector` and `tsquery`
- Configurable text search language
- JSONB column support for metadata

## SQLite

Supported with limitations:
- Full-text search falls back to `LIKE` queries
- JSON support depends on SQLite version
- Suitable for development and small datasets

## SQL Server

Supported with full-text search:
- Requires full-text catalog to be configured
- Uses `CONTAINS` predicates
