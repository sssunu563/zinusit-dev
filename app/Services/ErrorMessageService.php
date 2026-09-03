<?php

namespace App\Services;

use Exception;
use Illuminate\Database\QueryException;
use Throwable;

class ErrorMessageService
{
    /**
     * Map technical exceptions to user-friendly Indonesian messages
     */
    public static function getUserFriendlyMessage(Throwable $exception, string $context = 'default'): string
    {
        // Try to extract meaningful message first
        $message = self::extractMeaningfulMessage($exception);

        // Map to user-friendly message based on context and error type
        if ($exception instanceof QueryException) {
            return self::handleDatabaseError($exception, $context);
        }

        if (str_contains($message, 'Integrity constraint violation') || str_contains($message, 'UNIQUE')) {
            return self::handleUniqueConstraintError($message, $context);
        }

        if (str_contains($message, 'Foreign key constraint')) {
            return self::handleForeignKeyError($message, $context);
        }

        if (str_contains($message, 'not found') || str_contains($message, 'NotFoundException')) {
            return "Data yang diminta tidak ditemukan.";
        }

        if (str_contains($message, 'Unauthorized') || str_contains($message, '401')) {
            return "Anda tidak memiliki akses untuk melakukan aksi ini.";
        }

        if (str_contains($message, 'Forbidden') || str_contains($message, '403')) {
            return "Aksi ini tidak diizinkan.";
        }

        // Default context-specific messages
        return match ($context) {
            'stb_create' => 'STB tidak dapat dibuat. Silakan periksa data dan coba lagi.',
            'stb_update' => 'Perubahan STB gagal disimpan. Silakan coba lagi.',
            'stb_delete' => 'STB tidak dapat dihapus. Silakan coba lagi.',
            'stb_cancel' => 'STB tidak dapat dibatalkan. Silakan coba lagi.',
            'stb_complete' => 'STB tidak dapat diselesaikan. Silakan pastikan semua tanda tangan sudah lengkap.',
            'peminjaman_create' => 'Dokumen peminjaman tidak dapat dibuat. Silakan periksa data dan coba lagi.',
            'peminjaman_update' => 'Dokumen peminjaman tidak dapat diubah. Silakan coba lagi.',
            'peminjaman_delete' => 'Dokumen peminjaman tidak dapat dihapus. Silakan coba lagi.',
            'peminjaman_complete' => 'Dokumen peminjaman tidak dapat diselesaikan. Silakan coba lagi.',
            'asset_create' => 'Aset tidak dapat ditambahkan. Silakan periksa data dan coba lagi.',
            'asset_update' => 'Data aset tidak dapat diubah. Silakan coba lagi.',
            'asset_delete' => 'Aset tidak dapat dihapus. Silakan coba lagi.',
            'vendor_create' => 'Vendor tidak dapat ditambahkan. Silakan periksa data dan coba lagi.',
            'vendor_update' => 'Data vendor tidak dapat diubah. Silakan coba lagi.',
            'vendor_delete' => 'Vendor tidak dapat dihapus. Silakan coba lagi.',
            'procurement_create' => 'Pengadaan tidak dapat ditambahkan. Silakan periksa data dan coba lagi.',
            'procurement_update' => 'Data pengadaan tidak dapat diubah. Silakan coba lagi.',
            'batch_process' => 'Pemrosesan batch gagal. Beberapa item mungkin sudah dalam status lain. Silakan coba lagi.',
            'snipeit_sync' => 'Sinkronisasi dengan Snipe-IT gagal. Silakan hubungi administrator.',
            'verification' => 'Verifikasi aset gagal disimpan. Silakan coba lagi.',
            default => 'Terjadi kesalahan. Silakan coba lagi atau hubungi administrator.',
        };
    }

    /**
     * Extract meaningful message from exception
     */
    private static function extractMeaningfulMessage(Throwable $exception): string
    {
        // For QueryException, get the error message part
        if ($exception instanceof QueryException) {
            $message = $exception->getMessage();
            // Extract just the SQL error part
            if (preg_match('/SQLSTATE\[\w+\]: (.+)/', $message, $matches)) {
                return $matches[1] ?? $message;
            }
            return $message;
        }

        return $exception->getMessage();
    }

    /**
     * Handle database errors
     */
    private static function handleDatabaseError(QueryException $exception, string $context): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, '1048') || str_contains($message, 'cannot be null')) {
            return "Beberapa data wajib diisi. Silakan periksa dan coba lagi.";
        }

        if (str_contains($message, '1062') || str_contains($message, 'Duplicate entry')) {
            return self::handleUniqueConstraintError($message, $context);
        }

        if (str_contains($message, '1452') || str_contains($message, 'Foreign key constraint')) {
            return self::handleForeignKeyError($message, $context);
        }

        if (str_contains($message, 'Connection refused') || str_contains($message, 'Connection timed out')) {
            return "Koneksi database gagal. Silakan hubungi administrator.";
        }

        return "Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.";
    }

    /**
     * Handle UNIQUE constraint violations
     */
    private static function handleUniqueConstraintError(string $message, string $context): string
    {
        if (str_contains($message, 'email')) {
            return "Email sudah terdaftar. Silakan gunakan email lain.";
        }

        if (str_contains($message, 'username') || str_contains($message, 'name')) {
            return "Nama/kode ini sudah digunakan. Silakan gunakan yang lain.";
        }

        if (str_contains($message, 'request_number') || str_contains($message, 'po_number')) {
            return "Nomor dokumen ini sudah terdaftar. Silakan gunakan nomor yang berbeda.";
        }

        return "Data ini sudah terdaftar dalam sistem. Silakan coba dengan data yang berbeda.";
    }

    /**
     * Handle FOREIGN KEY constraint violations
     */
    private static function handleForeignKeyError(string $message, string $context): string
    {
        return match ($context) {
            'asset_delete' => "Aset tidak dapat dihapus karena sudah digunakan di dokumen lain.",
            'vendor_delete' => "Vendor tidak dapat dihapus karena masih digunakan di pengadaan.",
            'category_delete' => "Kategori tidak dapat dihapus karena masih memiliki data terkait.",
            default => "Data ini tidak dapat diubah karena masih digunakan di tempat lain.",
        };
    }

    /**
     * Check if error is sensitive (should not be logged with full details)
     */
    public static function isSensitiveError(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'password') ||
               str_contains($message, 'token') ||
               str_contains($message, 'API key') ||
               str_contains($message, 'secret');
    }

    /**
     * Log error with appropriate details
     */
    public static function logError(Throwable $exception, string $context = '', array $extraData = []): void
    {
        $logData = [
            'context' => $context,
            'exception_class' => get_class($exception),
            'message' => self::extractMeaningfulMessage($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            ...$extraData,
        ];

        // Don't log sensitive data
        if (!self::isSensitiveError($exception)) {
            $logData['trace'] = substr($exception->getTraceAsString(), 0, 500);
        }

        \Log::error('Application error occurred', $logData);
    }
}
