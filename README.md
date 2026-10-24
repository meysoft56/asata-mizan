# ASATA MİZAN PHP portalı

## Ürün planı ve ilerleme kaydı

- [Ürün kapsamı, kullanıcı/yetki matrisi, veri modeli, iş akışları ve MVP planı](./PROJECT_BLUEPRINT.md)
- [Yapılan, devam eden ve yapılacak işler / doğrulama kaydı](./PROJECT_STATUS.md)
- Kaynak master promptları yerel proje yedeğinde tutulur ve bu herkese açık depoya dahil edilmez.

## Sayfalar

- `index.php`: ana sayfa, ön alan hesaplayıcısı
- `?page=ilanlar`: arama ve il filtresi olan proje ilanları
- `?page=ilan&id=...`: ilan detayı ve iletişim talebi
- `?page=proje-ekle`: moderasyona gönderilen yeni proje başvurusu
- Proje başvurusu: malik/arsa sahibi, geliştirici veya emlak danışmanı rolü ve taşınmaz/ticari varsayımlar alınır; alanların tümü başvuru sahibi beyanı olarak işaretlenir. Yönetici bilgi isteyebilir, reddedebilir veya onaylı brief sürümü oluşturabilir; bilgi istenen başvuruyu yalnız sahibi düzenleyip yeniden gönderebilir.
- Yönetim paneli: onaylı brief'i yalnız kayıtlı mimar, mühendis veya müteahhit hesaplarına proje bazında atar. Uzman paneli, yalnız kendisine atanmış onaylı brief'in sınırlı alanlarını gösterir.
- `?page=fizibilite`: alan, maliyet, satış değeri, hedef kâr, hassasiyet senaryoları ve girdileri/sonuçları içeren CSV raporu
- `?page=serefiye`: kriter ağırlıklı bağımsız bölüm puanlama, 10.000 baz puan normalizasyonu ve CSV çıktısı
- Yönetim panelinde master prompttaki beş inşaat aşaması için yönetici tarafından güncellenen ilerleme kontrol listesi
- `?page=kayit` / `?page=uye-giris` / `?page=hesabim`: ayrı malik, geliştirici, emlak danışmanı, mimar, mühendis ve müteahhit çalışma alanları; hesap sahibi iletişim ve mesleki profil alanlarını güncelleyebilir
- `?page=giris` / `?page=admin`: yönetici girişi ve moderasyon paneli
- `?page=kurulum`: tek seferlik yönetici hesabı oluşturma

## Yerel çalıştırma

PHP 8.1 veya üzeri gereklidir. PHP `mbstring` eklentisi Türkçe metin arama ve doğrulaması için kullanılmaktadır.

```sh
php -S 127.0.0.1:8000 -t .
```

Ana sayfa: `http://127.0.0.1:8000/`

## Tam erişimli yerel demo

Tüm ziyaretçilerin yönetici işlemlerini deneyebilmesi için ayrı ve yalnızca loopback'e bağlı demo sunucusu çalıştırılabilir. Bu mod yalnızca `ASATA_DEMO_FULL_ACCESS=1`, `127.0.0.1`/`localhost` isteği, loopback istemci adresi ve sabit `/home/pardus/Masaüstü/asata-demo-data` veri dizini birlikte eşleştiğinde etkinleşir. Normal yerel sunucu, üretim sunucusu ve başka veri dizinleri etkilenmez.

```sh
ASATA_DEMO_FULL_ACCESS=1 \
ASATA_DATA_DIR='/home/pardus/Masaüstü/asata-demo-data' \
php -S 127.0.0.1:8002 -t '/home/pardus/Masaüstü/web'
```

Demo ilk açılışta sentetik örnek ilanları gösterir ve yeni kayıtlar ayrı demo veri dizinine yazılır. Bu adrese sadece güvenilen yerel kullanıcılar erişmelidir; tam erişim modu gerçek kişisel/ticari veriyle veya internete açık bir sunucuda kullanılmamalıdır.

## Hosting'e yayınlama

SFTP veya hosting sağlayıcısının web dosya yöneticisini kullanın; FTP şifresi şifrelenmeden aktarılabilir. Yüklemeden önce erişim bilgilerini döndürün ve parolaları kaynak dosyalara, komut geçmişine veya bu belgeye yazmayın.

PHP 8.1+ ve `mbstring` etkin bir hosting gerekir. `public_html/` içine `index.php`, `.htaccess`, `inc/` ve `assets/` yükleyin. Eski `index.html` kalabilir; `.htaccess` PHP giriş dosyasını önce seçer. Yerel proje/master prompt dosyalarını ve yerel `data/` klasörünü web köküne yüklemeyin. Uygulama verileri web kökünün dışında `asata-data/` klasörüne yazılır; hosting PHP sürecinin bu klasörü oluşturma/yazma izni olmalıdır.

