# OTOHASAR — Hasar Dosya ve Süreç Takip Sistemi

Yetkili otomotiv servisi için hasar dosya takip sistemi. Masaüstünde Kanban pano, mobilde saha personeli için hızlı kamera/fotoğraf yükleme. PWA standardında.

**Canlı Demo:** https://otohasar.neciparmagan.net.tr

## Teknoloji

- **Backend:** PHP 8.2 (PDO, prepared statements)
- **Veritabanı:** MariaDB 10.11 (utf8mb4, InnoDB)
- **Frontend:** HTML5 + Vanilla CSS + Vanilla JS
- **PWA:** manifest.json + service worker (app-shell önbelleği; API asla önbelleğe alınmaz)
- **Container:** Docker Compose (nginx + php-fpm + mariadb)

## Hızlı Başlangıç (Docker)

```bash
cd otohasar
cp .env.example .env
docker compose up -d --build
```

Tarayıcıda açın: **http://localhost:8080**

Domain için reverse proxy ile `8080` portuna yönlendirme yapın.

## Üretim Kurulumu (Docker — port 4080)

Detaylı rehber: **[ILK-KURULUM.md](ILK-KURULUM.md)** | Portainer: **[PORTAINER.md](PORTAINER.md)**

```bash
sudo mkdir -p /mnt/1tb_disk/otohasar
# Dosyaları /mnt/1tb_disk/otohasar/ içine yükle (WinSCP)
cd /mnt/1tb_disk/otohasar
chmod +x scripts/*.sh
./scripts/docker-install.sh
```

Erişim: **http://SUNUCU_IP:4080** — `hasardanismandemo` / `1234`

## Manuel Kurulum

```bash
mysql -u root -p -e "CREATE DATABASE otohasar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p otohasar < database/schema.sql
php scripts/setup.php
php -S 0.0.0.0:8000 -t public
```

Tarayıcıda açın: **http://localhost:8000**

## Demo Hesaplar

Tüm hesapların şifresi: **1234**

| Kullanıcı Adı | Ad | Rol |
|---|---|---|
| `hasardanismandemo` | Ahmet Yılmaz | Hasar Danışmanı |
| `hasardanisman2demo` | Burak Şahin | Hasar Danışmanı |
| `yoneticidemo` | Elif Kaya | Servis Yöneticisi |
| `atolyedemo` | Mehmet Demir | Atölye Personeli |

## Domain Kurulumu

DNS A kaydını sunucu IP'sine yönlendirin, reverse proxy ile HTTPS sağlayın (Stack için port **4080**).

## API

| Method | Endpoint | Açıklama |
|---|---|---|
| POST | `/api/login.php` | Giriş |
| POST | `/api/create_file.php` | Yeni dosya |
| POST | `/api/upload.php` | Evrak yükleme |
| POST | `/api/status.php` | Durum değişikliği |
| GET | `/api/get_file.php?id=` | Dosya detayı |
| POST | `/api/delete_doc.php` | Evrak silme |
| GET | `/api/plate_search.php?q=` | Plaka arama |

## Güvenlik

PDO prepared statements, CSRF, bcrypt, MIME kontrolü, rol bazlı yetki, file_logs audit trail.
