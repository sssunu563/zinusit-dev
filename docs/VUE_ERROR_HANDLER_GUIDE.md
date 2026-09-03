# Vue Error Handler - Frontend Error Sanitization Guide

## Overview
Created centralized frontend error handling utility to convert technical error messages to user-friendly Indonesian messages on the client side.

## File Created
**`resources/ts/utils/errorHandler.ts`**

Main utility functions for frontend error handling:
- `getUserFriendlyErrorMessage()` - Maps technical errors to user messages
- `extractValidationErrors()` - Extracts form validation errors
- `formatErrorForDisplay()` - Formats errors for UI display
- `translateValidationMessage()` - Translates Laravel validation messages

## Usage Examples

### Example 1: Handle Axios Errors in API Calls
```typescript
import { getUserFriendlyErrorMessage, extractValidationErrors } from '@/utils/errorHandler';

try {
    await axios.post('/stb/store', formData);
} catch (error) {
    const userMessage = getUserFriendlyErrorMessage(error, 'stb_create');
    alert(userMessage); // Shows: "STB tidak dapat dibuat. Silakan periksa data dan coba lagi."
}
```

### Example 2: Handle Form Validation Errors
```typescript
import { extractValidationErrors, getUserFriendlyErrorMessage } from '@/utils/errorHandler';

try {
    const response = await axios.post('/vendor/store', formData);
} catch (error) {
    const validationErrors = extractValidationErrors(error);
    
    // Apply errors to form fields
    Object.entries(validationErrors).forEach(([field, message]) => {
        formErrors.value[field] = message;
    });
    
    // Show general error message
    const generalMessage = getUserFriendlyErrorMessage(error, 'vendor_create');
    showErrorToast(generalMessage);
}
```

### Example 3: Format Error for Display
```typescript
import { formatErrorForDisplay } from '@/utils/errorHandler';

try {
    await fetchReport();
} catch (error) {
    const { title, message } = formatErrorForDisplay(error, 'report_load');
    
    // Use in alert dialog or toast
    showAlert({
        title,        // "Terjadi Kesalahan"
        message,      // "Data laporan tidak dapat dimuat. Silakan muat ulang halaman."
        type: 'error'
    });
}
```

## Supported Contexts

The utility has built-in messages for these contexts:
- `stb_create` - STB creation errors
- `stb_update` - STB update errors
- `peminjaman_create` - Loan document creation
- `verification` - Asset verification
- `snipeit_sync` - Snipe-IT synchronization
- `report_load` - Report loading
- `asset_create` - Asset creation
- `default` - Generic error (used if context not found)

## Error Type Mapping

For each context, specific error types are mapped:
- `default` - Generic error message
- `database_error` - Database/SQL errors
- `js_error` - JavaScript runtime errors
- `server_error` - HTTP 500 errors

## How It Works

### 1. Error Detection
```
Technical Error → Analyze Type/Status → Map to Context/Type
```

### 2. Sanitization
Automatically filters out:
- SQL error details (SQLSTATE, constraint violations)
- Stack traces (undefined, Cannot read, etc.)
- Sensitive information (passwords, tokens, API keys)
- Overly technical messages

### 3. Mapping
```
Raw Error → Context-Specific Message → User-Friendly Indonesian
```

## Common Error Scenarios Handled

### Duplicate Entry Error
```
Before: "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry"
After:  "Data ini sudah ada dalam sistem. Silakan gunakan yang berbeda."
```

### NULL Constraint Violation
```
Before: "Column 'computer_id' cannot be null (1048)"
After:  "Beberapa data wajib diisi. Silakan periksa dan coba lagi."
```

### Not Found Error
```
Before: "NotFoundException: Record not found"
After:  "Data yang diminta tidak ditemukan."
```

### Validation Error
```
Before: "The email field must be a valid email address"
After:  "Email harus valid."
```

### Server Error
```
Before: "Internal Server Error (500)"
After:  "Server mengalami kesalahan. Silakan hubungi administrator."
```

## Integration with Vue Components

### For API Error Handling
```vue
<script setup lang="ts">
import axios from 'axios';
import { getUserFriendlyErrorMessage, extractValidationErrors } from '@/utils/errorHandler';

const handleSave = async () => {
    try {
        await axios.post('/api/vendor/store', formData.value);
        router.back();
    } catch (error) {
        // Use frontend utility first
        const message = getUserFriendlyErrorMessage(error, 'vendor_create');
        showError(message);
    }
};
</script>
```

### For Form Validation
```vue
<script setup lang="ts">
import { extractValidationErrors } from '@/utils/errorHandler';

const handleSubmit = async () => {
    try {
        const response = await submitForm();
    } catch (error) {
        const validationErrors = extractValidationErrors(error);
        
        if (Object.keys(validationErrors).length > 0) {
            // Show validation errors on fields
            errors.value = validationErrors;
        } else {
            // Show general error
            showError(getUserFriendlyErrorMessage(error));
        }
    }
};
</script>
```

## Reference: Context Types

| Context | Usage |
|---------|-------|
| `stb_create` | STB document creation |
| `stb_update` | STB document updates |
| `stb_delete` | STB document deletion |
| `peminjaman_create` | Loan document creation |
| `verification` | Asset verification process |
| `snipeit_sync` | Snipe-IT synchronization |
| `report_load` | Loading report data |
| `asset_create` | Asset creation |
| `asset_update` | Asset updates |
| `default` | Generic/unknown errors |

## Benefits

✅ **Consistent Error Messages** - Same errors show same messages  
✅ **User-Friendly** - No technical jargon shown to users  
✅ **Internationalized** - All messages in Indonesian  
✅ **Secure** - Hides sensitive technical details  
✅ **Centralized** - Single source of truth for error messages  
✅ **Extensible** - Easy to add new contexts  

## Next Steps

1. Import this utility in Vue components that make API calls
2. Wrap axios calls with try-catch and use `getUserFriendlyErrorMessage()`
3. Test error scenarios to verify messages are displayed correctly
4. Add new contexts as needed for other features

## Troubleshooting

If an error message doesn't display correctly:
1. Check the context parameter matches one of the supported contexts
2. Verify the error is being caught in the try-catch block
3. Use browser DevTools to inspect the actual error object
4. Add new context to `contextSpecificMessage()` if needed
