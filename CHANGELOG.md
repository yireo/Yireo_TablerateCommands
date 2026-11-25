# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.3] - 25 November 2026
### Added
- Add a `--website_id` option to filter the output of the table rate list command
- Support PHP 8.5

### Changed
- Drop PHP 8.1 support and require Magento framework 103 and league/csv 9.27+
- Declare all used Magento module and library dependencies in composer.json
- Load table rates in batches when listing them

### Fixed
- Fix SQL injection through the website ID in the import command
- Validate all CSV rows before importing, so a failing row no longer leaves a website without table rates
- Reject unknown countries, regions, too long postcodes and non-numeric values in imports (an unknown region no longer silently becomes the wildcard)
- Make a bare `--overwrite` flag work in the import command
- Restrict the export path: require a .csv file, refuse symbolic links and writes into pub/ and app/etc/, and never overwrite existing files
- Escape spreadsheet formulas in exported CSV files (CSV injection)
- Escape console markup and strip terminal control characters in the list output

## [0.0.2] - 22 October 2025
### Fixed
- Add PHP 8.4
- Fix license
- Remove debugging

## [0.0.1] - 5 March 2025
### Added
- Initial release
