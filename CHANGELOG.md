# Changelog

All notable changes to `aliziodev/laravel-product-catalog` will be documented in this file.

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
