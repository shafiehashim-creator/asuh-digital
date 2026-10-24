# ASUH Digital — panduan projek untuk Claude Code

Platform e-book kanak-kanak Islamik (umur 6–13) oleh ASUH, jenama Galeri Ilmu Media Group.
Pembeli: ibu bapa (Pas Tahunan, e-book single) dan institusi (lesen tahunan untuk tadika, sekolah rendah agama, KAFA, tahfiz).
Tagline jenama: **Jom Jadi Baik!**
Rujukan skrin: prototaip ASUH Digital di claude.ai (7 skrin: utama, katalog, halaman buku, harga, bayaran, rak buku, Mod Anak).

## Peraturan kerja (wajib)

- Jangan push ke `main`, deploy ke server, atau jalankan migrasi di production tanpa pengesahan saya dalam chat.
- Jangan sesekali commit rahsia (kata laluan, API key, kredensial cPanel, kunci CHIP). Semua dalam `.env`, dan `.env` mesti ada dalam `.gitignore`.
- Sebelum mula kerja baru, baca semula fail ini dan nyatakan fasa yang sedang dibina.
- Semua teks yang dilihat pengguna dalam Bahasa Melayu. Kod, nama jadual dan komen dalam Bahasa Inggeris.
- Setiap fasa mesti ada ujian (Pest atau PHPUnit) untuk aliran bayaran, akses e-book dan pintu ibu bapa.

## Stack

- PHP 8.3 dengan **Laravel 13**. Laravel 11 tidak digunakan kerana sokongan keselamatannya sudah tamat dan Composer menyekatnya atas amaran keselamatan.
- MySQL 8.0 di production (cPanel). SQLite untuk pembangunan dan ujian lokal, jadi migrasi mesti serasi dengan kedua-duanya.
- **Filament 5** untuk panel admin di `/admin`
- Blade, Tailwind CSS dan Alpine.js untuk bahagian awam. Aset dibina secara lokal atau dalam GitHub Actions. **Tiada Node.js di server.**
- PDF.js untuk pembaca e-book dalam pelayar
- Queue: driver `database`, dijalankan melalui cron cPanel (`schedule:run` setiap minit)
- E-mel: SMTP daripada hos cPanel

## Jenama

| Token | Nilai | Guna untuk |
| --- | --- | --- |
| `plum` | #3A1F6E | Header, butang gelap, tajuk kecil |
| `magenta` | #C4107A | Tajuk utama (H1) |
| `kuning` | #FFDE37 | Butang utama |
| `oren` | #F79521 | Sorotan, label "Penggoda" |
| `biru` | #1559D6 | Ikon, bar kemajuan, label "Watak baik" |
| `ink` | #231F20 | Teks dan garis |
| `latar` | #F7F5FF | Latar halaman |
| `lavender` | #E9E4FB | Latar Mod Anak |

- Fon: **Baloo 2** (tajuk, 600–800) dan **Nunito** (teks, 400–800) daripada Google Fonts.
- Butang utama gaya pelekat: latar kuning, teks ink, `border: 2px solid #231F20`, `box-shadow: 0 3px 0 #231F20`, bentuk pil.
- Aset jenama mula-mula berada dalam folder `_brand/` di akar projek: `logo-asuh.png`, `anis.png`, `anas.png`, `syaitan-gemuk.png`, `syaitan-kurus.png` (semuanya versi yang telah dibaiki). Selepas Laravel dipasang, pindahkan ke `resources/images/brand/` dan padam folder `_brand/`.
- Laravel dipasang ke dalam folder ini yang sudah ada `README.md`, `CLAUDE.md` dan `_brand/`. Pasang ke folder sementara, kemudian pindahkan fail ke sini tanpa menimpa `CLAUDE.md`.
- Sasaran kontras teks 4.5:1 dan saiz butang sentuh sekurang-kurangnya 44px.

## Model data (jadual utama)

