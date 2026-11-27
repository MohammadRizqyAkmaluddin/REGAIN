# Deep Pockets — Panduan Deploy VPS (deepocket.my.id) & PWA iPhone

Domain: `deepocket.my.id` → IP VPS (IDCloudHost, Ubuntu). Semua perintah `$` dijalankan di VPS via SSH.

## 0. Cek DNS (dari laptop)
```
nslookup deepocket.my.id
```
Hasilnya harus IP VPS kamu (A record). Kalau belum, tunggu propagasi.

## 1. Siapkan VPS
```bash
sudo apt update && sudo apt -y upgrade
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER   # logout-login lagi setelah ini
sudo apt -y install git certbot ufw
sudo ufw allow OpenSSH && sudo ufw allow 80 && sudo ufw allow 443 && sudo ufw enable
sudo mkdir -p /var/www/certbot
```
Di panel IDCloudHost, pastikan firewall/security group VPS juga membuka port 80 & 443.

## 2. Ambil kode
Push project ke GitHub (private), lalu di VPS:
```bash
git clone <URL_REPO> ~/REGAIN && cd ~/REGAIN
```

## 3. Sertifikat HTTPS (Certbot, sekali saja, SEBELUM menyalakan nginx)
```bash
sudo certbot certonly --standalone -d deepocket.my.id
```
Cek file ada di `/etc/letsencrypt/live/deepocket.my.id/`. Auto-renew (nginx dihentikan sebentar saat renew):
```bash
sudo crontab -e
# tambahkan:
0 4 1 * * certbot renew --pre-hook "docker stop regain-nginx" --post-hook "docker start regain-nginx" >> /var/log/certbot-renew.log 2>&1
```


## 4. Konfigurasi environment
```bash
cd ~/REGAIN
cp backend/.env.production.example backend/.env
nano backend/.env          # ganti DB_PASSWORD, MYSQL_ROOT_PASSWORD, VAPID_SUBJECT
cp backend/.env infrastructure/.env   # compose membaca variabel ${...} dari sini
```
APP_KEY & VAPID diisi di langkah 6.

## 5. Build & jalankan
```bash
cd ~/REGAIN/infrastructure
docker compose -f docker-compose.prod.yml up -d --build mysql redis backend
```
## 6. Generate key
```bash
docker compose -f docker-compose.prod.yml exec backend php artisan key:generate --show
docker compose -f docker-compose.prod.yml exec backend php artisan regain:vapid-generate
```
Salin hasilnya ke `backend/.env` (APP_KEY=..., VAPID_PUBLIC_KEY=..., VAPID_PRIVATE_KEY=...), lalu `cp ../backend/.env .env` lagi.

## 7. Migrasi & start semua service
```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
docker compose -f docker-compose.prod.yml exec backend php artisan optimize
docker compose -f docker-compose.prod.yml ps     # semua harus Up
```
Tes: buka `https://deepocket.my.id/api/v1/health` (harus JSON `status: ok`) dan `https://deepocket.my.id`.

> Frontend memakai API base `/api/v1` (same-origin) sehingga tidak perlu CORS tambahan.

## 8. Backup
```bash
chmod +x ~/REGAIN/infrastructure/scripts/backup-mysql.sh
crontab -e
0 2 * * * DB_PASSWORD='PASSWORD_DB' ~/REGAIN/infrastructure/scripts/backup-mysql.sh >> ~/backup.log 2>&1
```
(Container MySQL bernama `regain-mysql`, sesuai default script.)

## 9. Pasang di iPhone (iOS 16.4+)
1. Buka **Safari** → `https://deepocket.my.id` → daftar/login.
2. Tap **Bagikan** → **Tambah ke Layar Utama** → Tambah.
3. Buka aplikasi **dari ikon di layar utama** (bukan dari Safari).
4. Settings app → **Aktifkan di Perangkat Ini** → izinkan notifikasi → **Uji Kirim**.
5. Notifikasi push iOS hanya jalan jika app sudah di-install ke layar utama.

## 10. Update aplikasi di kemudian hari
```bash
cd ~/REGAIN && git pull && cd infrastructure
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
docker compose -f docker-compose.prod.yml exec backend php artisan optimize
```
Di app akan muncul banner "Pembaruan tersedia" → tap **Perbarui**.
