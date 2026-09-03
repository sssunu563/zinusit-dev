/**
 * Frontend Error Handler Utility
 * Converts technical error messages to user-friendly Indonesian messages
 */

export interface ApiErrorResponse {
    message?: string;
    errors?: Record<string, string[]>;
    error?: string;
    data?: any;
}

/**
 * Map technical error messages to user-friendly Indonesian messages
 */
export const getUserFriendlyErrorMessage = (error: unknown, context: string = 'default'): string => {
    // Handle axios errors
    if (error && typeof error === 'object' && 'response' in error) {
        const axiosError = error as any;
        const status = axiosError.response?.status;
        const data = axiosError.response?.data;

        // Handle specific status codes
        if (status === 401) {
            return 'Sesi Anda telah berakhir. Silakan login kembali.';
        }
        if (status === 403) {
            return 'Anda tidak memiliki akses untuk melakukan aksi ini.';
        }
        if (status === 404) {
            return 'Data yang diminta tidak ditemukan.';
        }
        if (status === 409 || status === 422) {
            // Conflict or validation error - might have details
            if (typeof data?.message === 'string') {
                return sanitizeMessage(data.message, context);
            }
            if (typeof data?.errors === 'object') {
                const firstError = Object.values(data.errors)[0];
                if (Array.isArray(firstError) && firstError.length > 0) {
                    return sanitizeMessage(String(firstError[0]), context);
                }
            }
        }
        if (status === 500) {
            return contextSpecificMessage(context, 'server_error');
        }

        // Try to extract message from response
        if (typeof data?.message === 'string') {
            return sanitizeMessage(data.message, context);
        }
    }

    // Handle string errors
    if (typeof error === 'string') {
        return sanitizeMessage(error, context);
    }

    // Handle Error objects
    if (error instanceof Error) {
        return sanitizeMessage(error.message, context);
    }

    // Default message
    return contextSpecificMessage(context, 'default');
};

/**
 * Sanitize technical messages by removing SQL errors, stack traces, etc.
 */
const sanitizeMessage = (message: string, context: string): string => {
    // If message contains SQL error patterns, return context-specific message
    if (message.includes('SQLSTATE') || message.includes('SQL') || message.includes('constraint')) {
        return contextSpecificMessage(context, 'database_error');
    }

    // If message looks like a technical error, return context-specific message
    if (message.includes('undefined') || message.includes('Cannot read') || message.includes('is not a function')) {
        return contextSpecificMessage(context, 'js_error');
    }

    // If message contains sensitive keywords, return generic message
    if (message.includes('password') || message.includes('token') || message.includes('secret')) {
        return 'Terjadi kesalahan. Silakan coba lagi atau hubungi administrator.';
    }

    // Try to detect error type and return appropriate message
    if (message.toLowerCase().includes('duplicate') || message.toLowerCase().includes('already')) {
        return 'Data ini sudah ada dalam sistem. Silakan gunakan yang berbeda.';
    }

    if (message.toLowerCase().includes('not found') || message.toLowerCase().includes('tidak ditemukan')) {
        return 'Data yang diminta tidak ditemukan.';
    }

    if (message.toLowerCase().includes('cannot be null') || message.toLowerCase().includes('required')) {
        return 'Beberapa data wajib diisi. Silakan periksa dan coba lagi.';
    }

    // Return original message if it looks safe
    if (message.length < 100 && !message.includes(':') && !message.includes('{')) {
        return message;
    }

    // Default to context-specific message
    return contextSpecificMessage(context, 'default');
};

/**
 * Get context-specific error message in Indonesian
 */