- `users`: role `admin`, `editor`, `parent`, `institution_admin`
- `children`: `user_id`, `nickname`, `age_group` (`6-9` / `10-13`), `avatar`. Maksimum 4 setiap akaun.
- `categories`: Komik, Kisah nabi, Akhlak, Doa harian, Cerita bergambar, Novel kanak-kanak, Sirah
- `books`: tajuk, slug, penulis, sinopsis, `age_group`, kategori, harga single, `is_enhanced_edition`, `published_at`, status (`draft` / `review` / `published`)
- `book_files`: `book_id`, edisi (`original` / `enhanced`), path fail peribadi, bilangan halaman
- `syariah_reviews`: `book_id`, penyemak, status, catatan, tarikh. **Buku tidak boleh diterbitkan tanpa semakan yang lulus.**
- `plans`: `pas_tahunan` (RM99), `pas_tahunan_early` (RM79), `pas_plus` (Fasa 2), `single`
- `orders`: `user_id`, `plan_id` / `book_id`, jumlah, kaedah (`chip` / `bank_transfer`), status (`pending` / `paid` / `failed` / `refunded`), rujukan CHIP, resit pindahan bank, disahkan oleh dan bila
- `settings`: tetapan yang boleh disunting di admin (harga, had slot, teks landing page, butiran bank, maklumat syarikat, e-mel notifikasi)
- `passes`: `user_id`, `plan_id`, `starts_at`, `ends_at`
- `entitlements`: akses buku single yang dibeli
- `institutions`, `licenses`, `pilot_requests`
- `preorders`: nama, e-mel, telefon, status bayaran (untuk Ujian Permintaan)
- `reading_sessions`: `child_id`, `book_id`, minit, halaman terakhir, tarikh
- `quizzes`, `quiz_questions`, `quiz_attempts`, `badges`, `child_badges`

## Panel admin (Filament 5)

Resource: Buku (dengan muat naik fail dan semakan syariah), Kategori, Pelanggan, Profil anak (baca sahaja), Pesanan, Pas, Institusi dan lesen, Permohonan pilot, Pre-order, Kuiz dan lencana, Pengguna admin.

Widget papan pemuka:
- Pre-order berbanding target 100 (Ujian Permintaan)
- Jualan hari ini, minggu ini dan bulan ini
- Pas aktif dan pas yang tamat dalam 30 hari
- Institusi pilot berbanding yang membayar
- Buku paling banyak dibaca

Peranan: `admin` boleh akses semua. `editor` hanya boleh urus buku, kuiz dan semakan, tanpa akses kewangan.

## Bayaran (CHIP dan pindahan bank)

Gateway ialah **CHIP** (chip-in.asia), bukan BCL. Semua kaedah CHIP dibenarkan (FPX, kad, e-wallet). Kunci CHIP (Brand ID, API key, public key webhook) hanya dalam `.env`.

