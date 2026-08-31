# OTOHASAR — GitHub ile Otomatik Güncelleme

Bu akış şöyle çalışır:

```
Siz (Cursor) → git push → GitHub → Actions → SSH → sunucu
  → git pull → otohasar_php / otohasar_nginx restart → site güncellenir
```

Portainer stack’i yeniden deploy etmeye gerek yok. Kod zaten
`/mnt/1tb_disk/otohasar` dizininden volume ile okunuyor.

---

## 1) GitHub repo oluştur (bilgisayarınızda)

```bash
cd C:\Users\necip\Documents\otohasar
git init -b main
git add .
git commit -m "OTOHASAR ilk sürüm"
```

GitHub’da **private** bir repo oluşturun (ör. `otohasar`), sonra:

```bash
git remote add origin https://github.com/KULLANICI/otohasar.git
git push -u origin main
```

> Repo **private** olsun — `portainer-stack.yml` içinde DB şifreleri var.

---

## 2) Sunucuyu GitHub’a bağla (SSH, bir kez)

```bash
cd /mnt/1tb_disk/otohasar

# Henüz git yoksa:
git init -b main
git remote add origin git@github.com:KULLANICI/otohasar.git

# Deploy key (sunucudan GitHub’a okuma)
ssh-keygen -t ed25519 -f ~/.ssh/otohasar_github -N ""
cat ~/.ssh/otohasar_github.pub
```

`*.pub` çıktısını GitHub → Repo → **Settings → Deploy keys → Add** (Allow write: kapalı).

```bash
# SSH config
cat >> ~/.ssh/config << 'EOF'
Host github.com
  IdentityFile ~/.ssh/otohasar_github
  StrictHostKeyChecking accept-new
EOF

# Kodu çek
git fetch origin
git checkout -B main origin/main
# veya: git reset --hard origin/main

chmod +x scripts/*.sh
```

**Önemli:** `data/` klasörü git’te yok; MySQL ve uploads silinmez.

---

## 3) GitHub Actions Secrets

Repo → **Settings → Secrets and variables → Actions → New repository secret**

| Secret | Örnek |
|--------|--------|
| `SERVER_HOST` | Sunucu public IP veya domain |
| `SERVER_USER` | `tbserver` |
| `SERVER_SSH_KEY` | Sunucuya bağlanan **private** key (PEM tam metin) |
| `SERVER_SSH_PORT` | `22` (farklıysa) |

Actions’ın SSH key’i, sunucuda `~/.ssh/authorized_keys` içinde olmalı.

---

## 4) Test

1. Küçük bir değişiklik yapın (ör. README’ye satır)
2. Commit + push:
   ```bash
   git add .
   git commit -m "test: auto deploy"
   git push origin main
   ```
3. GitHub → **Actions** sekmesinde yeşil tik bekleyin
4. Siteyi yenileyin: https://otohasar.neciparmagan.net.tr

Manuel sunucu güncellemesi:
```bash
cd /mnt/1tb_disk/otohasar
./scripts/auto-deploy.sh main
```

---

## Siteye bağlanamıyorsanız (şimdi)

```bash
# Container’lar ayakta mı?
sudo docker ps | grep otohasar

# Port 4080 cevap veriyor mu?
curl -I http://127.0.0.1:4080

# PHP log
sudo docker logs otohasar_php --tail 30

# Restart
sudo docker restart otohasar_php otohasar_nginx
```

NPM forward: `http://127.0.0.1:4080`  
Cloudflare SSL: **Full** (NPM’de Let’s Encrypt varsa) veya **Flexible**

---

## Günlük kullanım

1. Cursor’da değişiklik yapılır
2. `git add` + `commit` + `push`
3. 30–60 sn sonra sunucu otomatik güncellenir

Başka bir şey yapmanıza gerek yok.
