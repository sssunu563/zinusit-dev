export type AssetItem = {
    id: number | string;
    name?: string;
    serial?: string;
    otherserial?: string;
    state_name?: string | null;
    group_name?: string;
    department_name?: string;
    company_name?: string;
    holder_name?: string;
    type_name?: string;
    stock?: string | number;
    remaining?: number | null;
    used?: string | number;
    state?: number;
    [key: string]: unknown;
};

export type SortKey =
    | 'name'
    | 'otherserial'
    | 'serial'
    | 'type_name'
    | 'group_name'
    | 'department_name'
    | 'company_name'
    | 'holder_name'
    | 'state_name'
    | 'stock'
    | 'used';

export type TableColumn = {
    key: string;
    label: string;
    sortKey?: SortKey;
    headerClass?: string;
    cellClass?: string;
    linkStyle?: 'pill' | 'text' | 'asset-tag';
    value: (asset: AssetItem) => string | number;
};
