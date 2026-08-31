# OTOHASAR — Docker ile Sunucu Kurulumu

Swarm gerekmez. Sadece **Docker + Docker Compose** yeterli.

---

## Sunucuda Yapılacaklar (3 adım)

### Adım 1 — Dizin oluştur

```bash
sudo mkdir -p /mnt/1tb_disk/otohasar
sudo chown -R $USER:$USER /mnt/1tb_disk/otohasar
```

### Adım 2 — Projeyi yükle

Tüm proje dosyalarını şuraya atın:

```
/mnt/1tb_disk/otohasar/
```

WinSCP / FileZilla ile bilgisayarınızdaki `otohasar` klasörünün **içeriğini** bu dizine sürükleyin.

Kontrol:

```bash
ls /mnt/1tb_disk/otohasar/
# Görmeli: docker-compose.prod.yml  Dockerfile  .env  public/  scripts/  ...
```

### Adım 3 — Docker ile başlat

```bash
cd /mnt/1tb_disk/otohasar
chmod +x scripts/*.sh
./scripts/docker-install.sh
```

İlk build 2-5 dakika sürebilir. Bittiğinde:

**http://SUNUCU_IP:4080** → `hasardanismandemo` / `1234`

---

## Hazır Dosyalar (sizin yapmanız gereken bir şey yok)

| Dosya | Ne işe yarar |
|-------|--------------|
| `.env` | Port 4080, DB şifreleri, domain — hazır |
| `docker-compose.prod.yml` | db + php + nginx |
| `Dockerfile` | PHP uygulaması |
| `Dockerfile.nginx` | Web sunucusu |
| `scripts/docker-install.sh` | Tek komut kurulum |

---

## Veriler (kalıcı)

```
/mnt/1tb_disk/otohasar/data/
├── mysql/     ← veritabanı
└── uploads/   ← evraklar
```

Container silinse bile veriler korunur.

---

## Güncelleme

Dosyaları yeniden yükledikten sonra:

```bash
cd /mnt/1tb_disk/otohasar
./scripts/docker-update.sh
```

---

## Yararlı Komutlar

```bash
cd /mnt/1tb_disk/otohasar

# Durum
docker compose -f docker-compose.prod.yml ps

# Loglar
docker compose -f docker-compose.prod.yml logs -f

# Durdur
docker compose -f docker-compose.prod.yml down

# Yeniden başlat
docker compose -f docker-compose.prod.yml up -d

# Tamamen sil (veriler kalır)
docker compose -f docker-compose.prod.yml down
```

---

## Reverse Proxy (sizin işiniz)

```
otohasar.neciparmagan.net.tr → localhost:4080
```

Caddy:
```
otohasar.neciparmagan.net.tr {
    reverse_proxy localhost:4080
}
```

---

## Şifreler

Uygulama demo: `hasardanismandemo` / `1234`

Veritabanı: `deploy/SIFRELER.txt`

---

## Sorun Giderme

**Docker yok:**
```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
# çıkış yap, tekrar gir
```

**Port meşgul:**
```bash
ss -tlnp | grep 4080
```

**DB hatası:**
```bash
docker compose -f docker-compose.prod.yml logs db
docker compose -f docker-compose.prod.yml logs php
```

**Sıfırdan DB (DİKKAT: veri silinir):**
```bash
docker compose -f docker-compose.prod.yml down
sudo rm -rf /mnt/1tb_disk/otohasar/data/mysql /mnt/1tb_disk/otohasar/data/.initialized
./scripts/docker-install.sh
```
