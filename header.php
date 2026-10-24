<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#101714">
  <meta name="description" content="Kat karşılığı geliştirme ve kentsel dönüşüm projeleri için şeffaf ön fizibilite ve ilan portalı.">
  <title><?= e($title) ?> — ASATA MİZAN</title>
  <link rel="stylesheet" href="assets/site.css">
</head>
<body>
<header class="topbar">
  <div class="wrap nav">
    <a class="brand" href="?page=home" aria-label="ASATA MİZAN ana sayfa"><span class="brand-mark">M</span><span class="brand-name">ASATA MİZAN<small>GAYRİMENKUL GELİŞTİRME PLATFORMU</small></span></a>
    <nav class="nav-links" aria-label="Ana menü">
      <a href="?page=ilanlar">İlanlar</a><a href="?page=fizibilite">Fizibilite</a><a href="?page=serefiye">Şerefiye</a><a href="?page=proje-ekle">Proje ekle</a><a href="?page=kayit">Mesleki hesap</a><a href="?page=home#platform">Platform</a>
    </nav>
    <div class="nav-actions">
      <?php if (is_admin()): ?><a class="login" href="?page=admin">Yönetim paneli</a><form method="post" action="?page=cikis" class="logout-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="button button-outline" type="submit">Çıkış</button></form><?php elseif ($member): ?><a class="login" href="?page=hesabim"><?= e($member['name']) ?></a><form method="post" action="?page=cikis" class="logout-form"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="button button-outline" type="submit">Çıkış</button></form><?php else: ?><a class="login" href="?page=uye-giris">Giriş</a><?php endif; ?>
      <a class="button button-lime" href="?page=proje-ekle">Proje başvurusu <span aria-hidden="true">↗</span></a>
    </div>
  </div>
</header>
