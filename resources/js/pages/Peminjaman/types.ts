export type PeminjamanItem = {
    id: number;
    nama: string;
    kategori?: string | null;
    type: string;
    jumlah: number;
    serial_no: string;
    inventory_number?: string | null;
    computer_id?: number | null;
    snipeit_asset_id?: number | null;
    asset_reference_snapshot?: string | null;
};

export type LoanReferenceOption = {
    id: number;
    docId: string;
    label: string;
};

export type PeminjamanDocumentType = 'loan';

export type PeminjamanMovementType = 'out' | 'return';

export type GroupParts = {
    location?: string | null;
    company?: string | null;
    department?: string | null;
};

export type StbItem = PeminjamanItem;
