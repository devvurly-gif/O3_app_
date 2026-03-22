# Plan: Bulk Import for Categories & Brands

## Overview
Add Excel/CSV bulk import functionality for Categories and Brands, following the exact same pattern already used for Products and ThirdPartners imports.

## Backend Changes

### 1. Create `app/Imports/CategoriesImport.php`
- Same pattern as `ProductsImport.php` / `ThirdPartnersImport.php`
- Implements: `ToModel`, `WithHeadingRow`, `WithValidation`, `SkipsEmptyRows`
- Excel columns: `nom` (required), `code` (optional)
- Upsert logic: if `ctg_code` matches existing → update, else create new
- Auto-set `ctg_status = true` for new entries

### 2. Create `app/Imports/BrandsImport.php`
- Same pattern
- Excel columns: `nom` (required), `code` (optional)
- Upsert logic: if `br_code` matches existing → update, else create new
- Auto-set `br_status = true` for new entries

### 3. Update `ImportController.php`
- Add `categories()` method — same structure as `products()`/`thirdPartners()`
- Add `brands()` method — same structure

### 4. Update `routes/api.php`
- Add `POST import/categories` and `POST import/brands` under the `role:admin,manager` group (next to existing import routes)

## Frontend Changes

### 5. Update `Categories.vue`
- Add "Import" button next to "Ajouter" button in header
- Add import modal with:
  - File info text (columns: `nom`, `code`)
  - File input (xlsx, xls, csv)
  - Progress indicator
  - Success/error display
- Use existing `useExcelImport` composable with endpoint `/api/import/categories`
- Refresh store after successful import

### 6. Update `Brands.vue`
- Same import button and modal as Categories
- Use endpoint `/api/import/brands`

### 7. Update i18n files (`fr.js`, `en.js`)
- Add import-related translations for categories and brands sections

## Files to Create
- `app/Imports/CategoriesImport.php`
- `app/Imports/BrandsImport.php`

## Files to Modify
- `app/Http/Controllers/Api/ImportController.php`
- `routes/api.php`
- `resources/js/pages/Categories.vue`
- `resources/js/pages/Brands.vue`
- `resources/js/i18n/fr.js`
- `resources/js/i18n/en.js`
