import {
    Boxes,
    CircuitBoard,
    ClipboardList,
    FileText,
    Folder,
    HardDrive,
    Key,
    LayoutGrid,
    Package,
    Plug,
    Search,
    Users,
    Wrench,
    History,
    BarChart,
    QrCode,
    Briefcase,
    Laptop,
    Scan,
    Truck,
    BookOpen,
    ShoppingCart,
    Wifi,
    Camera,
    Server,
    LifeBuoy,
    Shield,
    FolderArchive,
    Tag,
} from 'lucide-vue-next';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export const assetMenuItems: NavItem[] = [
    {
        title: 'Hardware',
        href: '/asset?type=assets',
        icon: HardDrive,
    },
    {
        title: 'Laptop',
        href: '/asset?type=laptop',
        icon: Laptop,
    },
    {
        title: 'License',
        href: '/asset?type=license',
        icon: Key,
    },
    {
        title: 'Accessories',
        href: '/asset?type=accessories',
        icon: Plug,
    },
    {
        title: 'Consumable',
        href: '/asset?type=consumable',
        icon: Package,
    },
    {
        title: 'Component',
        href: '/asset?type=component',
        icon: CircuitBoard,
    },
];

export const formMenuItems: NavItem[] = [
    {
        title: 'Dokumen STB',
        href: '/stb',
        icon: Folder,
    },
    {
        title: 'Peminjaman',
        href: '/peminjaman',
        icon: ClipboardList,
    },
    {
        title: 'Inspection',
        href: '/inspection',
        icon: Search,
    },
    {
        title: 'Workspace',
        href: '/helpdesk',
        icon: Briefcase,
    },
    {
        title: 'Bank Document',
        href: '/bank-documents',
        icon: FolderArchive,
    },
];

export const logMenuItems: NavItem[] = [
    {
        title: 'Auth Logs',
        href: '/auth-logs',
        icon: ClipboardList,
    },
    {
        title: 'Activity Logs',
        href: '/action-logs',
        icon: History,
    },
    {
        title: 'Form Logs',
        href: '/form-logs',
        icon: FileText,
    },
    {
        title: 'Report Logs',
        href: '/report-logs',
        icon: BarChart,
    },
];

export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Assets',
        href: '/asset',
        icon: Boxes,
        children: assetMenuItems,
    },
    {
        title: 'Form',
        href: '/stb',
        icon: FileText,
        children: formMenuItems,
    },
    {
        title: 'Users',
        href: '/users',
        icon: Users,
    },
    {
        title: 'Report',
        href: '/reports',
        icon: BarChart,
        children: [
            {
                title: 'Infra Report',
                href: '/infra-report',
                icon: Shield,
            },
            {
                title: 'Network Operation',
                href: '/network-operation',
                icon: Wifi,
            },
            {
                title: 'CCTV Operation',
                href: '/cctv-operation',
                icon: Camera,
            },
            {
                title: 'Server Operation',
                href: '/server-operation',
                icon: Server,
            },
            {
                title: 'Support Operation',
                href: '/support-operation',
                icon: LifeBuoy,
            },
        ],
    },
    {
        title: 'Tools',
        href: '#',
        icon: QrCode,
        children: [
            {
                title: 'Label Engine',
                href: '/label-generator',
                icon: Tag,
            },
            {
                title: 'Stock Opname',
                href: '/audit',
                icon: Scan,
            },
            {
                title: 'Master Vendors',
                href: '/vendors',
                icon: Truck,
            },
            {
                title: 'Knowledge Base',
                href: '/kb',
                icon: BookOpen,
            },
            {
                title: 'Rekap Pengadaan',
                href: '/procurement',
                icon: ShoppingCart,
            },
        ],
    },
    {
        title: 'Log',
        href: '/action-logs',
        icon: History,
        children: logMenuItems,
    },
];
