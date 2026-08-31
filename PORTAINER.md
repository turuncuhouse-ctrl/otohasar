# OTOHASAR — Portainer Kurulum Rehberi

Portainer'da stack oluşturmak için adım adım rehber.

---

## Ön hazırlık (SSH — bir kez)

### 1. Dizin oluştur ve dosyaları yükle

WinSCP ile tüm proje dosyalarını şuraya atın:

```
/mnt/1tb_disk/otohasar/
```

### 2. Docker imajlarını build et

Portainer web editörü `build` desteklemez; imajları önce SSH ile oluşturun:

```bash
cd /mnt/1tb_disk/otohasar
chmod +x scripts/*.sh
./scripts/portainer-build.sh
```

Başarılı olunca şunu görmelisiniz:
```
otohasar-php:latest
otohasar-nginx:latest
```

---

## Portainer'da Stack Oluşturma

### Adım 1 — Stacks menüsü

Portainer → sol menü → **Stacks** → **+ Add stack**

### Adım 2 — Stack bilgileri

| Alan | Değer |
|------|-------|
| **Name** | `otohasar` |
| **Build method** | **Web editor** |

### Adım 3 — Stack kodunu yapıştır

**Web editor** kutusuna `portainer-stack.yml` dosyasının **tüm içeriğini** yapıştırın.

Dosya projede hazır: `/mnt/1tb_disk/otohasar/portainer-stack.yml`

SSH ile kopyalamak için:
```bash
cat /mnt/1tb_disk/otohasar/portainer-stack.yml
```
Çıktıyı kopyalayıp Portainer'a yapıştırın.

### Adım 4 — Deploy

Altta **Deploy the stack** butonuna tıklayın.

### Adım 5 — Kontrol

Portainer → **Containers** — şunlar **running** olmalı:

| Container | Durum |
|-----------|-------|
| `otohasar_db` | running |
| `otohasar_php` | running |
| `otohasar_nginx` | running |

Tarayıcı: **http://SUNUCU_IP:4080**  
Giriş: `hasardanismandemo` / `1234`

---

## Stack Kodu (kopyala-yapıştır)

Aşağıdaki kod `portainer-stack.yml` ile aynıdır:

```yaml
services:
  db:
    image: mariadb:10.11
    container_name: otohasar_db
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ySfnUpvGBqPbc1Cs2TW7NuJxw69R
      MYSQL_DATABASE: otohasar
      MYSQL_USER: otohasar
      MYSQL_PASSWORD: WMLn3djz6QGq1VyFE2N5glDK
    volumes:
      - /mnt/1tb_disk/otohasar/data/mysql:/var/lib/mysql
    networks:
      - otohasar
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 40s

  php:
    image: otohasar-php:latest
    container_name: otohasar_php
    restart: unless-stopped
    environment:
      DB_HOST: db
      DB_PORT: "3306"
      DB_NAME: otohasar
      DB_USER: otohasar
      DB_PASSWORD: WMLn3djz6QGq1VyFE2N5glDK
      APP_URL: https://otohasar.neciparmagan.net.tr
    volumes:
      - /mnt/1tb_disk/otohasar/data/uploads:/var/www/public/uploads
      - /mnt/1tb_disk/otohasar/data:/var/www/data
    networks:
      - otohasar
    depends_on:
      db:
        condition: service_healthy

  nginx:
    image: otohasar-nginx:latest
    container_name: otohasar_nginx
    restart: unless-stopped
    ports:
      - "4080:80"
    volumes:
      - /mnt/1tb_disk/otohasar/data/uploads:/var/www/public/uploads:ro
    networks:
      - otohasar
    depends_on:
      - php

networks:
  otohasar:
    driver: bridge
```

---

## Güncelleme (kod değişince)

1. WinSCP ile yeni dosyaları `/mnt/1tb_disk/otohasar/` üzerine yazın
2. SSH:
   ```bash
   cd /mnt/1tb_disk/otohasar
   ./scripts/portainer-build.sh
   ```
3. Portainer → Stacks → `otohasar` → **Update the stack** → tekrar **Deploy**

Veya Portainer'da sadece container'ları yeniden başlatın:
- `otohasar_php` → **Recreate**
- `otohasar_nginx` → **Recreate**

---

## Loglar (Portainer'dan)

Portainer → **Containers** → container adına tıkla → **Logs**

| Container | Ne zaman bakılır |
|-----------|------------------|
| `otohasar_db` | Veritabanı hatası |
| `otohasar_php` | Uygulama / DB bağlantı hatası |
| `otohasar_nginx` | 502 / sayfa açılmıyor |

---

## Sık Karşılaşılan Hatalar

**`otohasar-php:latest` not found**  
→ `./scripts/portainer-build.sh` çalıştırılmamış. SSH ile build edin.

**Port 4080 already in use**  
→ Başka bir servis portu kullanıyor. Stack'te `"4081:80"` yapın veya diğer servisi durdurun.

**502 Bad Gateway**  
→ `otohasar_php` henüz hazır değil. 1-2 dk bekleyin, php loglarına bakın.

**Veritabanı boş / giriş olmuyor**  
→ php container loglarında "Database initialized" mesajını kontrol edin.  
Sıfırlamak için:
```bash
docker stop otohasar_php otohasar_db
sudo rm -rf /mnt/1tb_disk/otohasar/data/mysql /mnt/1tb_disk/otohasar/data/.initialized
# Portainer'dan stack'i yeniden deploy edin
```

---

## Reverse Proxy (HTTPS)

Caddy / Nginx ile:
```
otohasar.neciparmagan.net.tr → localhost:4080
```

---

## Özet

```
1. Dosyaları /mnt/1tb_disk/otohasar/ içine at
2. SSH: ./scripts/portainer-build.sh
3. Portainer → Stacks → Add stack
4. portainer-stack.yml içeriğini yapıştır
5. Deploy the stack
6. http://SUNUCU_IP:4080
```
