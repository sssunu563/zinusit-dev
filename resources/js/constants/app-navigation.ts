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
    BookOpen,
    ShoppingCart,
    Wifi,
    Camera,
    Server,
    LifeBuoy,
    Shield,
    FolderArchive,
    Bell,
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
        title: 'Lisensi',
        href: '/asset?type=license',
        icon: Key,
    },
    {
        title: 'Aksesori',
        href: '/asset?type=accessories',
        icon: Plug,
    },
    {
        title: 'Barang Habis Pakai',
        href: '/asset?type=consumable',
        icon: Package,
    },
    {
        title: 'Komponen',
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
        title: 'Inspeksi',
        href: '/inspection',
        icon: Search,
    },
    {
        title: 'Helpdesk',
        href: '/helpdesk',
        icon: Briefcase,
    },
    {
        title: 'Bank Dokumen',
        href: '/bank-documents',
        icon: FolderArchive,
    },
];

export const logMenuItems: NavItem[] = [
    {
        title: 'Log Autentikasi',
        href: '/auth-logs',
        icon: ClipboardList,
    },
    {
        title: 'Log Aktivitas',
        href: '/action-logs',
        icon: History,
    },
    {
        title: 'Log Formulir',
        href: '/form-logs',
        icon: FileText,
    },
    {
        title: 'Log Laporan',
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
        title: 'Aset',
        href: '/asset',
        icon: Boxes,
        children: assetMenuItems,
    },
    {
        title: 'Formulir',
        href: '/stb',
        icon: FileText,
        children: formMenuItems,
    },
    {
        title: 'Pengguna',
        href: '/users',
        icon: Users,
        children: [
            {
                title: 'User Snipe-IT',
                href: '/users',
                icon: Users,
            },
            {
                title: 'User LDAP',
                href: '/users/ldap',
                icon: Shield,
            },
        ],
    },
    {
        title: 'Laporan',
        href: '/reports',
        icon: BarChart,
        children: [
            {
                title: 'Laporan Infrastruktur',
                href: '/infra-report',
                icon: Shield,
            },
            {
                title: 'Operasional Jaringan',
                href: '/network-operation',
                icon: Wifi,
            },
            {
                title: 'Operasional CCTV',
                href: '/cctv-operation',
                icon: Camera,
            },
            {
                title: 'Operasional Server',
                href: '/server-operation',
                icon: Server,
            },
            {
                title: 'Operasional Dukungan',
                href: '/support-operation',
                icon: LifeBuoy,
            },
        ],
    },
    {
        title: 'Alat',
        href: '#',
        icon: QrCode,
        children: [
            {
                title: 'Stock Opname',
                href: '/audit',
                icon: Scan,
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
            {
                title: 'Webhook Notification',
                href: '/notification-settings',
                icon: Bell,
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
