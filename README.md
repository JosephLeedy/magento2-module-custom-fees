<p align="center">
    <a href="https://packagist.org/packages/joseph-leedy/module-custom-fees">
        <img src="http://poser.pugx.org/joseph-leedy/module-custom-fees/v"
            alt="Latest Stable Version">
    </a>
    <a href="https://packagist.org/packages/joseph-leedy/module-custom-fees">
        <img src="http://poser.pugx.org/joseph-leedy/module-custom-fees/require/php" 
            alt="PHP Version Required">
    </a>
    <a href="https://packagist.org/packages/joseph-leedy/module-custom-fees">
        <img src="https://img.shields.io/badge/magento-2.4.4%2B-F46F25"
            alt="Magento Version Required">
    </a>
    <a href="https://packagist.org/packages/joseph-leedy/module-custom-fees">
        <img src="https://img.shields.io/badge/hyvä-compatible-0A23B9"
            alt="Hyvä Compatibility">
    </a>
    <a href="https://packagist.org/packages/joseph-leedy/module-custom-fees">
        <img src="http://poser.pugx.org/joseph-leedy/module-custom-fees/downloads"
            alt="Total Downloads">
    </a>
    <a href="https://github.com/sponsors/JosephLeedy">
        <img alt="GitHub Sponsors"
            src="https://img.shields.io/github/sponsors/JosephLeedy">
    </a>
</p>

# Custom Fees for Magento 2
_by Joseph Leedy_

Custom Fees allows merchants to configure additional fees to be charged to 
customers when orders are placed.

## Features

- Allows fees to be configured with a label and amount to be added to an order
- Custom fees are displayed for orders, invoices and credit memos in both the 
frontend and backend
- Custom fees can be refunded via Magento's credit memo functionality
- Includes a report detailing all charged custom fees for a given time period
- Fully compatible with the [Hyvä] theme (Hyvä Default, Hyvä CSP, Hyvä Checkout 
and Hyvä Checkout CSP)

## Requirements

- PHP 8.1 or greater
- Magento Open Source 2.4.4 or greater _or_ Adobe Commerce 2.4.4 or greater
- MySQL 8.0.4 or greater, MariaDB 10.6.0 or greater, _or_ a MySQL 8-compatible 
database server (for generating reports)

## Installation

This extension can be installed via [Composer] from [Packagist] by running 
these commands from a terminal on a Web server or in the desired installation 
location:

    cd /path/to/your/store
    composer require joseph-leedy/module-custom-fees
    php bin/magento module:enable JosephLeedy_CustomFees
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy

## Updating

This extension can be updated via [Composer] from [Packagist] by running
these commands from a terminal on a Web server or in the desired installation
location:

    cd /path/to/your/store
    composer update joseph-leedy/module-custom-fees
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy

## Usage

### Configuration

Custom Fees can be added from the Magento Admin panel by going to `Stores > 
Settings > Configuration > Sales > Sales > Custom Order Fees`. The overall 
display order of the Custom Fees block in relation to other totals shown in the 
cart and checkout can be configured at `Stores > Settings > Configuration > 
Sales > Sales > Checkout Totals Sort Order`. All settings for this extension 
can be configured in the Global (Default), Website or Store scope.

### Reporting

To view a report of the collected custom order fees, go to `Reports > Sales > 
Custom Order Fees` in the Magento Admin panel.

**Note:** For performance reasons, the report generation process makes use of
special database functions that are only available in MySQL 8.0.4+ or
MariaDB 10.6.0+. Errors or unexpected behavior may occur when using incompatible
database server software versions.

## Support

If you experience any issues or errors while using this extension, please
[open an issue] in the GitHub [repository]. Be sure to include all relevant
information, including a description of the issue or error, what you were doing
when it occurred, what versions of Magento Open Source or Adobe Commerce and PHP
are installed and any other pertinent details. I will do my best to respond to
your request in a timely manner.

## License

The source code contained in this extension is licensed under the Open Software
License version 3.0 (OSL-3.0) license. A copy of this license can be found in
the [LICENSE] file included with the source code or online at
https://opensource.org/licenses/OSL-3.0.

Copyright for the included source code is exclusively held by Joseph Leedy,
all rights reserved.

## History

A full history of the extension can be found in the [CHANGELOG.md] file.

## Contributing

We welcome and value your contribution. For more details on how you can help us
improve and maintain this tool, please see the [CONTRIBUTING.md] file.

## Shout-Outs

- A huge thanks to [@pykettk], [@Vinai] and [@hostep] for reviewing the Hyvä 
compatibility implementation in [pull request #16] and suggesting improvements!️

[Hyvä]: https://hyva.io
[Composer]: https://getcomposer.org
[Packagist]: https://packagist.org
[open an issue]: https://github.com/JosephLeedy/magento2-module-custom-fees/issues/new
[repository]: https://github.com/JosephLeedy/magento2-module-custom-fees
[LICENSE]: ./LICENSE
[CHANGELOG.md]: ./CHANGELOG.md
[CONTRIBUTING.md]: ./CONTRIBUTING.md
[@pykettk]: https://github.com/pykettk
[@Vinai]: https://github.com/Vinai
[@hostep]: https://github.com/hostep
[pull request #16]: https://github.com/JosephLeedy/magento2-module-custom-fees/pull/16
