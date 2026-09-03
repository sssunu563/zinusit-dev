<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kerja – {{ $printedBy }}</title>
    @vite(['resources/css/app.css'])
    <style>
        *, *::before, *::after { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        @page { 
            size: A4 portrait; 
            margin: 0 !important; /* Force override of global app.css !important margins */
        }

        body {
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
        }

        .print-stage {
            padding: 10px 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* The Paper Container */
        .batch-print-canvas {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            padding: 5mm 8mm;
            margin: 0 auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }

        @media print {
            .no-print { display: none !important; }
            body { 
                background: white !important; 
                margin: 0 !important; 
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .print-stage { padding: 0 !important; margin: 0 !important; width: 100% !important; }
            .batch-print-canvas {
                width: 100% !important;
                margin: 0 !important;
                padding: 4mm 6mm !important;
                box-shadow: none !important;
                min-height: auto !important;
                border: none !important;
            }
        }

        /* Table styles to ensure they fit A4 */
        .batch-table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            margin-top: 15px !important;
            border: 1px solid #cbd5e1 !important;
        }
        .batch-table th {
            background: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            padding: 8px 6px !important;
            text-align: left !important;
            font-weight: 700 !important;
            font-size: 9px !important;
            color: #475569 !important;
            text-transform: uppercase !important;
        }
        .batch-table td {
            border: 1px solid #cbd5e1 !important;
            padding: 8px 6px !important;
            vertical-align: top !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            font-size: 9px !important;
            line-height: 1.4 !important;
        }
        
        /* Column Widths */
        .col-no { width: 30px !important; text-align: center !important; }
        .col-date { width: 65px !important; }
        .col-loc { width: 90px !important; }
        .col-req { width: 90px !important; }
        .col-dept { width: 90px !important; }
        .col-cat { width: 90px !important; }
        .col-desc { width: auto !important; } /* Flexible */
        .col-stat { width: 60px !important; text-align: center !important; }
    </style>
</head>
<body class="font-sans text-[#111827] text-[10px]">

<div class="print-stage">
    
    <div class="no-print w-[210mm] max-w-full mb-6 flex items-center justify-between border-b border-slate-200 pb-5">
        <div>
            <h2 class="text-xl font-bold text-[#003628] tracking-tight uppercase">Pratinjau Laporan Kerja</h2>
            <p class="mt-1 text-sm font-medium text-slate-500">A4 Portrait Mode • Siap Cetak</p>
        </div>
        <div class="flex items-center gap-3">
            <button class="h-10 px-6 rounded-xl bg-[#003628] text-white text-xs font-bold uppercase tracking-widest shadow-lg hover:brightness-110 transition-all flex items-center gap-2" onclick="window.print()">
                Cetak Dokumen
            </button>
        </div>
    </div>

    <div class="batch-print-canvas shared-print">
        
        <div class="flex-grow">
            <table class="shared-header-table w-full">
                <tbody>
                    <tr>
                        <td class="shared-logo-cell">
                            <img src="{{ asset('form-logo.png') }}" class="shared-logo" alt="Zinus" />
                        </td>
                        <td class="shared-title-cell">
                            <div class="shared-title-main">LAPORAN KERJA</div>
                            <div class="shared-title-sub">PT. {{ $techCompany ?: 'ZINUS DREAM INDONESIA' }}</div>
                        </td>
                        <td class="shared-meta-cell">
                            <div class="font-semibold">IT Dept.</div>
                            <div>Dicetak Pada:</div>
                            <div>{{ $printedAt }}</div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="shared-recipient-note mt-3">
                <div class="flex items-center justify-between w-full">
                    <div class="text-left">
                        <strong>Teknisi:</strong> {{ $technician ?: 'Semua Teknisi' }}
                    </div>
                    <div class="text-center">
                        <strong>Periode:</strong> {{ $fromDate ?? '—' }} - {{ $toDate ?? '—' }}
                    </div>
                    <div class="text-right">
                        <strong>Total:</strong> {{ $tickets->count() }} tiket
                    </div>
                </div>
            </div>

            <table class="batch-table mt-2">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-date">Tanggal</th>
                        <th class="col-loc">Lokasi</th>
                        <th class="col-req">Peminta</th>
                        <th class="col-dept">Departemen</th>
                        <th class="col-cat">Kategori</th>
                        <th class="col-desc">Deskripsi Masalah</th>
                        <th class="col-stat">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $i => $ticket)
                        <tr>
                            <td class="text-center font-medium">{{ $i + 1 }}</td>
                            <td class="whitespace-nowrap">{{ optional($ticket->created_at)->format('d/m/y') ?? '—' }}</td>
                            <td>{{ $ticket->location ?: '—' }}</td>
                            <td>{{ $ticket->requester ?: '—' }}</td>
                            <td>{{ $ticket->department ?: '—' }}</td>
                            <td>{{ $ticket->category ?: '—' }}</td>
                            <td>{{ $ticket->issue_description ?: '—' }}</td>
                            <td class="text-center font-bold">{{ $ticket->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-10 text-center text-slate-400 italic font-medium">Tidak ada data ditemukan untuk periode yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="shared-signature-grid mt-auto pt-8 w-full" style="grid-template-columns: repeat(2, 1fr) !important">
            <table class="shared-signature-table">
                <tbody>
                    <tr><td class="shared-signature-head">Teknisi</td></tr>
                    <tr>
                        <td class="shared-signature-body">
                            <div class="shared-signature-stack">
                                <div class="shared-signature-image-box"></div>
                                <div class="shared-signature-name mt-auto border-t border-[#d1d8d4] pt-1">
                                    {{ $printedBy }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="shared-signature-table">
                <tbody>
                    <tr><td class="shared-signature-head">Disetujui Oleh</td></tr>
                    <tr>
                        <td class="shared-signature-body">
                            <div class="shared-signature-stack">
                                <div class="shared-signature-image-box"></div>
                                <div class="shared-signature-name mt-auto border-t border-[#d1d8d4] pt-1">
                                    {{ $approvedBy ?: '______________________' }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 800);
    };
</script>
</body>
</html>