- Setiap pesanan mencipta *purchase* CHIP dengan rujukan pesanan yang unik.
- Pengaktifan Pas **hanya melalui webhook CHIP** yang tandatangannya disahkan, bukan melalui halaman redirect selepas bayaran.
- Webhook mesti idempotent: rujukan yang sama tidak boleh mengaktifkan pas dua kali. Log setiap panggilan webhook.
- Aliran CHIP (dibina 2026-10-24): callback `POST /webhooks/chip` disahkan dengan `X-Signature` (RSA-SHA256, kunci awam dari `GET /public_key/`), kemudian server **bertanya semula** `GET /purchases/{id}/` dan hanya mengaktifkan pas jika status `paid`, jumlah dan mata wang sepadan, dan `is_test` sepadan dengan `CHIP_TEST_MODE`. Semua pengaktifan melalui `App\Services\PaymentProcessor::markPaid()`.
- CHIP menolak URL callback yang mempunyai nombor port, jadi callback hanya sampai di server sebenar (bukan `localhost:8000`).
- Bayaran yang sampai **selepas tempahan luput tetap dihormati** (keputusan pengguna): pas diaktifkan walaupun kiraan slot terlebih sedikit. Admin boleh kenal pasti kes ini kerana `paid_at` lebih lewat daripada `reserved_until`.
- **Pindahan bank:** pembeli muat naik resit, pesanan kekal `pending`, dan hanya `admin` boleh klik **Sahkan bayaran**. Rekod siapa yang mengesahkan dan bila. Butiran bank ialah tetapan admin.
  - Resit (JPG/PNG/WEBP/PDF, maks 5 MB) disimpan dalam `storage/app/private/receipts` dan hanya dipaparkan kepada `admin` melalui `/admin/resit/{rujukan}`.
  - Resit yang dihantar mengekalkan slot sehingga admin membuat keputusan. Resit untuk tempahan yang sudah luput tetap diterima (selaras dengan dasar menghormati bayaran lewat).
  - Admin boleh **Tolak** dengan sebab (dilog); slot dilepaskan dan pembeli boleh menghantar resit baharu. Pesanan dalam panel admin adalah baca sahaja.
- Tiada caj berulang automatik. Bayaran sekali setahun.
- Hantar resit melalui e-mel selepas status pesanan bertukar kepada `paid`. Maklumat syarikat pada resit (nama, SSM, alamat, SST) ialah tetapan admin.

## Skop Fasa A (dipersetujui 2026-10-24)

- Pre-order **Pas Tahunan harga early bird RM79**, bayar penuh, terhad **100 slot**. Slot ditempah sementara (~30 minit) semasa bayaran dan dilepaskan jika tidak dibayar.
- Pas 12 bulan bermula **selepas bayaran disahkan**. Peraturan tarikh mula boleh ditukar di admin (contohnya kepada tarikh launch).
- Pembeli **cipta akaun semasa checkout** (e-mel dan kata laluan, pengesahan e-mel, lupa kata laluan). Halaman "Akaun saya" memaparkan status pesanan dan pas, dan muat naik resit pindahan bank.
- Maklumat pembeli: nama, e-mel, telefon, negeri, bilangan anak, kumpulan umur anak, dan persetujuan PDPA.
- Landing page memaparkan kaunter baki slot dan tempoh pilot institusi. Headline, subheadline, FAQ, polisi bayaran balik dan tempoh pilot boleh disunting di admin.
- Polisi bayaran balik: dibenarkan (teks muktamad disunting di admin).
- Borang pilot institusi: nama institusi, jenis, negeri dan daerah, pegawai, jawatan, e-mel, telefon, anggaran murid, kumpulan umur, catatan. Notifikasi ke e-mel admin (tetapan) dan status di admin: Baru, Dihubungi, Pilot aktif, Membayar, Tidak meneruskan.
- Satu Super Admin (role `admin`), dicipta melalui `php artisan asuh:create-admin`. Role `editor` wujud tetapi belum digunakan.
- Notis privasi dan terma didraf dalam BM, dan **mesti disemak** pihak pengurusan atau undang-undang sebelum go-live.
- Sasaran live sekitar 7 November 2026 (boleh berubah).

## Perlindungan e-book

- Fail disimpan dalam `storage/app/private`, **tidak** dalam `public/`.
- Fail dihantar melalui controller selepas semakan login dan hak akses (pas aktif, entitlement atau lesen institusi).
- Gunakan URL bertandatangan yang tamat dalam masa yang singkat, dan hadkan kadar permintaan.
- Letak watermark ringan dengan e-mel pemilik akaun pada paparan pembaca.

## Mod Anak dan pintu ibu bapa

