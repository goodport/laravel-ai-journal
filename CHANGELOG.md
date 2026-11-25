# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-26

### Added

- Journal of `laravel/ai` agent invocations and generation steps.
- Support for synchronous and streaming calls, tools, multi-step runs, structured output, tool approvals, and provider failover.
- Usage and configurable cost calculation.
- Trace, scope, tags, and Laravel `Context` correlation.
- Stable request fingerprints and opt-in step replay with TTL and scope.
- Redaction contract and attachment metadata normalization.
- Retention command `ai-journal:prune`.
- Read-only facade and public Eloquent models.
