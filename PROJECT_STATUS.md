# ASATA MİZAN — Devam ve görev durum listesi

**Son güncelleme:** 2026-10-24  
**Çalışma alanı:** `Masaüstü/web`  
**Yayın durumu:** Normal site yerel geliştirme modunda; ayrıca sentetik veriye ayrılmış tam erişimli demo `http://127.0.0.1:8002/` adresinde çalışıyor. Hosting'e yayın yapılmadı.

## Anlık görev panosu

| Durum | İş | Sonuç / sonraki adım |
|---|---|---|
| ✅ Tamamlandı | Yeni master promptu inceleme | Ürün kapsamı, roller/yetkiler, veri modeli, uçtan uca akışlar ve MVP fazları çıkarıldı; kaynak bölüm/satır referansları [PROJECT_BLUEPRINT.md](./PROJECT_BLUEPRINT.md) içinde |
| ✅ Tamamlandı | Mevcut uygulama karşılaştırması | Tamamlanan, kısmi ve eksik alanlar blueprint'teki karşılaştırma tablosuna yazıldı |
| ✅ Tamamlandı | Master prompt yedeği | Kaynak dosya yerel proje yedeğinde korundu; herkese açık depodan hariç tutuldu |
| ✅ Tamamlandı | Üye profil düzenleme | Kullanıcı kendi iletişim/mesleki profilini güncelleyebiliyor; hesap rolü ve parola değişmiyor |
| ✅ Tamamlandı | Profil doğrulaması | PHP lint ve geçici veri dizininde HTTP uçtan uca test: başarılı güncelleme kalıcı, başka hesaba ait e-posta reddediliyor ve mevcut profil korunuyor |
| ✅ Tamamlandı | Tam erişimli yerel demo modu | Sadece `ASATA_DEMO_FULL_ACCESS=1` + loopback istek + ayrı sabit `asata-demo-data` dizini eşleşince admin işlemlerini açar |
| ✅ Tamamlandı | Tam erişimli demo sunucusu ve güvenlik sınırları | Demo yönetim paneli 200; normal sunucu ve sahte Host başlığı 302 giriş yönlendirmesi; demo ayrı veri dizini ve loopback bind kullanıyor |
| ✅ Tamamlandı | Profil e-posta çakışması hatası | Testte bulunan erken döngü çıkışı giderildi; benzersizlik kontrolü tüm hesaplar tarandıktan sonra kayıt güncelliyor |
| ✅ Tamamlandı | Fizibilite CSV raporu | Girdiler, özet, metraj, senaryolar ve sınırlamalar indirilebilir; yerel HTTP yanıtı doğrulandı |
| ✅ Tamamlandı | Şerefiye CSV raporu | 10.000 baz puanlık endeks ve kullanıcı girdisi dışa aktarılabilir |
| ✅ Tamamlandı | Rol tabanlı prototip ve proje akışı | Altı temel üyelik rolü, ilan başvurusu, admin moderasyonu, üye dashboardu ve aşama görünümü mevcut |
| ✅ Tamamlandı | Proje işlem geçmişi ve geri alınabilir arşiv | Başvuru, yayın/gizleme, arşiv/geri yükleme ve aşama güncellemeleri aktör/zaman bilgisiyle kaydedilir; silme yerine arşivleme kullanılır |
| ✅ Tamamlandı | VisionForge prompt yedeği | Kullanıcı kaynak promptu yerel web yedeğinde korundu; herkese açık depodan hariç tutuldu |
| ✅ Tamamlandı | Başvuru → onaylı uzman brifi akış tasarımı | Malik/geliştirici/emlak danışmanı girdisinin kaynaklı onaylı sürüm olarak derlenmesi; atanmış mimar/mühendis/müteahhit ekranlarına kontrollü aktarılması ve teklif/keşif/metrajla bağlanması blueprint'e eklendi |
| ✅ Tamamlandı | VisionForge prompt analizi | Role göre giriş, kaynak/doğrulama durumları, brief/onay geçitleri, uzman teslimi ve miktar kuralları kaynak bölümleriyle çıkarıldı |
| ✅ Tamamlandı | Başvuru ve onaylı brif ilk dikey dilimi | Rol bazlı başvuru, beyan kaynağı, eksik bilgi, malik revizyonu, onaylı sürüm geçmişi ve seçilen uzmanlara sınırlı erişim uygulandı |
| 🟡 Kısmi | MVP/Faz 0 onayı | Belge hazırlandı; pilot kullanıcı, organizasyon, başarı ölçütü ve veri sınıfları henüz kullanıcı/paydaş onayı almadı |
| ⏳ Yapılacak | Güvenli temel ve tenant yetkisi | Tenant izolasyonu, RBAC+ABAC, davet/üyelik, MFA, audit ve olumsuz erişim testleri tasarlanıp uygulanmalı |
| ⏳ Yapılacak | Malik, temsil ve taşınmaz | Malik/vekalet/hisse/parsel ilişkileri ve kaynak/doğrulama durumlu kayıt modeli eklenmeli |
| ⏳ Yapılacak | Belge kasası ve belge talebi | Özel dosya depolama, rol/proje erişim kapsamı, belge sürümü, kötü amaçlı dosya kontrolü ve retention politikası gerekli |
| ⏳ Yapılacak | Sürümlü fizibilite ve teklif/karar | Varsayımlar/formül versiyonları, projeye bağlı senaryolar, teklif revizyonu, onay ve karar audit izi gerekli |
| 🟡 Kısmi | Başvuru formu ve onaylı proje brifi | Malik/geliştirici/emlakçı alanları, beyan kaynağı/tarihi, admin eksik bilgi/ret/onayı, malikin düzeltme ve yeniden gönderimi, sabit onay snapshot geçmişi uygulandı; belge dosyası ve resmi doğrulama yok |
| 🟡 Kısmi | Proje bazında uzman paylaşımı | Yönetici yalnız kayıtlı mimar/mühendis/müteahhit hesaplarını projeye atar; uzman yalnız atanmış güncel onaylı brifi ve sınırlı alanları görür. Ayrıntılı alan sınıfları, tenant izolasyonu ve gerçek belge izinleri sonraki faz |
| ⏳ Yapılacak | Onaylı briften teklif/keşif temeli | Şu an onaylı brief yalnız kavramsal temel; teklif/metraj taslağı, çizim/formül revizyon bağı ve değişiklikte yeniden inceleme uygulamada yok |
| ⏳ Yapılacak | Görev ve kanıtlı proje yürütme | Atanmış görev, belge/kanıt, onay kapıları, saha, kalite/İSG ve maliyet gerçekleşme iş akışları gerekli |
| ⏳ Yapılacak | İlişkisel veri tabanı ve operasyon | JSON prototipinden güvenli, tenant uyumlu DB migrasyonu; audit, yedek/restore testi ve işletim hazırlığı |
| ⏸️ Beklemede | Üretime yayın | Hosting erişimi ve güvenli yayın yöntemi belirlenmedi; daha önce paylaşılan FTP parolası kullanılmamalı ve döndürülmeli; SFTP/FTPS veya dosya yöneticisi tercih edilmeli |
| ⏸️ Faz dışı | İleri modüller | Tam muhasebe/bordro, BIM/CAD, tüm kamu API'leri, otomatik ruhsat/ödeme, AI otomasyonu ve native mobil MVP sonrasına bırakıldı |

