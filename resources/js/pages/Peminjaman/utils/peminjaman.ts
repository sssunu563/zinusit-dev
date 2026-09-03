import {
    isDocumentFlowLoanOut,
    isDocumentFlowReturn,
    resolveDocumentFlowAssetLabel,
    resolveDocumentFlowDateLabel,
    resolveDocumentFlowLabel,
    resolveDocumentFlowPhotoLabel,
    resolveDocumentFlowPrintToolbarCopy,
    resolveDocumentFlowPrintToolbarKicker,
    resolveDocumentFlowRemarkLabel,
    resolveDocumentFlowRequesterRoleLabels,
    resolveDocumentFlowTitle,
} from '@/utils/documentFlow';

/**
 * Format Doc ID untuk peminjaman: [LOC]-YYMM-#### (identik dengan backend)
 * Contoh: ZGI-2604-0001
 * Menggunakan UTC untuk konsistensi dengan backend (PHP Carbon)
 */
export const formatPeminjamanDocId = ({ id, locationName, date }: { id?: any; locationName?: any; date?: any }): string => {
    if (!id) return '';
    const d = date ? new Date(date) : new Date();
    if (Number.isNaN(d.getTime())) return String(id);

    // Use UTC to match PHP backend (Carbon::parse uses UTC)
    // If date string has no timezone (e.g. from datetime-local input), treat as local
    const hasTimezone = typeof date === 'string' && (date.endsWith('Z') || /[+-]\d{2}:\d{2}$/.test(date));
    const yy = hasTimezone ? d.getUTCFullYear().toString().slice(-2) : d.getFullYear().toString().slice(-2);
    const mm = hasTimezone ? String(d.getUTCMonth() + 1).padStart(2, '0') : String(d.getMonth() + 1).padStart(2, '0');
    const ym = `${yy}${mm}`;
    const padded = String(parseInt(String(id), 10)).padStart(4, '0');

    // Extract 3-letter location code (e.g. "ZGI BGR F1" → "ZGI")
    const loc = locationName && String(locationName).trim() && String(locationName).trim() !== '-'
        ? String(locationName).trim().split(/\s+/)[0].toUpperCase().slice(0, 3)
        : '';

    return loc ? `${loc}-${ym}-${padded}` : `${ym}-${padded}`;
};

export {
    isDocumentFlowLoanOut as isPeminjamanLoanOut,
    isDocumentFlowReturn as isPeminjamanReturnDocument,
    resolveDocumentFlowAssetLabel as resolvePeminjamanAssetLabel,
    resolveDocumentFlowDateLabel as resolvePeminjamanDateLabel,
    resolveDocumentFlowLabel as resolvePeminjamanDocumentLabel,
    resolveDocumentFlowPhotoLabel as resolvePeminjamanPhotoLabel,
    resolveDocumentFlowPrintToolbarCopy as resolvePeminjamanPrintToolbarCopy,
    resolveDocumentFlowPrintToolbarKicker as resolvePeminjamanPrintToolbarKicker,
    resolveDocumentFlowRemarkLabel as resolvePeminjamanRemarkLabel,
    resolveDocumentFlowRequesterRoleLabels as resolvePeminjamanRequesterRoleLabels,
    resolveDocumentFlowTitle as resolvePeminjamanFlowTitle,
};