- Mod Anak tidak memaparkan harga, butang beli atau tetapan akaun.
- Untuk keluar dari Mod Anak, pengguna perlu melepasi pintu ibu bapa (soalan matematik rawak atau PIN 4 digit ibu bapa).
- Rak buku Mod Anak hanya memaparkan buku ikut `age_group` anak tersebut.
- Lencana diberi berdasarkan kuiz yang dijawab betul dan konsistensi membaca, bukan berdasarkan bilangan halaman yang diselak.

## Privasi kanak-kanak (PDPA)

- Akaun adalah milik ibu bapa. Anak tidak mempunyai e-mel atau kata laluan sendiri.
- Data anak yang disimpan hanyalah nama panggilan dan kumpulan umur. Tiada foto, tarikh lahir atau sekolah.
- Halaman notis privasi dalam BM, dan persetujuan ibu bapa semasa mendaftar.
- Ibu bapa boleh memadam profil anak dan semua data bacaannya.

## Git dan deploy ke cPanel

- Repo GitHub **peribadi**. Cawangan `main` untuk production dan `develop` untuk kerja harian. Hanya `main` yang dideploy.
- **Tiada staging untuk Fasa A.** Sebelum launch, production (`asuh.galeriilmu.com.my`) diuji dengan CHIP mod ujian (test mode), kemudian kunci ditukar kepada live dan pesanan ujian dikosongkan. Staging (`staging-asuh.galeriilmu.com.my`) ditimbang semula sebelum Fasa B.
- Struktur server: kod di `/home/galeriil78/asuh-app` (di luar `public_html`), dan `public_html/asuh.galeriilmu.com.my` ialah symlink ke `asuh-app/public`. Laman utama syarikat berada dalam akaun cPanel yang sama, jadi jangan sentuh `public_html` yang lain.
- DNS di Cloudflare: rekod A `asuh` dalam mod DNS only (awan kelabu) supaya AutoSSL cPanel berfungsi.
- Deploy melalui GitHub Actions ke cPanel menggunakan SSH. Langkah: `composer install --no-dev`, bina aset, `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`.
- Pilihan alternatif: ciri Git Version Control cPanel dengan fail `.cpanel.yml`.
- Document root domain mesti menghala ke folder `public/`.
- Kredensial SSH disimpan dalam GitHub Secrets, bukan dalam repo.
- Pasang cron: `* * * * * php /path/artisan schedule:run`

## Semak cPanel dulu (langkah pertama sebelum menulis kod)

Tanya saya dan sahkan perkara ini:
1. Versi PHP yang ada (perlu 8.2+) dan sambungan PHP yang dibenarkan
2. Akses SSH dan Composer
3. MySQL atau MariaDB, serta versinya
4. Cron job
5. SSL untuk domain
6. Akaun SMTP untuk e-mel
7. Had storan dan bandwidth

## Fasa pembinaan

| Fasa | Skop | Sasaran |
| --- | --- | --- |
| A | Landing page pre-order, akaun ibu bapa, bayaran CHIP dan pindahan bank, borang pilot institusi, panel admin untuk pre-order, pesanan, pilot dan tetapan | **Live dalam 2 minggu** (Ujian Permintaan) |
| B | Katalog, halaman buku, akaun ibu bapa, Pas Tahunan, pembaca e-book, perlindungan fail, admin buku dengan semakan syariah | Sebelum PBAKL |
| C | Profil anak, Mod Anak dan pintu ibu bapa, laporan bacaan mingguan melalui e-mel, kuiz dan lencana | Sebelum PBAKL |
| D | Lesen institusi: akaun guru dan kod kelas | Selepas pilot institusi |
| Fasa 2 | Pas Tahunan Plus dan animasi. Video dihoskan di luar cPanel (contohnya penstriman video berbayar atau YouTube unlisted), bukan dalam server cPanel | 2027 |

Setiap fasa dikira siap apabila: ujian lulus, berjalan di server (staging jika ada, atau production dalam mod ujian CHIP sebelum launch untuk Fasa A), dan saya telah menguji aliran utama sendiri.
