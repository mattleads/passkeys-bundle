# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

[Unreleased]: https://github.com/mattleads/passkeys-bundle/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/mattleads/passkeys-bundle/releases/tag/v0.1.0

## [0.1.0] - 2026-11-01

### Added
- `LoginFlowController`: passkey-or-password decision endpoint with anti-enumeration default.
- Recovery codes: `RecoveryCodeManager`, `RecoveryCodeStorageInterface`, Doctrine ORM storage with XML mapping, in-memory storage for tests, `RecoveryCodeAuthenticator` (Passport-based, CSRF, remember-me, target path).
- `CredentialUsageSubscriber` + `TracksCredentialUsageInterface` for last-used timestamps.
- `PasskeyManagementController` (list / revoke / regenerate codes) and `RecoveryLoginController` with overridable Twig templates; `CanDeleteCredentialRecord` contract.
- Stimulus controllers `hybrid-login`, `passkey`, `confirm` and a shared `webauthn_service.js` (Symfony UX package layout).
