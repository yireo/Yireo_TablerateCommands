# Yireo_TablerateCommands

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-tablerate-commands)
<!-- badges.specs.end -->


**A Magento 2 module offering CLI commands to manipulate table rates**

### Installation

```bash
composer require yireo/magento2-tablerate-commands
bin/magento module:enable Yireo_TablerateCommands
```

### Usage
List all existing tablerates (optionally only those of website ID `1`):
```bash
bin/magento yireo_tablerates:list
bin/magento yireo_tablerates:list --website_id=1
```

Export all existing tablerates with website ID `1`:
```bash
bin/magento yireo_tablerates:export 1 test.csv
bin/magento yireo_tablerates:export 1 test.csv --overwrite
```

Import tablerates with website ID `1`:
```bash
bin/magento yireo_tablerates:import 1 test.csv
bin/magento yireo_tablerates:import 1 test.csv --overwrite
```

Notes:
- The CSV file must have the extension `.csv`. The export refuses to write into `pub/` or `app/etc/`.
- With `--overwrite` (or `--overwrite=1`), the import replaces all existing tablerates of the website.
- The import validates all rows first (country, region, postcode and numbers). If any row is invalid, nothing is changed.
- Text columns of the export are escaped against spreadsheet formula injection.

## Current status

<!-- badges.test.start -->
![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_TablerateCommands/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_TablerateCommands/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_TablerateCommands/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_TablerateCommands/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_TablerateCommands/compile.yml?label=compile)
<!-- badges.test.end -->
