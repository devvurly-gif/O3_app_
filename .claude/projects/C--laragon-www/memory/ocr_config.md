# OCR Menu Item Configuration

## Overview
Le menu item **Import OCR** dans O3_app POS ne s'affiche que si l'option OCR est activée dans la configuration du tenant depuis le système central.

## Configuration

### Backend Configuration
Pour activer l'OCR pour un tenant, il faut ajouter le paramètre suivant dans la table `settings` :

```sql
INSERT INTO settings (domain, key, value) VALUES 
  ('pos', 'ocr_enabled', 'true');
```

### Frontend Implementation
- **File**: `resources/js/pages/pos/PosMain.vue`
- **Computed Property**: `isOcrEnabled`
- **Button**: "Import OCR" button shown conditionally

### How It Works

1. **Settings Store** (`resources/js/stores/setting.ts`):
   - Fetches all tenant configuration from `/settings` endpoint
   - Settings are organized by domain (e.g., `pos`, `invoice`, etc.)

2. **OCR Check** (in PosMain.vue):
   ```typescript
   const isOcrEnabled = computed(() => {
     const val = settingStore.settings?.pos?.ocr_enabled
     return val === 'true'
   })
   ```

3. **Conditional Rendering**:
   ```vue
   <button v-if="isOcrEnabled" @click="openOcrImport">
     Import OCR
   </button>
   ```

## Setting Up OCR

### Step 1: Backend - Add Setting
Add the configuration to your Settings system:
```
Domain: pos
Key: ocr_enabled
Value: 'true'  (or 'false' to disable)
```

### Step 2: Frontend - Implement OCR Handler
The `openOcrImport()` function is currently a placeholder. Implement it to:
1. Open an OCR import modal
2. Accept image upload or document scan
3. Extract text/data using OCR library (e.g., Tesseract.js)
4. Match product codes and add to cart

### Step 3: Testing
- Enable `pos.ocr_enabled = 'true'` in the backend settings
- Reload the POS page
- The "Import OCR" button should now appear below the search bar
- Disable it by setting the value to `'false'`

## Architecture

```
Central Settings System
    ↓
POST /settings (save config)
GET /settings (fetch config)
    ↓
useSettingStore (Pinia)
    ↓
PosMain.vue
    ├── isOcrEnabled computed
    └── openOcrImport() function
```

## Notes
- The button is positioned below the search bar in the products panel
- It uses a blue color scheme to distinguish from other controls
- The icon is a lightning bolt (suggesting quick import)
- Full OCR functionality needs to be implemented in the `openOcrImport()` function

## Related Files
- `/resources/js/stores/setting.ts` - Settings management
- `/resources/js/pages/pos/PosMain.vue` - POS interface with OCR button
- Backend settings endpoint - Must support `pos` domain configuration
