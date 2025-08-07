# Changelog for Custom Fees for Magento 2 by Joseph Leedy

All notable changes to this extension will be documented in this file.

The format is based on [Keep a Changelog], and this extension adheres to
[Semantic Versioning].

For more information about this extension, please refer to the [README] 
document.

## [Unreleased]

### Fixed

- Custom fees were not calculated correctly when calculating totals for 
  orders with multiple invoices

## [1.2.0]

### Added

- Configuration for custom fees can be imported from a CSV spreadsheet
- Conditions can be defined in the extension configuration to determine whether 
  or not to apply a custom fee to an order
- Custom Fees can be calculated as a percentage of an order's subtotal

## [1.1.1]

### Fixed

- Softened dependency on Zend Framework 1 Database component to fix 
  incompatibility with Magento 2.4.4 and 2.4.5

## [1.1.0]

### Added

- Custom fees are now rendered in columns in the Sales Order Grid in the Admin
  panel
- A report summarizing the total amount of collected custom order fees can be
  generated from the Admin panel
- Custom fees are now rendered on the _Cart_ page in the Hyvä frontend
- Custom fees are now rendered on the _Checkout_ pages in the Hyvä frontend

### Fixed

- Reorded custom fees totals to be placed _after_ tax totals on customer and 
  guest order, invoice and credit memo pages in Hyvä frontend

## [1.0.2]

### Fixed

- Mark constructor parameters as explicitly nullable in custom order fees model 
  to fix deprecation errors thrown by PHP 8.4

### Changed

- Moved the _Custom Fees_ configuration field to be placed after the _Tax_ 
  field in the _Totals Sort Order_ group

## [1.0.1]

### Fixed

- Narrowed type for `custom_fees` Cart extension attribute to fix Swagger error
- Added and updated method annotations in Custom Order Fees Interface to fix 
  Swagger errors

## [1.0.0]

### Added

- Initial version of extension

[Keep a Changelog]: https://keepachangelog.com/en/1.1.0
[Semantic Versioning]: https://semver.org/spec/v2.0.0.html
[README]: ./README.md
[Unreleased]: https://github.com/JosephLeedy/magento2-module-custom-fees/compare/1.2.0...HEAD
[1.2.0]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.2.0
[1.1.1]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.1.1
[1.1.0]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.1.0
[1.0.2]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.0.2
[1.0.1]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.0.1
[1.0.0]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.0.0