## Şu an tamamlanan değişiklikler

- [index.php](./index.php): fizibilite CSV dışa aktarımı ve hesap sonucu bağlantısı.
- [index.php](./index.php): hesap sahibinin CSRF korumalı profil güncellemesi; e-posta çakışması halinde kayıt değişmeden reddedilir.
- [inc/app.php](./inc/app.php): profil alanı sınır ve e-posta doğrulaması.
- [README.md](./README.md): fizibilite CSV ve profil düzenleme davranışının belgelenmesi.
- [inc/app.php](./inc/app.php): tam erişimli demo koşulunun loopback ve ayrı veri klasörüne sabitlenmesi.
- [index.php](./index.php): proje başvurusu, yayın/gizleme, arşiv/geri yükleme ve aşama değişiklikleri için aktörlü/zamanlı işlem geçmişi; kalıcı silme kaldırıldı.
- [index.php](./index.php) / [inc/app.php](./inc/app.php): role göre başvuru alanları, beyan kaynağı metadata'sı, admin inceleme durumu, onaylı brief snapshot'ı ve proje bazında uzman ataması.
- [index.php](./index.php): eksik bilgi isteği üzerine yalnız başvuru sahibinin düzenleyip yeniden gönderebildiği form; önceki onaylı sürümler korunur ve uzman erişimi yeni inceleme boyunca kapatılır.
- [index.php](./index.php): admin incelemesi onaylanmadan ilan yayınlanamaz; onay ve ilan yayın durumu birbirinden ayrıdır.
- [PROJECT_BLUEPRINT.md](./PROJECT_BLUEPRINT.md): VisionForge promptundan başvuru verisi → onaylı proje brifi → atanmış uzman ekranları → teklif/keşif bağlantı akışı eklendi.
- [PROJECT_BLUEPRINT.md](./PROJECT_BLUEPRINT.md): bu iterasyondaki ürün/mimari kapsam ve uygulama karşılaştırması.
- Master prompt kaynak dosyaları bu yerel klasörde bulunur; herkese açık depoya gönderilmez.

