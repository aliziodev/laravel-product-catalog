# Changelog

All notable changes to `aliziodev/laravel-product-catalog` will be documented in this file.

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