İlk açılış sonrasında SSL/HTTPS'i etkinleştirin ve `ASATA_ADMIN_SETUP_KEY` ortam değişkenini hosting panelinden ayarlayıp yönetici hesabını kurun. Ortam değişkeni ayarlayamıyorsanız herkese açık kurulum anahtarını dosyaya koymayın; hosting desteğinden sunucu ortam değişkeni tanımlamasını isteyin. Yayından sonra anahtarı kaldırın. Yönetici parolası olarak daha önce mesajlaşmada paylaşılan parolayı kullanmayın.

## İlk yönetici hesabı

İnternete açık sunucuda uygulamayı yayınlamadan önce web sunucusuna `ASATA_ADMIN_SETUP_KEY` adlı güçlü, rastgele bir ortam değişkeni ekleyin. Kurulum ekranını açıp aynı anahtarla yönetici hesabını oluşturun. Anahtar yoksa veya admin hesabı varsa kurulum yapılamaz. Yönetici parolası `password_hash` ile saklanır.

Linux shell örneği:

```sh
export ASATA_ADMIN_SETUP_KEY='uzun-ve-rastgele-bir-kurulum-anahtari'
php -S 127.0.0.1:8000 -t .
```

Üretimde PHP-FPM/Apache yapılandırmasında ortam değişkenini web uygulama sürecine verin; anahtarı kaynak koda veya web köküne koymayın. Kurulum tamamlanınca ortam değişkenini kaldırın. HTTPS kullanın.

## Veri ve yedek

Başvurular, ilanlar, üye profilleri ve yönetici kaydı varsayılan olarak web kökünün dışındaki `../asata-data/` klasöründe JSON dosyalarında tutulur. PHP sürecinin bu klasöre yazma izni olmalıdır. Başka bir konum kullanmak için `ASATA_DATA_DIR` ortam değişkenini tanımlayın. Yedekleme için bu veri klasörünü erişim kontrollü bir konuma kopyalayın.

Yerel `data/` klasörü ve kaynak prompt/strateji yedekleri `.gitignore` ile dışlanır; bunlar herkese açık depoya gönderilmemelidir.

Bu ilk sürüm dosya tabanlı, tek sunucu kullanımına yöneliktir. Çoklu sunucu, yoğun trafik veya kişisel veri saklama yükümlülükleri için veritabanı, erişim denetimi, log/retention politikası ve KVKK süreçleri ayrıca tasarlanmalıdır. Formlarda tapu/kimlik belgesi yüklemesi yoktur.

## Güvenlik / doğruluk notları

- Form istekleri CSRF token ile korunur; çıktı HTML'de escape edilir.
- Tam erişimli demo modu yalnızca sabit demo veri dizininde ve loopback bağlantısında çalışır; gerçek/üretim verisi için etkinleştirilmemelidir.
- İlan başvuruları admin onayı olmadan yayınlanmaz.
- Başvuru inceleme/onayı, ilanın yayın durumundan ayrıdır. Yayınlama için önce onaylı brief gerekir. Yönetici onayı kullanıcı beyanının resmi mülkiyet/imar/teknik doğrulaması değildir.
- Uzman erişimi varsayılan kapalıdır; atama yalnızca proje bazında yönetici tarafından yapılır. Uzman ekranı başvuru sahibinin iletişim ve ticari bütçe verilerini göstermez.
- Başvuru alanlarının kaynak bilgisi, başvuru sahibi beyanı ve tarih olarak tutulur; dosya yükleme ve resmi belge doğrulaması bu prototipte yoktur.
- Üye rolleri farklı çalışma alanları gösterir; üyelik ve mesleki kayıt bilgileri doğrulanmış kimlik/yetki belgesi değildir.
- Üye profil güncellemesi yalnızca oturum açmış kullanıcının kendi hesabını değiştirir; e-posta adresi başka bir hesapta kayıtlıysa güncelleme reddedilir. Rol ve parola bu formdan değiştirilemez.
- Örnek ilanlar açıkça örnek olarak işaretlenmiştir; gerçek proje/verisi değildir.
- Alan hesaplayıcısı ön fizibilitedir; imar hakkı veya hukuki/mühendislik görüşü değildir.
- Fizibilite girdileri kullanıcı varsayımlarıdır; emsal dışı hak, maliyet, satış değeri veya paylaşım oranı resmî olarak tespit edilmez. Metraj katsayıları proje mühendisi girdisi olmadan üretilmez.
- Fizibilite CSV raporu, hesaplama anındaki girdileri, gösterge sonuçlarını, metraj varsayımlarını ve senaryo hassasiyetini dışa aktarır; resmi rapor veya değerleme belgesi değildir.
- Şerefiye aracı kullanıcı ağırlıklı skorlamadır; tam Saaty AHP tutarlılık matrisi değildir ve gerçek değerleme, mülkiyet payı veya taraf mutabakatı yerine geçmez. CSV çıktı, yalnızca kullanıcı girdilerinin hesaplama dökümüdür.
- Aşama takip ekranı yalnızca durum kaydıdır; tapu devri, escrow, sigorta, hakediş, bağımsız teknik tespit veya hukuki onay sunmaz. Promptta yer alan oranlar örnek varsayımdır.
