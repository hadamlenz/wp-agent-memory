# Relationship Modeling Guide

This guide explains how to build and maintain memory relationships in `wp-agent-memory`.

The relationship model in v1 is taxonomy-based clustering, not an explicit edge graph.

- `relation_role` answers: "What kind of relationship role does this entry play?"
- `relation_group` answers: "Which cluster/thread does this entry belong to?"

Both fields are single-value in v1.

## Relationship Model

`relation_role` is a locked taxonomy (`memory_relation_role`) with allowed slugs:

- `canonical`
- `companion`
- `supporting`
- `superseded`
- `historical`
- `duplicate`
- `alternative`

`relation_group` is a taxonomy (`memory_relation_group`) used as a shared cluster slug.
Group terms are created automatically when first assigned.

## Core Pattern: Canonical + Companions

Use this as the default pattern for reusable guidance clusters:

1. Pick one primary entry as `canonical`.
2. Assign all related details/implementations as `companion`.
3. Use the exact same `relation_group` slug across all entries in that cluster.

Recommended group naming:

- `canonical-<topic-slug>`
- lowercase letters and hyphens only
- stable over time
- avoid numeric-only group IDs for new work

Example:

- Canonical: `relation_role=["canonical"]`, `relation_group=["canonical-hover-css-vars-wordpress-blocks"]`
- Companion: `relation_role=["companion"]`, `relation_group=["canonical-hover-css-vars-wordpress-blocks"]`

## When to Use Other Roles

- `supporting`: useful related context that is not a direct companion to the canonical flow.
- `superseded`: replaced by newer guidance in the same cluster.
- `historical`: legacy context retained for background only.
- `duplicate`: overlapping/redundant entry you keep for traceability.
- `alternative`: valid but different approach in the same problem space.

## High-Confidence Relationship Rules

Only assign relationship metadata when contextual evidence is strong.
Good signals:

- Explicit "companion to/superseded by" language in summary/content.
- Shared problem space + same implementation context + same intended primary guidance.
- Existing cluster with clear canonical anchor and matching scope.

Leave entries ungrouped when relationships are weak or ambiguous.

## Authoring Workflow

### 1. Find nearby entries first

Use search before create/update:

```json
{
  "ability_name": "agent-memory/search",
  "parameters": { "query": "hover css vars", "limit": 10 }
}
```

Or filter by existing cluster:

```json
{
  "ability_name": "agent-memory/search",
  "parameters": { "relation_group": ["canonical-hover-css-vars-wordpress-blocks"], "limit": 50 }
}
```

### 2. Create or update with relationship metadata

Canonical entry:

```json
{
  "ability_name": "agent-memory/create-entry",
  "parameters": {
    "title": "Hover Style System",
    "summary": "Primary guidance for hover CSS vars.",
    "topic": ["hover-css-vars-wordpress-blocks"],
    "relation_role": ["canonical"],
    "relation_group": ["canonical-hover-css-vars-wordpress-blocks"]
  }
}
```

Companion entry:

```json
{
  "ability_name": "agent-memory/update-entry",
  "parameters": {
    "id": 162,
    "relation_role": ["companion"],
    "relation_group": ["canonical-hover-css-vars-wordpress-blocks"]
  }
}
```

## Validation Checklist

After updates:

1. Verify each changed entry has one role and one group:

```json
{
  "ability_name": "agent-memory/get-entry",
  "parameters": { "id": 162 }
}
```

2. Verify cluster membership:

```json
{
  "ability_name": "agent-memory/search",
  "parameters": { "relation_group": ["canonical-hover-css-vars-wordpress-blocks"], "limit": 50 }
}
```

3. Verify role slices when needed:

```json
{
  "ability_name": "agent-memory/search",
  "parameters": { "relation_role": ["historical"], "limit": 50 }
}
```

## Common Mistakes

- Assigning companions to different group slugs for the same canonical concept.
- Using role/group for weak thematic similarity with no strong contextual tie.
- Forgetting cardinality: both fields accept arrays but only one slug is allowed.
- Treating relation data as explicit edges; v1 is cluster metadata only.

## Legacy Content Note

A one-time backfill migrates legacy prose of the form:

`Status: Companion to [#<id> ...]`

into relation taxonomies. Keep status links in content if useful for humans, but use `relation_role` + `relation_group` as the source of truth for machine-readable relationships.
