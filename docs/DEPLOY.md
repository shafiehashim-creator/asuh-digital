# Deploy ASUH Digital ke cPanel

Panduan untuk deploy pertama ke `https://asuh.galeriilmu.com.my` dan cara kerja deploy seterusnya.

## Cara ia berfungsi

1. Setiap push ke `develop` atau `main` menjalankan **ujian** dan **raptai deploy** di GitHub Actions (`.github/workflows/deploy.yml`).
2. Push atau merge ke `main` juga **deploy** ke cPanel melalui SSH, selepas anda klik *Approve* (jika environment `production` mempunyai *required reviewer*).
3. Setiap deploy ialah satu *release* baharu. Trafik hanya ditukar selepas migrasi berjaya, jadi laman tidak rosak di tengah-tengah deploy.

Susunan di server (akaun `galeriil78`):

```
/home/galeriil78/
├── public_html/                          laman utama syarikat (tidak disentuh)
│   └── asuh.galeriilmu.com.my  ──►  /home/galeriil78/asuh-app/current/public
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

Semakan 2026-10-24: `php` di Terminal ialah **7.3** (lalai sistem, jangan diubah). PHP 8.3 berada di `/opt/cpanel/ea-php83/root/usr/bin/php`, dan laluan inilah yang digunakan oleh deploy dan cron (`DEPLOY_PHP`). Server tiada `rsync`; deploy menggunakan tar melalui SSH, jadi rsync tidak diperlukan.

Pastikan juga subdomain menggunakan PHP 8.3: cPanel → **MultiPHP Manager** → `asuh.galeriilmu.com.my` mesti **PHP 8.3 (ea-php83)**. Jika tidak, tanda subdomain itu, pilih PHP 8.3 dan klik **Apply**. ☐

### 6. Fail `.env` di server ☐

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

Biarkan `APP_KEY` kosong; ia dijana semasa deploy pertama. Simpan (Ctrl+O, Enter, Ctrl+X), kemudian:

```bash
chmod 600 ~/asuh-app/shared/.env
```

> Jangan tampal kata laluan atau kunci ini dalam chat atau dalam repo.

### 7. Kunci SSH untuk GitHub Actions ☐

Di Terminal cPanel, cipta kunci khas untuk deploy dan benarkannya masuk ke akaun ini:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N "" -C "github-actions-asuh"
cat ~/.ssh/github_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
cat ~/.ssh/github_deploy
```

Salin **seluruh** output arahan terakhir (dari `-----BEGIN` hingga `-----END ... KEY-----`) ke GitHub Secret `SSH_PRIVATE_KEY` (langkah 8). Selepas disimpan di GitHub, padam salinan kunci peribadi di server:

```bash
rm ~/.ssh/github_deploy
```

Dapatkan juga nama hos dan port SSH: cPanel → **SSH Access** (atau tanya hos). Biasanya port `22`.

Untuk `SSH_KNOWN_HOSTS`, jalankan di komputer anda (Git Bash), gantikan HOS dan PORT:

```bash
ssh-keyscan -p PORT HOS
```

Bandingkan cap jari dengan output arahan ini di Terminal cPanel (mesti sama):

```bash
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

### 8. Tetapan repo GitHub ☐

Repo → **Settings** → **Secrets and variables** → **Actions**

**Secrets** (tab *Secrets*):

| Nama | Nilai |
| --- | --- |
| `SSH_HOST` | Nama hos atau IP server |
| `SSH_PORT` | Port SSH (contoh `22`) |
| `SSH_USER` | `galeriil78` |
| `SSH_PRIVATE_KEY` | Kunci peribadi dari langkah 7 |
| `SSH_KNOWN_HOSTS` | Output `ssh-keyscan` dari langkah 7 |

**Variables** (tab *Variables*):

| Nama | Nilai |
| --- | --- |
| `DEPLOY_PATH` | `/home/galeriil78/asuh-app` |
| `DEPLOY_DOCROOT` | `/home/galeriil78/public_html/asuh.galeriilmu.com.my` |
| `DEPLOY_PHP` | `/opt/cpanel/ea-php83/root/usr/bin/php` |
| `APP_URL` | `https://asuh.galeriilmu.com.my` |

**Disyorkan:** Repo → **Settings** → **Environments** → **New environment** → `production` → tanda **Required reviewers** dan tambah diri anda. Setiap deploy akan menunggu anda klik *Approve*.

### 9. Push dan deploy ☐

Beritahu Claude dalam chat apabila langkah 1 hingga 8 siap. Claude akan:

1. Push `develop` (ujian dan raptai deploy berjalan di GitHub, tiada apa-apa di server).
2. Selepas anda luluskan, merge `develop` ke `main`, yang memulakan deploy sebenar.

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

## Jika ada masalah selepas deploy

Kembali ke release sebelumnya (Terminal cPanel):

```bash
bash ~/asuh-app/current/scripts/deploy/rollback.sh ~/asuh-app /opt/cpanel/ea-php83/root/usr/bin/php
```

Log ralat aplikasi: `~/asuh-app/shared/storage/logs/`.