## Doğrulama kaydı

- `php -l` ile `index.php`, `inc/app.php`, `inc/header.php`, `inc/footer.php`: temiz.
- Profil HTTP entegrasyon testi: geçici `ASATA_DATA_DIR` ile hesap kaydı, kendi profil güncellemesi ve başka kullanıcının e-postasına çakışma reddi başarılı.
- Fizibilite CSV HTTP isteği: 200; girdiler, özet, metraj ve üç hassasiyet senaryosu içeriyor.
- Tam erişimli yerel demo: `127.0.0.1:8002` ana sayfası ve yönetim paneli 200; `127.0.0.1:8000` yönetim paneli ve `example.test` Host başlıklı demo isteği 302 ile girişe yönlendirildi.
- Proje işlem akışı entegrasyon testi: sentetik geçici veriyle yayınla → yayından kaldır → arşivle → geri yükle ve aşama tamamlandı geçişi başarılı; her event yönetici adı/zaman/durum değişimiyle kaydedildi.
- Başvuru/uzman görünürlüğü uçtan uca testi: kaynak beyanı → admin eksik bilgi → malik düzeltme/yeniden gönderim → onaylı sürüm → belirli uzmanlara atama → atanmamış uzman erişiminin reddi → yeni inceleme isteğinde erişimin kaldırılması; başarılı. İletişim ve ticari bütçe alanları uzman görünümünde yok.
- Başvuru ve yönetim ekranları yerel HTTP smoke testi: 200.
- VisionForge prompt yedeği kaynakla `cmp` karşılaştırmasında aynı; web kopyası 694 satır.
- Testler gerçek kullanıcı verisini değiştirmemek için geçici dizinde çalıştırıldı.

## Sıradaki önerilen iş

Tam erişimli demo yalnızca yerel/sentetik deneme içindir; `ASATA_DEMO_FULL_ACCESS=1` ayarını gerçek verili veya internete açık sunucuda kullanma. VisionForge akışının uygulama adımına geçmeden önce Faz 0'da form alanları, doğrulayıcı/onarım rolleri, hangi verinin mimar/mühendis/müteahhide açılacağı, onay kriterleri ve teklif/keşif çıktı sahipleri netleştirilmeli. Tenant, malik doğrulama ve belge saklama güvenlik kararları verilmeden gerçek tapu/kimlik belgesi veya üretim verisi toplama. Ardından MVP çıkış kapılarını sırayla tamamla.

## Veri ve gizlilik

Canlı üye/proje verileri varsayılan olarak bu web klasöründe değil, `ASATA_DATA_DIR`/`asata-data` konumunda tutulur. Master prompt ve ürün belgeleri proje yedeğidir; parola, kurulum anahtarı, kişisel veri veya canlı veri dosyası web köküne kopyalanmamalıdır.
