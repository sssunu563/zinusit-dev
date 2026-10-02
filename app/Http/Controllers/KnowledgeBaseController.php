<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureDefaultArticlesExist();

        $articles = Article::query()
            ->select(['id', 'title', 'slug', 'category', 'view_count', 'created_at', \DB::raw('LEFT(content, 150) as content_preview')])
            ->where('is_published', true)
            ->when($request->search, function($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Cache categories untuk 1 jam
        $categories = \Cache::remember('kb_categories', 3600, function() {
            return Article::where('is_published', true)->distinct()->pluck('category')->filter()->values();
        });

        return Inertia::render('KnowledgeBase/Index', [
            'articles' => $articles,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function show(Request $request, Article $article)
    {
        $article->increment('view_count');
        $article->load('author:id,name');

        return Inertia::render('KnowledgeBase/Show', [
            'article' => $article,
            'related' => Article::where('category', $article->category)
                ->where('id', '!=', $article->id)
                ->limit(3)
                ->get(),
        ]);
    }

    protected function ensureDefaultArticlesExist(): void
    {
        if (Article::count() > 0) {
            return;
        }

        $defaultAuthorId = \App\Models\User::first()?->id;
        if (!$defaultAuthorId) {
            return;
        }

        $defaults = [
            [
                'title' => 'Panduan Menghubungkan Laptop ke Wi-Fi Perusahaan',
                'category' => 'Network & WiFi',
                'slug' => 'panduan-koneksi-wifi-perusahaan',
                'content' => "## Cara Terhubung ke Wi-Fi Kantor (Zinus Secure)\n\nUntuk menjaga keamanan data perusahaan, seluruh laptop operasional wajib terhubung ke jaringan resmi kantor.\n\n### Langkah-langkah Koneksi:\n1. Buka pengaturan Wi-Fi di pojok kanan bawah desktop Anda.\n2. Pilih SSID jaringan: **Zinus-Office** atau **Zinus-Staff**.\n3. Masukkan identitas akun domain/LDAP Anda (Username dan Password Windows yang biasa digunakan untuk login laptop).\n4. Jika muncul konfirmasi sertifikat keamanan (*Server Certificate Validation*), klik **Connect / Hubungkan**.\n\n> **Catatan:** Jangan membagikan password jaringan kepada pihak luar atau tamu non-karyawan. Untuk tamu, gunakan jaringan **Zinus-Guest** yang memerlukan registrasi resepsionis.\n\n### Kendala Sering Terjadi:\n- **Gagal Otentikasi:** Pastikan Caps Lock tidak aktif saat mengetik password.\n- **Koneksi Terputus:** Coba matikan Wi-Fi laptop selama 5 detik kemudian nyalakan kembali (*Forget Network* dan sambung ulang).",
                'is_published' => true,
                'author_id' => $defaultAuthorId,
            ],
            [
                'title' => 'Cara Menghubungkan Printer Jaringan Kantor ke Laptop',
                'category' => 'Hardware & Perangkat',
                'slug' => 'cara-menghubungkan-printer-jaringan-kantor',
                'content' => "## Panduan Menambah Printer Jaringan\n\nSetiap lantai operasional Zinus dilengkapi dengan printer multifungsi (*Network Printer*) yang dapat diakses melalui jaringan lokal.\n\n### Langkah Pemasangan Printer di Windows:\n1. Pastikan laptop Anda terhubung ke jaringan **Zinus-Office** (bukan Guest).\n2. Tekan tombol **Windows + R** pada keyboard untuk membuka dialog *Run*.\n3. Ketik alamat IP printer kantor Anda (misal: `\\\\192.168.1.50` atau nama share yang tertera pada stiker fisik printer).\n4. Klik kanan pada icon printer yang muncul, lalu pilih **Connect**.\n5. Tunggu proses instalasi driver printer otomatis hingga selesai.\n\n### Tips Mencetak Hemat:\n- Selalu gunakan opsi **Duplex (Print 2 sisi / bolak-balik)** untuk dokumen draf.\n- Gunakan mode *Grayscale* untuk dokumen teks biasa demi efisiensi toner.",
                'is_published' => true,
                'author_id' => $defaultAuthorId,
            ],
            [
                'title' => 'Pengaturan Email Outlook & Reset Password Akun IT',
                'category' => 'Software & Email',
                'slug' => 'pengaturan-email-outlook-dan-reset-password',
                'content' => "## Konfigurasi Email & Akun IT Karyawan\n\nSetiap karyawan Zinus mendapatkan alamat email resmi `@zinus.com` untuk komunikasi profesional.\n\n### Konfigurasi Microsoft Outlook:\n1. Buka aplikasi **Outlook** di laptop Anda.\n2. Masukkan alamat email lengkap Anda: `nama.karyawan@zinus.com`.\n3. Klik **Connect** dan masukkan password akun domain Anda.\n4. Ikuti instruksi verifikasi 2 langkah (MFA/2FA) jika diminta.\n\n### Kebijakan Password IT:\n- Minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka.\n- Password wajib diperbarui secara berkala demi keamanan data perusahaan.\n- Jika akun terkunci (*locked out*) akibat salah password lebih dari 5 kali, hubungi tim IT Support untuk melakukan *unlock* akun.",
                'is_published' => true,
                'author_id' => $defaultAuthorId,
            ],
            [
                'title' => 'Prosedur Pelaporan Perbaikan & Kerusakan Laptop (Helpdesk)',
                'category' => 'Hardware & Perangkat',
                'slug' => 'prosedur-pelaporan-perbaikan-laptop-rusak',
                'content' => "## Prosedur Layanan IT Helpdesk\n\nJika perangkat kerja Anda mengalami kendala fisik (layar mati, keyboard rusak, baterai drop, blue screen), ikuti langkah pelaporan berikut:\n\n### Langkah Pelaporan:\n1. Catat nomor **Asset Tag** (contoh: `ZGI-NB-001`) dan **Serial Number** yang tertempel pada stiker fisik laptop.\n2. Hubungi tim IT Support di ruang IT lantai 2 atau melalui kontak Helpdesk resmi.\n3. Sertakan penjelasan kendala dan foto error jika memungkinkan.\n4. Tim IT akan melakukan diagnosa awal dan menyiapkan laptop pengganti (*backup unit*) jika perbaikan memerlukan waktu lebih dari 1 hari kerja.\n\n> **Penting:** Dilarang membongkar atau membawa laptop inventaris kantor ke tempat servis luar tanpa persetujuan tertulis dari IT Supervisor.",
                'is_published' => true,
                'author_id' => $defaultAuthorId,
            ],
            [
                'title' => 'Panduan Keamanan Data: Menghindari Phishing & Malware',
                'category' => 'Keamanan & Akun',
                'slug' => 'panduan-keamanan-menghindari-phishing',
                'content' => "## Tetap Aman dari Ancaman Siber di Tempat Kerja\n\nSerangan phishing sering kali menyamar sebagai email resmi dari manajemen, bank, atau vendor luar.\n\n### Ciri-ciri Email Mencurigakan:\n- Alamat email pengirim tidak sesuai dengan nama tampilan (*display name* palsu).\n- Mendesak Anda untuk segera klik link atau mengisi password / kredensial.\n- Terdapat lampiran file executable (`.exe`, `.zip`, `.scr`) yang tidak jelas sumbernya.\n\n### Tindakan Pencegahan:\n1. Selalu periksa domain pengirim sebelum membuka lampiran.\n2. Jangan pernah memberikan password Anda kepada siapapun — **Tim IT tidak akan pernah meminta password Anda**.\n3. Selalu kunci layar laptop (**Windows + L**) saat meninggalkan meja kerja.",
                'is_published' => true,
                'author_id' => $defaultAuthorId,
            ],
        ];

        foreach ($defaults as $data) {
            Article::create($data);
        }
    }

    public function create()
    {
        $categories = Article::distinct()->pluck('category');

        return Inertia::render('KnowledgeBase/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'content' => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $article = Article::create([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'is_published' => $validated['is_published'] ?? true,
            'slug' => Str::slug($validated['title']) . '-' . rand(1000, 9999),
            'author_id' => $request->user()->id,
        ]);

        return redirect()->route('kb.show', $article->slug)->with('success', 'Artikel berhasil dibuat.');
    }

    public function edit(Request $request, Article $article)
    {
        $user = $request->user();
        if ($article->author_id !== $user->id && !$user->is_admin) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit artikel ini.');
        }

        $categories = Article::distinct()->pluck('category');

        return Inertia::render('KnowledgeBase/Edit', [
            'article' => $article->load('author:id,name'),
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $user = $request->user();
        if ($article->author_id !== $user->id && !$user->is_admin) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit artikel ini.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'content' => 'required|string',
            'is_published' => 'nullable|boolean',
        ]);

        $updateData = [
            'title' => $validated['title'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'is_published' => $validated['is_published'] ?? $article->is_published,
        ];

        if ($article->title !== $validated['title']) {
            $updateData['slug'] = Str::slug($validated['title']) . '-' . rand(1000, 9999);
        }

        $article->update($updateData);

        return redirect()->route('kb.show', $article->fresh()->slug)->with('success', 'Artikel berhasil diperbarui.');
    }

    public function destroy(Request $request, Article $article)
    {
        $user = $request->user();
        if ($article->author_id !== $user->id && !$user->is_admin) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus artikel ini.');
        }

        $article->delete();

        return redirect()->route('kb.index')->with('success', 'Artikel berhasil dihapus.');
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
        ]);

        $path = $request->file('image')->store('articles', 'public');
        $url = Storage::url($path);

        return response()->json([
            'url' => $url,
            'filename' => $request->file('image')->getClientOriginalName(),
        ]);
    }
}
