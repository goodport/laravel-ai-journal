# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-11-26

### Added

- `UploadedFile` metadata normalization with `sha256`, MIME type, size, and original name, without binary content or temporary paths.
- Remote attachment replay modes `never`, `hash`, and `trust`; `hash` requires an application `RemoteAttachmentHasher`.
- Machine-readable error metadata on invocations and steps: `error_code`, `http_status`, `provider_request_id`, `retry_after`, and sanitized `error_details`.
- Fixed error keys in the attempts JSON schema.
- `AiJournal::invocations()` for filtered and paginated journal reads without loading heavy prompt or response fields.

### Changed

- Remote URL attachments are not replay-stable by default.
- Error details are sanitized to remove credentials, cookies, and sensitive keys.
- Replay and other short-circuit middleware must run before rate limiting; documented as a contract.

## [1.1.1] - 2026-11-26

### Fixed

- `ReplayRecordedStep` keeps its previous constructor calls working by resolving `ContextReader` from the container when it is not supplied.

## [1.1.0] - 2026-11-26

### Added

- Per-call replay control through `ai.journal.fresh` and `ai.journal.ttl` context keys.
- `ai.check` and `ai.pass` are included in the default fingerprint context, so separate checks and passes do not reuse each other's responses.

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
