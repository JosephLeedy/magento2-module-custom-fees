# Changelog for Custom Fees for Magento 2 by Joseph Leedy

All notable changes to this extension will be documented in this file.

The format is based on [Keep a Changelog], and this extension adheres to
[Semantic Versioning].

For more information about this extension, please refer to the [README] 
document.

## [Unreleased]

### Added

- Custom fees can be refunded from credit memos without requiring an adjustment 
  fee (the previous work-around was to create an adjustment fee of the same 
  amount as the custom fee)

### Changed

- Renamed the `custom_fees` column in the `custom_order_fees` table to clarify 
  its purpose (new name: `custom_fees_ordered`)
- The `custom_order_fees` table now includes a column called 
  `custom_fees_refunded` to track the total amount of custom fees refunded for 
  each credit memo related to the order
- The Custom Order Fees Report now includes a column for the total amount of
  custom fees refunded for each order, aggregated from the new 
  `custom_fees_refunded` column in the `custom_order_fees` table

### Deprecated

- The method `retrieve()` in the `CustomFeesRetriever` service has been 
  deprecated in favor of the `retrieveOrderedCustomFees()` method

## [1.2.3]

### Fixed

- An exception was thrown when loading the Sales Order Grid in the Admin panel
  if the JSON data in the `custom_order_fees` table was quoted

## [1.2.2]

### Fixed

- The Custom Order Fees Report did not aggregate custom fees if the custom 
  order fee data was quoted in the database
- The Custom Order Fees Report did not aggregate custom fees if the related
  orders had multiple invoices

### Changed

- Regenerated the database schema allowlist to add missing constraints for the 
  `custom_order_fees` table

## [1.2.1]

### Fixed

- Custom fees were not calculated correctly when calculating totals for 
  orders with multiple invoices
- Custom fees were not calculated correctly when calculating totals for 
  orders with multiple credit memos

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
[Unreleased]: https://github.com/JosephLeedy/magento2-module-custom-fees/compare/1.2.3...HEAD
[1.2.3]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.2.3
[1.2.2]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.2.2
[1.2.1]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.2.1
[1.2.0]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.2.0
[1.1.1]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.1.1
[1.1.0]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.1.0
[1.0.2]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.0.2
[1.0.1]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.0.1
[1.0.0]: https://github.com/JosephLeedy/magento2-module-custom-fees/releases/tag/1.0.0
