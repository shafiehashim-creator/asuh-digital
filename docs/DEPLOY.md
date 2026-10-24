# Deploy ASUH Digital ke cPanel

Panduan untuk deploy pertama ke `https://asuh.galeriilmu.com.my` dan cara kerja deploy seterusnya.

## Cara ia berfungsi

1. Setiap push ke `develop` atau `main` menjalankan **ujian** dan **raptai deploy** di GitHub Actions (`.github/workflows/deploy.yml`).
2. Push atau merge ke `main` juga **membina release lengkap** (`vendor` dan aset) dan menyimpannya dalam cawangan `deploy-build`.
3. **Anda** deploy dari cPanel → **Git™ Version Control** → *Update from Remote* → *Deploy HEAD Commit*. Fail `.cpanel.yml` menjalankan `scripts/deploy/cpanel-deploy.sh`. GitHub tidak pernah masuk ke server, jadi tiada SSH dari luar atau Secrets diperlukan.
4. Setiap deploy ialah satu *release* baharu. Trafik hanya ditukar selepas migrasi berjaya, jadi laman tidak rosak di tengah-tengah deploy.

Susunan di server (akaun `galeriil78`):

```
/home/galeriil78/
├── public_html/                          laman utama syarikat (tidak disentuh)
│   └── asuh.galeriilmu.com.my  ──►  /home/galeriil78/asuh-app/current/public
├── repositories/asuh-digital/            klon cPanel Git (cawangan deploy-build)
└── asuh-app/                             di luar public_html: .env tidak boleh dicapai dari web
    ├── releases/20261107093000-abc1234/  5 release terakhir disimpan
    ├── current  ──►  releases/<terkini>
    └── shared/
        ├── .env                          rahsia sebenar (chmod 600)
        └── storage/                      resit, log, cache (kekal antara release)
```

---

## Tugasan anda untuk deploy pertama

Buat mengikut urutan. Tanda ✅ apabila siap.

### 1. Subdomain ✅

cPanel → **Domains** → **Create a New Domain**

- Domain: `asuh.galeriilmu.com.my`
- *Share document root*: **tidak bertanda**
- Document root: biarkan `public_html/asuh.galeriilmu.com.my`
- Biarkan folder itu **kosong**. Deploy pertama akan menukarnya menjadi symlink secara automatik dan menyimpan folder asal sebagai sandaran.

### 2. Rekod DNS di Cloudflare ✅

Cloudflare → `galeriilmu.com.my` → **DNS** → **Add record**

| Type | Name | IPv4 address | Proxy status |
| --- | --- | --- | --- |
| A | `asuh` | IP server (cPanel → *General Information* → *Shared IP Address*) | **DNS only** (awan kelabu) |

### 3. SSL dan HTTPS ✅

- cPanel → **SSL/TLS Status** → tanda `asuh.galeriilmu.com.my` → **Run AutoSSL**. Tunggu sehingga hijau.
- cPanel → **Domains** → hidupkan **Force HTTPS Redirect** untuk `asuh.galeriilmu.com.my`.

### 4. Pangkalan data MySQL ✅

cPanel → **Manage My Databases**

1. *Create New Database*: `asuh` (menjadi `galeriil78_asuh`)
2. *Add New User*: `asuh` (menjadi `galeriil78_asuh`), guna **Password Generator** dan simpan kata laluan di tempat selamat
3. *Add User To Database*: pilih kedua-duanya → **ALL PRIVILEGES**

### 5. PHP 8.3 untuk subdomain dan Terminal ✅

Semakan 2026-10-24: `php` di Terminal ialah **7.3** (lalai sistem, jangan diubah). PHP 8.3 berada di `/opt/cpanel/ea-php83/root/usr/bin/php`, dan laluan inilah yang digunakan oleh deploy dan cron (`DEPLOY_PHP`). Server tiada `rsync`; skrip deploy menggunakan `tar`, jadi rsync tidak diperlukan. Laluan PHP boleh diubah dalam `~/asuh-app/shared/deploy.conf` (`DEPLOY_PHP=...`) jika perlu.

