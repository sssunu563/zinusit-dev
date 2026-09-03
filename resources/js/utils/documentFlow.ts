const getLocationCode = (location: any): string => {
    const str = String(location || '').trim();
    if (!str || str === '-') return '';
    return str.split(/\s+/)[0].toUpperCase().slice(0, 3);
};

const getYearMonthCode = (value?: any): string => {
    const date = value ? new Date(value) : new Date();
    if (Number.isNaN(date.getTime())) return '';
    const year = date.getFullYear().toString().slice(-2);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${year}${month}`;
};

const getPaddedId = (id?: any): string => {
    const n = parseInt(String(id ?? 0), 10);
    return String(Number.isNaN(n) ? 0 : n).padStart(4, '0');
};

export type DocumentFlowLike = {
    status?: number | string | null;
    document_type?: string | null;
    movement_type?: string | null;
    returned_at?: string | null;
};

const isBlankFlowValue = (value: unknown) =>
    value === null || value === undefined || value === '';

export const formatDocumentFlowDocId = ({
    id,
    locationName,
    date,
    prefix,
}: {
    id?: any;
    locationName?: any;
    date?: any;
    prefix?: any;
}): string => {
    const loc = getLocationCode(locationName);
    const ym = getYearMonthCode(date);
    const idStr = getPaddedId(id);

    if (!ym) return '';

    const segments: string[] = [];

    // 1. Prefix (e.g. STB)
    if (prefix) {
        const cleanPrefix = String(prefix).replace(/-/g, '').trim();
        if (cleanPrefix) segments.push(cleanPrefix);
    }

    // 2. Location (e.g. ZDI)
    const cleanLoc = loc ? String(loc).replace(/-/g, '').trim() : '';
    if (cleanLoc) {
        segments.push(cleanLoc);
    }

    // 3. YearMonth (e.g. 2604)
    if (ym) {
        const cleanYm = String(ym).replace(/-/g, '').trim();
        if (cleanYm) segments.push(cleanYm);
    }

    // 4. ID (e.g. 0001)
    if (idStr) {
        const cleanId = String(idStr).replace(/-/g, '').trim();
        if (cleanId) segments.push(cleanId);
    }

    return segments.join('-');
};

export const resolveDocumentFlowType = (flow: DocumentFlowLike) => {
    if (
        flow.document_type === 'handover' ||
        flow.document_type === 'loan' ||
        flow.document_type === 'service'
    ) {
        return flow.document_type;
    }

    switch (flow.status) {
        case 3:
            return 'loan';
        case 4:
            return 'service';
        default:
            return 'handover';
    }
};

export const resolveDocumentFlowMovement = (flow: DocumentFlowLike): string => {
    if (flow.movement_type === 'out' || flow.movement_type === 'return') {
        return flow.movement_type;
    }

    return flow.status === 2 ? 'return' : 'out';
};

export const resolveDocumentFlowLabel = (flow: DocumentFlowLike) => {
    const documentType = resolveDocumentFlowType(flow);
    const movementType = resolveDocumentFlowMovement(flow);

    if (
        documentType === 'handover' &&
        (movementType === 'out' || movementType === 'handover')
    ) {
        return 'handover';
    }

    if (documentType === 'handover' && movementType === 'return') {
        return 'return';
    }

    if (documentType === 'loan' && movementType === 'out') {
        return flow.returned_at ? 'Peminjaman (Dikembalikan)' : 'Peminjaman';
    }

    if (documentType === 'loan' && movementType === 'return') {
        return 'Pengembalian Pinjaman';
    }

    if (documentType === 'service') {
        return 'Perbaikan';
    }

    return '-';
};

export const resolveDocumentFlowTitle = (
    flow: DocumentFlowLike,
    fallback = 'FORM SERAH TERIMA BARANG',
) => {
    if (
        isBlankFlowValue(flow.document_type) &&
        isBlankFlowValue(flow.movement_type) &&
        isBlankFlowValue(flow.status)
    ) {
        return fallback;
    }

    const documentType = resolveDocumentFlowType(flow);
    const movementType = resolveDocumentFlowMovement(flow);

    if (documentType === 'loan' && movementType === 'return') {
        return 'Pengembalian Pinjaman';
    }

    if (documentType === 'loan' && movementType === 'out') {
        return 'Form Peminjaman';
    }

    if (documentType === 'handover' && movementType === 'return') {
        return 'Pengembalian';
    }

    if (
        documentType === 'handover' &&
        (movementType === 'out' || movementType === 'handover')
    ) {
        return 'FORM SERAH TERIMA BARANG';
    }

    if (documentType === 'service') {
        return 'Serah Terima Perbaikan';
    }

    return fallback;
};

export const isDocumentFlowLoanOut = (flow: DocumentFlowLike) =>
    resolveDocumentFlowType(flow) === 'loan' &&
    resolveDocumentFlowMovement(flow) === 'out';

export const isDocumentFlowLoanReturn = (flow: DocumentFlowLike) =>
    resolveDocumentFlowType(flow) === 'loan' &&
    resolveDocumentFlowMovement(flow) === 'return';

export const isDocumentFlowService = (flow: DocumentFlowLike) =>
    resolveDocumentFlowType(flow) === 'service';

export const isDocumentFlowReturn = (flow: DocumentFlowLike) =>
    resolveDocumentFlowMovement(flow) === 'return';

export const resolveDocumentFlowPhotoLabel = (flow: DocumentFlowLike) => {
    if (isDocumentFlowService(flow)) {
        return 'Condition Photo';
    }

    if (isDocumentFlowReturn(flow)) {
        return 'Return Photo';
    }

    if (isDocumentFlowLoanOut(flow)) {
        return 'Loan Photo';
    }

    return 'Deliver Photo';
};

export const resolveDocumentFlowDateLabel = (flow: DocumentFlowLike) => {
    if (isDocumentFlowService(flow)) {
        return 'Service Date';
    }

    if (isDocumentFlowReturn(flow)) {
        return 'Return Date';
    }

    if (isDocumentFlowLoanOut(flow)) {
        return 'Loan Date';
    }

    return 'Deliver Date';
};

export const resolveDocumentFlowAssetLabel = (flow: DocumentFlowLike) => {
    if (isDocumentFlowService(flow)) {
        return 'Aset Perbaikan';
    }

    if (isDocumentFlowReturn(flow)) {
        return 'Aset Pengembalian';
    }

    if (isDocumentFlowLoanOut(flow)) {
        return 'Aset Peminjaman';
    }

    return 'Aset';
};

export const resolveDocumentFlowPrintToolbarKicker = (
    flow: DocumentFlowLike,
) => {
    if (isDocumentFlowService(flow)) {
        return 'Dokumen Perbaikan';
    }

    if (isDocumentFlowReturn(flow)) {
        return 'Dokumen Pengembalian';
    }

    if (isDocumentFlowLoanOut(flow)) {
        return 'Dokumen Peminjaman';
    }

    return 'Dokumen STB';
};

export const resolveDocumentFlowPrintToolbarCopy = (flow: DocumentFlowLike) => {
    if (isDocumentFlowService(flow)) {
        return 'Siap cetak dokumen serah terima perbaikan.';
    }

    if (isDocumentFlowReturn(flow)) {
        return 'Siap cetak dokumen pengembalian aset.';
    }

    if (isDocumentFlowLoanOut(flow)) {
        return 'Siap cetak dokumen peminjaman aset.';
    }

    return 'Siap cetak dokumen serah terima STB.';
};

export const resolveDocumentFlowRemarkLabel = (flow: DocumentFlowLike) => {
    if (isDocumentFlowService(flow)) {
        return 'Service Detail';
    }

    if (isDocumentFlowReturn(flow)) {
        return 'Return Note';
    }

    if (isDocumentFlowLoanOut(flow)) {
        return 'Loan Note';
    }

    return 'Remark';
};

export const resolveDocumentFlowRequesterRoleLabels = (
    flow: DocumentFlowLike,
) => {
    if (isDocumentFlowService(flow)) {
        return {
            receiver: 'Technician',
            approver: 'IT Manager',
            section: 'Service',
        };
    }

    if (isDocumentFlowReturn(flow)) {
        return {
            receiver: 'Returned by',
            approver: 'Dept Head',
            section: 'Return',
        };
    }

    if (isDocumentFlowLoanOut(flow)) {
        return {
            receiver: 'Borrower',
            approver: '',
            section: 'Borrower',
        };
    }

    return {
        receiver: 'Received',
        approver: 'Dept Head',
        section: 'Requester',
    };
};
