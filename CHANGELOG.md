# Changelog

All notable changes to `aliziodev/laravel-product-catalog` will be documented in this file.

## [1.7.1](https://github.com/aliziodev/laravel-product-catalog/compare/v1.7.0...v1.7.1) (2026-10-30)

### Bug Fixes

* **migrations:** include the table prefix in every index name ([b8cd31d](https://github.com/aliziodev/laravel-product-catalog/commit/b8cd31d28b6abeee3b813f827bf2f063cea5c026))

## [1.7.0](https://github.com/aliziodev/laravel-product-catalog/compare/v1.6.2...v1.7.0) (2026-10-30)

### Features

* **products:** schedule publishing with published_at ([31281bc](https://github.com/aliziodev/laravel-product-catalog/commit/31281bc4def0218ded7d1070e80284bb0f921231))
* **variants:** widen price columns to decimal(19,4) ([d0f2c24](https://github.com/aliziodev/laravel-product-catalog/commit/d0f2c24036248717acda6c53761f9ee8cf372ecb))

### Bug Fixes

* **api:** leave inactive variants and meta out of the public API ([acdb6da](https://github.com/aliziodev/laravel-product-catalog/commit/acdb6da16e1ba6fc841fc3b2705d4279518b12e2))
* **events:** dispatch catalog events after the transaction commits ([d212d0c](https://github.com/aliziodev/laravel-product-catalog/commit/d212d0c07b82eb2837f3b630946291724f48c53b))
* **inventory:** reject reserve() for variants with the deny policy ([c80312e](https://github.com/aliziodev/laravel-product-catalog/commit/c80312e0de07536d8def194fe11d3d4d1e254849))
* **inventory:** stop adjust() from deducting reserved stock ([056cb99](https://github.com/aliziodev/laravel-product-catalog/commit/056cb9961d440e09809da062ac23179e9bb7886b))
* **models:** use the configured product model in every relation ([ed00597](https://github.com/aliziodev/laravel-product-catalog/commit/ed00597f0a7b26c9d6fc7ec29c1ba2e0deb892a4))
* **scout:** load missing relations before indexing a product ([267dca1](https://github.com/aliziodev/laravel-product-catalog/commit/267dca11d1df36f5a5e6e34cffe466e310f90997))

## [1.6.2](https://github.com/aliziodev/laravel-product-catalog/compare/v1.6.1...v1.6.2) (2026-10-30)

### Bug Fixes

* **http:** fall back to the default page size for invalid per_page ([508cef8](https://github.com/aliziodev/laravel-product-catalog/commit/508cef8c813df9fa79d44f2db8b4dfa206bf8d4d))
* **search:** ignore malformed query parameters in fromRequest() ([b48f9e5](https://github.com/aliziodev/laravel-product-catalog/commit/b48f9e5ae58764625ea72184eb62e8d7a74a1b28))
* **search:** make keyword search case-insensitive on PostgreSQL ([45e0807](https://github.com/aliziodev/laravel-product-catalog/commit/45e0807a91ea50523c98d95a70484551b925bf1c))

## [1.6.1](https://github.com/aliziodev/laravel-product-catalog/compare/v1.6.0...v1.6.1) (2026-10-30)

### Bug Fixes

* **inventory:** validate reservation quantities and keep stock from going negative ([ebd1c02](https://github.com/aliziodev/laravel-product-catalog/commit/ebd1c026d1cce628fee03f36a6242e16928588e2))
* make the migrations run on MySQL ([#1](https://github.com/aliziodev/laravel-product-catalog/issues/1)) ([8538225](https://github.com/aliziodev/laravel-product-catalog/commit/85382252064aebcd763894f826f4f789fc371638))
* **migrations:** add upgrade migration for schema changes older installs missed ([9007f4f](https://github.com/aliziodev/laravel-product-catalog/commit/9007f4fb5d9b1bbe9d22264b06ccc048cce5d0db))
* **search:** ignore status parameter in ProductSearchBuilder::fromRequest() ([381b5ac](https://github.com/aliziodev/laravel-product-catalog/commit/381b5acda0886c6cc44ca9ae17fd5fd25e32d37f))

## [1.6.0](https://github.com/aliziodev/laravel-product-catalog/compare/v1.5.0...v1.6.0) (2026-04-26)

### Features

* add inventory reservation lifecycle and private product status ([5348874](https://github.com/aliziodev/laravel-product-catalog/commit/5348874acca10966b0a349ec1d694243ea52aa73))

## [1.5.0](https://github.com/aliziodev/laravel-product-catalog/compare/v1.4.1...v1.5.0) (2026-04-22)

### Features

* implement product catalog core with database schema, models, search driver, and performance benchmarks ([7596b9d](https://github.com/aliziodev/laravel-product-catalog/commit/7596b9dedcd34eb880dd1517153a50b1ece7befb))

## [1.4.1](https://github.com/aliziodev/laravel-product-catalog/compare/v1.4.0...v1.4.1) (2026-04-22)

### Bug Fixes

* implement Brand model, migration, resource, and API tests ([5f7f997](https://github.com/aliziodev/laravel-product-catalog/commit/5f7f997dbd0b3f38fb9176ef7e0cbbf0a6a0e9af))

## [1.4.0](https://github.com/aliziodev/laravel-product-catalog/compare/v1.3.1...v1.4.0) (2026-04-22)

### Features

* implement core product catalog infrastructure including service provider, inventory contracts, search drivers, and controllers ([12156da](https://github.com/aliziodev/laravel-product-catalog/commit/12156daa278fff3a8b307efd4bf52e03b11b0b6c))
* implement core product catalog service provider, controllers, and search infrastructure with configuration validation ([bcf22e1](https://github.com/aliziodev/laravel-product-catalog/commit/bcf22e1fe78858611d36c4110f8ae748593aa0c3))

### Bug Fixes

* implement core product catalog architecture including inventory management, search drivers, and model scopes ([6461528](https://github.com/aliziodev/laravel-product-catalog/commit/64615287530d2f248189417ed5206c18db017009))
* implement product catalog service provider, demo seeder, and core infrastructure components ([623e972](https://github.com/aliziodev/laravel-product-catalog/commit/623e9725da7ce1ef685a412e0f2bffd8db2b5608))

## [1.3.0](https://github.com/aliziodev/laravel-product-catalog/compare/v1.2.1...v1.3.0) (2026-04-22)

### Features

* **search:** add configurable scout model and document setup ([3f72fbf](https://github.com/aliziodev/laravel-product-catalog/commit/3f72fbf2f546f99b09a1e27daead15682677bbaa))

## [1.2.1](https://github.com/aliziodev/laravel-product-catalog/compare/v1.2.0...v1.2.1) (2026-04-22)

### Bug Fixes

* normalize searchable price type and correct scout guard assertions ([2c9177f](https://github.com/aliziodev/laravel-product-catalog/commit/2c9177f8492f8d10ee6060851be2fcf85ac15859))

## [1.2.0](https://github.com/aliziodev/laravel-product-catalog/compare/v1.1.0...v1.2.0) (2026-04-22)

### Features

* add search driver system with Scout integration ([0c5c9b3](https://github.com/aliziodev/laravel-product-catalog/commit/0c5c9b31f12b0d9ef66ad5d3d6122a258f624fc6))

## [1.1.0](https://github.com/aliziodev/laravel-product-catalog/compare/v1.0.1...v1.1.0) (2026-04-22)

### Features

* add comprehensive skill guide and API reference for laravel-product-catalog ([a19934e](https://github.com/aliziodev/laravel-product-catalog/commit/a19934ed2de10994dfcdb3d7a28d03b8b66281db))

## 1.0.0 (2026-04-22)

### Features

* initial release of laravel-product-catalog ([87b58c7](https://github.com/aliziodev/laravel-product-catalog/commit/87b58c74ca1a32671e05c364219eedbc288d10e3))

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial package scaffold
- `Product` catalog entity with status lifecycle (draft → published → archived)
- `ProductVariant` as the primary sellable unit
- `ProductOption` and `ProductOptionValue` for variant configuration
- `Category` for basic taxonomy with parent–child nesting
- `InventoryItem` for per-variant stock tracking
- `DatabaseInventoryProvider` — default driver reading from `catalog_inventory_items`
- `NullInventoryProvider` — always-in-stock driver for digital/unlimited products
- `InventoryProviderInterface` contract for custom inventory integrations
- `ProductCatalog` facade with driver resolution and `extend()` hook
- Events: `ProductPublished`, `ProductArchived`, `InventoryAdjusted`
- Publishable config, migrations, and factories