Pastikan juga subdomain menggunakan PHP 8.3: cPanel → **MultiPHP Manager** → `asuh.galeriilmu.com.my` mesti **PHP 8.3 (ea-php83)**. Jika tidak, tanda subdomain itu, pilih PHP 8.3 dan klik **Apply**. ☐

### 6. Fail `.env` di server ✅

Di Terminal cPanel:

```bash
mkdir -p ~/asuh-app/shared
nano ~/asuh-app/shared/.env
```

Tampal kandungan fail `.env.production.example` dari repo GitHub, kemudian isi:

| Medan | Dari mana |
| --- | --- |
| `DB_PASSWORD` | Langkah 4 |
| `MAIL_HOST`, `MAIL_PORT` | cPanel → **Email Accounts** → `noreply@galeriilmu.com.my` → **Connect Devices** → *Secure SSL/TLS Settings* → *Outgoing Server* |
| `MAIL_PASSWORD` | Kata laluan `noreply@galeriilmu.com.my` |
| `CHIP_BRAND_ID`, `CHIP_API_KEY` | portal.chip-in.asia → **Developers** (guna kunci **test** dahulu, `CHIP_TEST_MODE=true`) |

Biarkan `APP_KEY` kosong; ia dijana semasa deploy pertama.

Kunci CHIP **boleh diisi kemudian**. Tanpanya, laman berfungsi seperti biasa, pindahan bank boleh digunakan, dan butang CHIP hanya memaparkan "Bayaran dalam talian belum dibuka". Status CHIP dipaparkan di panel admin → **Tetapan**.

Untuk akaun e-mel, guna akaun khas ASUH (contohnya `asuh@galeriilmu.com.my`) supaya kata laluan `noreply@` yang digunakan oleh sistem lain tidak perlu ditukar. Isi alamat itu dalam `MAIL_USERNAME` dan `MAIL_FROM_ADDRESS`.

Simpan (Ctrl+O, Enter, Ctrl+X), kemudian:

```bash
chmod 600 ~/asuh-app/shared/.env
```

> Jangan tampal kata laluan atau kunci ini dalam chat atau dalam repo.

### 7. Akses server ke repo GitHub ☐

Repo `asuh-digital` adalah peribadi, jadi server perlu kunci untuk menariknya. Semak dulu sama ada kunci yang anda guna untuk laman lama sudah boleh. Di Terminal cPanel:

```bash
ssh -T git@github.com
```

- Jika keluar `Hi shafiehashim-creator!` (tanpa nama repo), kunci akaun anda sudah ada akses ke semua repo anda. Guna Clone URL `git@github.com:...` dalam langkah 8. **Langkah ini siap.**
- Jika keluar `Hi shafiehashim-creator/<repo-lain>!`, kunci itu ialah **deploy key untuk repo lain** (contohnya `galeri-ilmu-lms`) dan tidak boleh menarik `asuh-digital`. **Jangan ubah kunci itu**, kerana laman lain bergantung padanya. Cipta kunci khas dengan alias hos tersendiri:

  1. Jana kunci dan alias `github-asuh` (hanya digunakan oleh repo ASUH, sambungan `github.com` yang lain tidak terjejas):

     ```bash
     ssh-keygen -t ed25519 -f ~/.ssh/asuh_deploy -N "" -C "cpanel-asuh-digital" && printf '\nHost github-asuh\n  HostName github.com\n  User git\n  IdentityFile ~/.ssh/asuh_deploy\n  IdentitiesOnly yes\n' >> ~/.ssh/config && chmod 600 ~/.ssh/config && cat ~/.ssh/asuh_deploy.pub
     ```

  2. Salin baris yang bermula dengan `ssh-ed25519` (ini kunci **awam**, selamat dikongsi).
  3. GitHub → repo `asuh-digital` → **Settings** → **Deploy keys** → **Add deploy key**. Title: `cPanel asuh`. Tampal kunci. **Jangan** tanda *Allow write access*. Klik **Add key**.
  4. Uji: `ssh -T git@github-asuh` mesti memaparkan `Hi shafiehashim-creator/asuh-digital!`, dan `ssh -T git@github.com` masih memaparkan repo lama anda.
  5. Dalam langkah 8, guna Clone URL `git@github-asuh:shafiehashim-creator/asuh-digital.git`.

