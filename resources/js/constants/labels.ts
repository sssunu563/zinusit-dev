/**
 * Centralized text labels and messages
 * Use these constants instead of hardcoding text in templates
 * Helps maintain consistency and makes translations easier
 */

export const LABELS = {
  // --- Button Labels (English) ---
  BUTTON: {
    ADD_MANUAL: 'Add Manual',
    PICK_FROM_MASTER: 'Pick from Master',
    SAVE: 'Save',
    CANCEL: 'Cancel',
    DELETE: 'Delete',
    EDIT: 'Edit',
    CLOSE: 'Close',
    APPLY_FILTER: 'Apply Filter',
    RESET: 'Reset',
    SIGN: 'Sign',
    CLEAR: 'Clear',
  },

  // --- Button Labels (Indonesian) ---
  BUTTON_ID: {
    BATAL: 'Batal',
    SIMPAN: 'Simpan',
    HAPUS: 'Hapus',
    UBAH: 'Ubah',
    BATALKAN: 'Batalkan',
    PERBARUI: 'Perbarui',
    UNGGAH: 'Unggah',
    TIDAK: 'Tidak',
    KEMBALI: 'Kembali',
  },

  // --- Document Actions (Indonesian) ---
  DOCUMENT_ACTION: {
    BATALKAN_DOKUMEN: 'Batalkan Dokumen',
    HAPUS_TANDA_TANGAN: 'Hapus Tanda Tangan',
    FINALISASI_DOKUMEN: 'Finalisasi Dokumen',
    KUNCI_DOKUMEN_KE_PDF: 'Kunci dokumen ke PDF final?',
    SELESAIKAN: 'Selesaikan',
  },

  // --- Confirmation Labels (Indonesian) ---
  CONFIRM: {
    YA_BATALKAN: 'Ya, Batalkan',
    YA_HAPUS: 'Ya, Hapus',
    YA_HAPUS_TANDA_TANGAN: 'Ya, Hapus Tanda Tangan',
    YA_FINALISASI: 'Ya, Finalisasi',
  },

  // --- Processing States (Indonesian) ---
  PROCESSING: {
    MEMPROSES: 'Memproses...',
    MENGHAPUS: 'Menghapus...',
    MENYIMPAN: 'Menyimpan...',
    TUNGGU_PROSES_FOTO: 'Tunggu proses foto selesai dulu',
  },

  // --- Status States (Indonesian) ---
  STATUS_ID: {
    DIBATALKAN: 'Dibatalkan',
    DIHAPUS: 'Dihapus',
    DIPERBARUI: 'Diperbarui',
    STB_SELESAI: 'STB Selesai',
    DIBUAT: 'Dibuat',
  },

  // --- Descriptions & Help Text (Indonesian) ---
  DESCRIPTION: {
    HAPUS_VENDOR_PERMANEN: 'Data vendor akan dihapus permanen. Hal ini tidak akan menghapus riwayat tiket yang sudah dikaitkan dengan vendor ini.',
    BATALKAN_DOKUMEN_DESKRIPSI: 'Dokumen akan dipindahkan ke daftar dibatalkan dan dikunci dari edit, tanda tangan, serta finalisasi.',
    FINALISASI_DOKUMEN_DESKRIPSI: 'Setelah difinalisasi, dokumen masuk ke daftar final, aksi edit atau hapus ditutup, dan fitur cetak memakai PDF final.',
    HAPUS_TANDA_TANGAN_DESKRIPSI: 'Tanda tangan untuk {name} akan dikosongkan lagi.',
    KOMPRESI_FOTO_GAGAL: 'Kompresi foto gagal. File asli tetap akan digunakan saat disimpan.',
    FOTO_TERSIMPAN: 'Foto yang sudah tersimpan akan tetap digunakan selama Anda belum menggantinya.',
    COBA_SESUAIKAN_FILTER: 'Coba sesuaikan filter atau kata kunci pencarian Anda.',
  },

  // --- Form Labels (Indonesian) ---
  FORM_LABEL: {
    NAMA_VENDOR: 'Nama Vendor / Perusahaan',
    KATEGORI: 'Kategori',
    CONTACT_PERSON: 'Contact Person',
    EMAIL_KANTOR: 'Email Kantor',
    NOMOR_TELEPON: 'Nomor Telepon',
    ALAMAT_KANTOR: 'Alamat Kantor',
    DOC_ID: 'Doc ID',
    LOCATION: 'Location',
    LOAN_DATE: 'Loan Date',
    DOCUMENT_INFO: 'Document Information',
    HAPUS_IDENTITAS: 'Hapus Identitas',
  },

  // --- Disabled/Help Text (Indonesian) ---
  HELP_TEXT: {
    KEMBALIKAN_SEMUA_ITEM: 'Kembalikan semua item terlebih dahulu',
    HAPUS_SEMUA_FILTER: 'Hapus semua filter',
  },

  // --- Empty States (Indonesian) ---
  EMPTY_STATE_ID: {
    VENDOR_TIDAK_DITEMUKAN: 'Vendor Tidak Ditemukan',
    MULAI_KELOLA_VENDOR: 'Mulai kelola database vendor Anda dengan menekan tombol "Tambah Vendor" di atas.',
  },

  // --- Error Messages (Indonesian) ---
  ERROR_ID: {
    GAGAL_CARI_ASET: 'Gagal mencari aset. Pastikan email Anda benar.',
    GAGAL_HUBUNGI_SERVER: 'Gagal menghubungi server.',
  },

  // --- Signature Modal (Indonesian) ---
  SIGNATURE: {
    TANDA_TANGAN_MANUAL: 'Tanda Tangan Manual',
    SIMPAN_TANDA_TANGAN: 'Simpan Tanda Tangan',
  },

  // --- Dialog Titles (Indonesian) ---
  DIALOG_ID: {
    FOTO_TERSIMPAN: 'Foto tersimpan',
    BATALKAN_DOKUMEN_JUDUL: 'Batalkan dokumen {id}?',
  },

  // --- Form Labels ---
  FORM: {
    ASSET_NAME: 'Asset Name',
    TYPE: 'Type',
    MODEL_TYPE: 'Model/Type',
    SERIAL_NUMBER: 'Serial Number',
    QUANTITY: 'Qty',
    CATEGORY: 'Category',
    CONDITION: 'Condition',
    ASSET_SPECIFICATION: 'Asset Specification',
    INVENTORY_MAPPING: 'Inventory Mapping',
    REMARK: 'Remark',
    PHOTO: 'Photo',
  },

  // --- Section Titles ---
  SECTION: {
    ITEMS: 'Items',
    ASSETS: 'Assets',
    IT_SECTION: 'IT',
    REQUESTER_SECTION: 'Requester',
    SIGNATURES: 'Signatures',
    SIGNATURE: 'Signature',
    SIGNATURE_LOG: 'Signature Log',
  },

  // --- Status Labels ---
  STATUS: {
    SCANNING_DIRECTORY: 'Scanning Directory...',
    NO_ASSETS_ASSIGNED: 'No assets assigned',
    ADD_ITEMS_HELP: 'Add items manually or pick from the master directory.',
    ITEM_NOT_FOUND: 'Item tidak ditemukan',
    DOCUMENTS_NOT_FOUND: 'Dokumen tidak ditemukan',
    TRY_ADJUST_FILTER: 'Coba sesuaikan filter atau kata kunci pencarian.',
    BELUM_DITANDATANGANI: 'Belum ditandatangani',
    DOKUMEN_BERHASIL_DIBUAT: 'Dokumen berhasil dibuat.',
    GAGAL_MEMBUAT_DOKUMEN: 'Gagal membuat dokumen',
  },

  // --- Condition Labels ---
  CONDITION: {
    GOOD: 'Good',
    BROKEN: 'Broken',
    MISSING: 'Missing',
  },

  // --- Placeholder Texts ---
  PLACEHOLDER: {
    SEARCH: 'Search...',
    SEARCH_DOCUMENT: 'Cari dokumen, ID, atau penerima...',
    SEARCH_ITEMS: 'Search items...',
  },

  // --- Table Headers ---
  TABLE: {
    NO: 'No',
    DOC_ID: 'Doc ID',
    LOCATION: 'Location',
    DATE: 'Date',
    NAME: 'Name',
    COMPANY: 'Company',
    FLOW: 'Flow',
    STATUS: 'Status',
    ITEMS: 'Items',
    ACTION: 'Action',
    SIGNATURE: 'Signature',
    DEPARTMENT: 'Department',
  },

  // --- Filter Labels ---
  FILTER: {
    FILTER: 'Filter',
    VIEWS: 'Views',
    ACTIVE: 'Active',
    COMPLETED: 'Completed',
    CANCELLED: 'Cancelled',
    FLOW_TYPE: 'Flow',
    COMPANY: 'Company',
    LOCATION: 'Location',
    ALL: 'All',
    LOAN_OUT: 'Loan Out',
    RETURN: 'Return',
  },

  // --- Movement Type Labels ---
  MOVEMENT_TYPE: {
    OUT: 'Loan Out',
    RETURN: 'Return',
    HANDOVER: 'Handover',
  },

  // --- Document Type Labels ---
  DOCUMENT_TYPE: {
    LOAN: 'Loan',
    HANDOVER: 'Handover',
    RETURN: 'Return',
  },

  // --- Empty States ---
  EMPTY_STATE: {
    NO_ITEMS: 'No items',
    NO_DOCUMENTS: 'No documents found',
    NO_RESULTS: 'No results',
  },

  // --- Pagination ---
  PAGINATION: {
    SHOW: 'Show',
    FROM: 'From',
    RECORD: 'Record',
    RECORDS: 'Records',
    PAGE: 'Page',
  },

  // --- Dialog/Modal Titles ---
  DIALOG: {
    CONFIRM: 'Confirm',
    WARNING: 'Warning',
    ERROR: 'Error',
    SUCCESS: 'Success',
    INFO: 'Information',
  },

  // --- Common Messages ---
  MESSAGE: {
    LOADING: 'Loading...',
    SAVING: 'Saving...',
    SAVED: 'Saved successfully',
    DELETED: 'Deleted successfully',
    ERROR: 'An error occurred',
    REQUIRED_FIELD: 'This field is required',
    INVALID_INPUT: 'Invalid input',
  },

  // --- Document Status ---
  DOCUMENT_STATUS: {
    PENDING: 'Pending',
    COMPLETED: 'Completed',
    CANCELLED: 'Cancelled',
    RETURNED: 'Returned',
    DRAFT: 'Draft',
  },

  // --- Asset Status ---
  ASSET_STATUS: {
    AVAILABLE: 'Available',
    BORROWED: 'Borrowed',
    BROKEN: 'Broken',
    MISSING: 'Missing',
    IN_REPAIR: 'In Repair',
  },

  // --- Role Labels ---
  ROLE: {
    DRAFTER: 'Drafter',
    CHECKER: 'Checker',
    APPROVED: 'Approved',
    RECEIVER: 'Receiver',
    REQUESTER: 'Requester',
    DEPT_HEAD: 'Dept Head',
  },
} as const;

// Type for label keys
export type LabelKey = keyof typeof LABELS;
export type LabelValue = string;

/**
 * Helper function to get nested label values
 * Usage: getLabelValue('BUTTON.ADD_MANUAL')
 */
export const getLabelValue = (path: string): LabelValue => {
  const keys = path.split('.');
  let value: any = LABELS;

  for (const key of keys) {
    value = value?.[key];
    if (value === undefined) {
      console.warn(`Label not found: ${path}`);
      return path; // Fallback to the path itself
    }
  }

  return value as LabelValue;
};