const contextSpecificMessage = (context: string, errorType: string = 'default'): string => {
    const messages: Record<string, Record<string, string>> = {
        stb_create: {
            default: 'STB tidak dapat dibuat. Silakan periksa data dan coba lagi.',
            database_error: 'Beberapa data wajib diisi. Silakan periksa dan coba lagi.',
            js_error: 'Terjadi kesalahan saat memproses. Silakan coba lagi.',
            server_error: 'Server mengalami kesalahan. Silakan hubungi administrator.',
        },
        stb_update: {
            default: 'Perubahan STB gagal disimpan. Silakan coba lagi.',
            database_error: 'Data tidak dapat diperbarui. Silakan periksa data.',
            js_error: 'Terjadi kesalahan saat memproses. Silakan coba lagi.',
            server_error: 'Server mengalami kesalahan. Silakan hubungi administrator.',
        },
        peminjaman_create: {
            default: 'Dokumen peminjaman tidak dapat dibuat. Silakan periksa data dan coba lagi.',
            database_error: 'Beberapa data wajib diisi. Silakan periksa dan coba lagi.',
            js_error: 'Terjadi kesalahan saat memproses. Silakan coba lagi.',
            server_error: 'Server mengalami kesalahan. Silakan hubungi administrator.',
        },
        verification: {
            default: 'Verifikasi aset gagal disimpan. Silakan coba lagi.',
            database_error: 'Data verifikasi tidak dapat disimpan. Silakan periksa data.',
            js_error: 'Terjadi kesalahan saat memproses. Silakan coba lagi.',
            server_error: 'Server mengalami kesalahan. Silakan hubungi administrator.',
        },
        snipeit_sync: {
            default: 'Sinkronisasi dengan Snipe-IT gagal. Silakan hubungi administrator.',
            database_error: 'Sinkronisasi dengan Snipe-IT gagal. Silakan hubungi administrator.',
            js_error: 'Terjadi kesalahan saat sinkronisasi. Silakan coba lagi.',
            server_error: 'Server Snipe-IT tidak merespons. Silakan hubungi administrator.',
        },
        report_load: {
            default: 'Data laporan tidak dapat dimuat. Silakan muat ulang halaman.',
            database_error: 'Data laporan tidak dapat dimuat. Silakan muat ulang halaman.',
            js_error: 'Terjadi kesalahan saat memuat data. Silakan muat ulang halaman.',
            server_error: 'Server mengalami kesalahan. Silakan hubungi administrator.',
        },
        asset_create: {
            default: 'Aset tidak dapat ditambahkan. Silakan periksa data dan coba lagi.',
            database_error: 'Beberapa data wajib diisi. Silakan periksa dan coba lagi.',
            js_error: 'Terjadi kesalahan saat memproses. Silakan coba lagi.',
            server_error: 'Server mengalami kesalahan. Silakan hubungi administrator.',
        },
    };

    return messages[context]?.[errorType] || messages[context]?.default || 'Terjadi kesalahan. Silakan coba lagi atau hubungi administrator.';
};

/**
 * Extract validation errors from API response
 */
export const extractValidationErrors = (error: unknown): Record<string, string> => {
    const errors: Record<string, string> = {};

    if (error && typeof error === 'object' && 'response' in error) {
        const axiosError = error as any;
        const data = axiosError.response?.data;

        if (typeof data?.errors === 'object' && data.errors !== null) {
            for (const [field, messages] of Object.entries(data.errors)) {
                if (Array.isArray(messages) && messages.length > 0) {
                    // Translate common Laravel validation messages
                    const message = String(messages[0]);
                    errors[field] = translateValidationMessage(message);
                }
            }
        }
    }

    return errors;
};

/**
 * Translate common Laravel validation error messages to Indonesian
 */
const translateValidationMessage = (message: string): string => {
    // Map of English Laravel validation messages to Indonesian
    const translations: Record<string, string> = {
        'The name field is required': 'Nama wajib diisi.',
        'The email field is required': 'Email wajib diisi.',
        'The email must be a valid email address': 'Email harus valid.',
        'has already been taken': 'sudah terdaftar.',
        'must be a number': 'harus berupa angka.',
        'must be at least': 'harus minimal',
        'may not be greater than': 'tidak boleh lebih dari',
        'The field is required': 'wajib diisi.',
        'is invalid': 'tidak valid.',
    };

    // Check for direct translations
    for (const [english, indonesian] of Object.entries(translations)) {
        if (message.includes(english)) {
            return message.replace(english, indonesian);
        }
    }

    // If message is already in Indonesian or we don't have a translation, return as is
    if (message.length < 150 && !message.includes(':')) {
        return message;
    }

    // Generic fallback
    return 'Data tidak valid. Silakan periksa dan coba lagi.';
};

/**
 * Format error for display in alert/toast
 */
export const formatErrorForDisplay = (error: unknown, context: string = 'default'): { title: string; message: string } => {
    const message = getUserFriendlyErrorMessage(error, context);

    return {
        title: 'Terjadi Kesalahan',
        message,
    };
};

export default {
    getUserFriendlyErrorMessage,
    extractValidationErrors,
    formatErrorForDisplay,
};