### 8. Deploy pertama ☐

1. Beritahu Claude dalam chat. Claude akan meminta kelulusan anda untuk merge `develop` ke `main`. GitHub Actions kemudian menjalankan ujian dan raptai, membina release lengkap dan menyimpannya dalam cawangan **`deploy-build`** (beberapa minit).
2. Selepas cawangan `deploy-build` wujud, di cPanel → **Git™ Version Control** → **Create**:
   - *Clone a Repository*: **hidup**
   - Clone URL: `git@github-asuh:shafiehashim-creator/asuh-digital.git` (atau `git@github.com:...` jika langkah 7 menunjukkan kunci akaun)
   - Repository Path: `repositories/asuh-digital`
   - Repository Name: `asuh-digital`
   - Klik **Create**
3. Klik **Manage** pada repo itu → tab **Basic Information** → *Checked-Out Branch*: pilih **`deploy-build`** → **Update**.
4. Tab **Pull or Deploy** → **Deploy HEAD Commit**. Tunggu sehingga selesai, kemudian buka `https://asuh.galeriilmu.com.my`.

Jika deploy gagal, mesej ralat dari skrip (bermula dengan `DEPLOY ERROR`) terpapar dalam log deploy cPanel. Tampal mesej itu kepada Claude. Tiada apa yang berubah pada laman jika deploy gagal.

### 9. Deploy seterusnya

Setiap kali ada kemas kini yang anda luluskan untuk `main`:

1. Tunggu GitHub Actions selesai (tanda ✅ di tab *Actions* repo).
2. cPanel → **Git™ Version Control** → **Manage** → **Pull or Deploy** → **Update from Remote** → **Deploy HEAD Commit**.

### 10. Selepas deploy pertama ☐

**Cron** (cPanel → **Cron Jobs** → *Once Per Minute*):

```bash
cd /home/galeriil78/asuh-app/current && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Akaun Super Admin** (Terminal cPanel):

```bash
cd ~/asuh-app/current && /opt/cpanel/ea-php83/root/usr/bin/php artisan asuh:create-admin
```

Kemudian log masuk di `https://asuh.galeriilmu.com.my/admin` → **Tetapan**, dan isi butiran bank, maklumat syarikat, e-mel hubungan dan e-mel admin.

**Uji aliran utama (mod ujian CHIP):**

- [ ] Pre-order dengan CHIP, guna kad ujian `4444 3333 2222 1111`, mana-mana nama, tarikh luput masa depan, CVC `123`
- [ ] Pastikan pas aktif dan resit e-mel diterima
- [ ] Pre-order dengan pindahan bank, muat naik resit, sahkan di panel admin
- [ ] Hantar borang pilot dan semak e-mel admin
- [ ] Hantar satu resit ke [mail-tester.com](https://www.mail-tester.com) untuk menyemak SPF/DKIM

---

## Sebelum launch sebenar

- [ ] Notis privasi dan terma telah disemak (dan versi Bahasa Inggeris notis privasi, PDPA s.7(3))
- [ ] Tukar ke kunci CHIP **live** dalam `~/asuh-app/shared/.env` dan set `CHIP_TEST_MODE=false`, kemudian jalankan `cd ~/asuh-app/current && /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize`
- [ ] Kosongkan pesanan ujian (Claude akan sediakan arahan khas yang selamat untuk ini)

## Mengubah `.env` selepas deploy

Setiap kali `~/asuh-app/shared/.env` diubah (contohnya mengisi kunci CHIP, menukar kata laluan e-mel, atau beralih ke kunci live), **wajib** jalankan arahan ini. Laravel menyimpan tetapan dalam cache, jadi tanpanya perubahan tidak akan dibaca:

```bash
cd ~/asuh-app/current && /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize
```

## Jika ada masalah selepas deploy

Kembali ke release sebelumnya (Terminal cPanel):

```bash
bash ~/asuh-app/current/scripts/deploy/rollback.sh ~/asuh-app /opt/cpanel/ea-php83/root/usr/bin/php
```

Log ralat aplikasi: `~/asuh-app/shared/storage/logs/`.
