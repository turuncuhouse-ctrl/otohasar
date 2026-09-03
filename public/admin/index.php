<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = require_auth();
if (!is_admin_user($currentUser)) {
    http_response_code(403);
    echo 'Yetkisiz erişim';
    exit;
}

$pageTitle = 'Yönetim';
$activeNav = 'admin';

$pdo = db();
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$insCount = 0;
$statusCount = 0;
$catCount = 0;
$groupCount = 0;
try {
    $insCount = (int) $pdo->query('SELECT COUNT(*) FROM insurance_companies WHERE is_active=1')->fetchColumn();
    $statusCount = (int) $pdo->query('SELECT COUNT(*) FROM app_statuses WHERE is_active=1')->fetchColumn();
    $catCount = (int) $pdo->query('SELECT COUNT(*) FROM app_categories WHERE is_active=1')->fetchColumn();
    $groupCount = (int) $pdo->query('SELECT COUNT(*) FROM user_groups WHERE is_active=1')->fetchColumn();
} catch (Throwable $e) {
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Yönetim Paneli</h1>
</div>

<p class="dash-sub" style="margin-bottom:1.25rem">
    Sistem ayarları: kullanıcılar, grup yetkileri, prim, evrak kategorileri, dosya durumları ve sigorta şirketleri.
    <br>Önce <a href="/admin/groups.php">Kullanıcı Grupları</a> ve <a href="/admin/users.php">Kullanıcılar</a>ı tanımlayın.
    <br>Şifrenizi <a href="/profile.php">Hesabım</a> sayfasından değiştirebilirsiniz.
</p>

<div class="admin-grid">
    <a class="admin-card" href="/admin/users.php">
        <h2>Kullanıcılar</h2>
        <p>Kullanıcı adı, şifre ve grup ataması</p>
        <span class="admin-stat"><?= $userCount ?> kullanıcı</span>
    </a>
    <a class="admin-card" href="/admin/groups.php">
        <h2>Kullanıcı Grupları</h2>
        <p>Grup ekleyin ve görünürlük / işlem izinlerini ayarlayın</p>
        <span class="admin-stat"><?= $groupCount ?> grup</span>
    </a>
    <a class="admin-card" href="/admin/prim.php">
        <h2>Prim Ayarları</h2>
        <p>Süre penceresi, yüzde veya sabit prim hesabı</p>
        <span class="admin-stat">Yapılandır</span>
    </a>
    <a class="admin-card" href="/admin/tour.php">
        <h2>Tanıtım Sunumu</h2>
        <p>Sistemin işleyişini anlatan slaytları düzenleyin</p>
        <span class="admin-stat">İçerik</span>
    </a>
    <a class="admin-card" href="/admin/statuses.php">
        <h2>Araç / Dosya Durumları</h2>
        <p>Kanban durumlarını ekleyin veya düzenleyin</p>
        <span class="admin-stat"><?= $statusCount ?> durum</span>
    </a>
    <a class="admin-card" href="/admin/categories.php">
        <h2>Evrak Kategorileri</h2>
        <p>İstenen evrak türlerini belirleyin ve kapak formuna bağlayın</p>
        <span class="admin-stat"><?= $catCount ?> kategori</span>
    </a>
    <a class="admin-card" href="/admin/cover_form.php">
        <h2>Kapak Formu</h2>
        <p>Yazdırılan forma alan ekleyin, çıkarın ve düzenleyin</p>
        <span class="admin-stat">Özelleştir</span>
    </a>
    <a class="admin-card" href="/admin/insurance.php">
        <h2>Sigorta Şirketleri</h2>
        <p>Anlaşmalı şirketler, işçilik/parça iskontosu ve serbest başlıklı form PDF şablonları</p>
        <span class="admin-stat"><?= $insCount ?> aktif</span>
    </a>
    <a class="admin-card" href="/admin/whatsapp.php">
        <h2>WhatsApp Şablonları</h2>
        <p>Durum/evrak mesajları ve kendi WhatsApp şablonlarınız</p>
        <span class="admin-stat">Özelleştir</span>
    </a>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
