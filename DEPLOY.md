# OTOHASAR — Docker Stack Kurulum Rehberi

Sunucu dizini: `/mnt/1tb_disk/otohasar`  
Port: **4080**  
Domain: `https://otohasar.neciparmagan.net.tr`

---

## Dizin Yapısı (sunucuda otomatik oluşur)

```
/mnt/1tb_disk/otohasar/
├── app/              ← Git repo (kod burada)
├── data/
│   ├── mysql/        ← Veritabanı (kalıcı)
│   ├── uploads/      ← Yüklenen dosyalar (kalıcı)
│   └── .initialized  ← İlk kurulum işareti
└── env/
    └── .env          ← Şifreler ve ayarlar
```

---

## 1. İlk Kurulum (Sunucuda)

```bash
# Projeyi sunucuya kopyalayın veya doğrudan klonlayın
sudo mkdir -p /mnt/1tb_disk/otohasar
sudo chown -R $USER:$USER /mnt/1tb_disk/otohasar

# Geçici: repo'yu klonla (GitHub kurulumundan sonra otomatik olacak)
git clone https://github.com/KULLANICI/otohasar.git /mnt/1tb_disk/otohasar/app

cd /mnt/1tb_disk/otohasar/app
chmod +x scripts/*.sh

# Swarm + dizinleri hazırla
./scripts/stack-init.sh

# Şifreleri düzenle
nano /mnt/1tb_disk/otohasar/env/.env

# Build + deploy
./scripts/stack-deploy.sh
```

Tarayıcı: **http://SUNUCU_IP:4080**

---

## 2. Reverse Proxy (HTTPS)

Caddy örneği:

```
otohasar.neciparmagan.net.tr {
    reverse_proxy localhost:4080
}
```

Nginx örneği:

```nginx
server {
    listen 443 ssl http2;
    server_name otohasar.neciparmagan.net.tr;
    # ssl_certificate ... (certbot)

    location / {
        proxy_pass http://127.0.0.1:4080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        client_max_body_size 12M;
    }
}
```

---

## 3. GitHub ile Otomatik Güncelleme

Her `main` branch push'unda sunucu otomatik güncellenir.

### Adım A — GitHub Repo oluştur

```bash
cd /mnt/1tb_disk/otohasar/app
git init
git add .
git commit -m "Initial OTOHASAR"
git remote add origin https://github.com/KULLANICI/otohasar.git
git push -u origin main
```

### Adım B — GitHub Secrets ekle

Repo → **Settings → Secrets and variables → Actions**:

| Secret | Değer |
|--------|-------|
| `SERVER_HOST` | Sunucu IP veya domain |
| `SERVER_USER` | SSH kullanıcı adı (ör. `root` veya `deploy`) |
| `SERVER_SSH_KEY` | Sunucuya erişen private key (PEM) |
| `SERVER_SSH_PORT` | SSH portu (varsayılan 22) |

### Adım C — Sunucuda deploy key / SSH

Sunucuda git pull için SSH key veya HTTPS token ayarlayın:

```bash
# Sunucuda deploy key oluştur (opsiyonel)
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N ""
cat ~/.ssh/github_deploy.pub   # GitHub repo → Deploy keys'e ekle

cd /mnt/1tb_disk/otohasar/app
git remote set-url origin git@github.com:KULLANICI/otohasar.git
```

### Adım D — Test

```bash
# Lokalde değişiklik yap → push
git add .
git commit -m "Güncelleme"
git push origin main
```

GitHub Actions sekmesinden deploy'u izleyin. Başarılı olunca sunucu otomatik güncellenir.

---

## 4. Manuel Güncelleme

```bash
cd /mnt/1tb_disk/otohasar/app
git pull
./scripts/stack-deploy.sh
```

---

## 5. Yararlı Komutlar

```bash
# Servis durumu
docker stack services otohasar

# Loglar
docker service logs -f otohasar_nginx
docker service logs -f otohasar_php
docker service logs -f otohasar_db

# Stack kaldır (veriler silinmez)
docker stack rm otohasar

# Yeniden deploy
./scripts/stack-deploy.sh
```

---

## 6. Demo Hesaplar

Şifre: **1234**

| Kullanıcı | Rol |
|-----------|-----|
| `admin` | Sistem Admin |

---

## Sorun Giderme

**Port 4080 kapalı:** `ss -tlnp | grep 4080` — firewall'da açın: `ufw allow 4080/tcp`

**DB bağlanamıyor:** `docker service logs otohasar_db` — mysql dizini izinlerini kontrol edin

**İlk kurulum tekrar çalışsın:** `rm /mnt/1tb_disk/otohasar/data/.initialized` ve stack'i yeniden başlatın (mysql verisini de silmeniz gerekir)
