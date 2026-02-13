# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.22] - 2026-02-13

### Security
- **CRITICAL**: Enabled SSL certificate verification in HTTP performance tests
- Added SSL_VERIFYHOST validation to prevent man-in-the-middle attacks
- Removed insecure `CURLOPT_SSL_VERIFYPEER = false` settings

### Fixed
- Fixed class name typo: `PerformaceToolkit` → `PerformanceToolkit`
- Fixed inconsistent boolean usage in `microtime()` calls (TRUE → true)
- Added unique temp file names to prevent race conditions
- Added file existence check before unlink operation
- Removed ObjectManager anti-pattern - now using proper dependency injection

### Changed
- Extracted magic numbers to class constants for better maintainability
- Improved variable naming in CPU test (single letters to descriptive names)
- Updated module version to 1.0.22

### Documentation
- Added security best practices section to README
- Added SSL/TLS configuration guidelines
- Added access control recommendations
- Added performance testing considerations
- Created CHANGELOG.md for tracking changes

### Code Quality
- Added performance test constants:
  - `CPU_TEST_ITERATIONS`
  - `MEMORY_TEST_ARRAY_SIZE`
  - `MEMORY_TEST_STRING_LENGTH`
  - `FILE_READ_ITERATIONS`
  - `HTTP_TIMEOUT_SECONDS`
  - `HTTP_CONNECT_TIMEOUT_SECONDS`
  - `REDIS_CONNECTION_TIMEOUT_SECONDS`
  - `OPCACHE_LOW_MEMORY_MB`
  - `OPCACHE_WARNING_MEMORY_MB`
  - `BYTES_TO_MB`
- Improved code consistency and maintainability

## [1.0.21] - Previous Release
- See git history for previous changes
