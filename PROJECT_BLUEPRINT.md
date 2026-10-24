# ASATA MİZAN — Ürün kapsamı ve MVP blueprint'i

Bu belge, proje için sağlanan ürün vizyonunu mevcut PHP uygulamasının gerçek kapsamıyla karşılaştırır. Master prompt geniş bir gayrimenkul geliştirme ve inşaat işletim sistemi tarif eder; mevcut uygulama bunun erken bir web prototipidir. Birbirleriyle karıştırılmamalıdır. Kaynak prompt dosyaları yerel proje yedeğinde tutulur ve herkese açık depoya dahil edilmez.

## 1. Ürün hedefi ve sınırlar

### Vizyon

Gayrimenkul geliştirme yaşam döngüsündeki kişileri, taşınmazları, kaynak belgeleri, kararları, varsayımları, teklifleri ve proje yürütmesini ortak bir izlenebilir süreçte buluşturmak. Her hesaplama ve karar; kaynağı, tarihi, sorumlusu, revizyonu ve güven düzeyiyle anlaşılabilir olmalıdır. Yapay zekâ/otomasyon ileride karar desteği sunabilir, karar veya profesyonel onay yerine geçemez.

### Ürün ilkeleri

- Kiracı/organizasyon, proje, taşınmaz, belge ve işlem kapsamı açıkça ayrılır.
- En az yetki ilkesi uygulanır; kullanıcı yalnızca atanmış olduğu bilgi ve eylemlere erişir.
- Doğrulanmış belge, kullanıcı beyanı, varsayım ve hesaplanan gösterge birbirinden ayrılır.
- Her önemli değişiklik ve karar, kim/ne zaman/ne değişti bilgisiyle denetlenebilir olur.
- Hesaplamalar yeniden üretilebilir; girdiler, formül sürümü ve çıktı birlikte saklanır.
- Hassas kişisel, mülkiyet ve ticari veriler varsayılan olarak özel kalır.
- Resmî kurum, hukuk, mühendislik, değerleme ve finans onayı gerektiren işlemler uygulama tarafından taklit edilmez.

### MVP'de özellikle yapılmayacaklar

Tam muhasebe ve bordro, resmî tapu/imar/AFAD/TÜİK entegrasyonları, otomatik ruhsat başvurusu, otomatik ödeme/escrow, tam BIM/CAD iş akışı, otomatik sözleşme imzası, üretken AI ile bağlayıcı karar ve native mobil uygulama sonraki fazlardır. Harici servis ve yasal dayanaklar doğrulanmadan entegrasyon varsayılmaz.

## 2. Kullanıcı ve yetki matrisi

Yetki yalnız rol adına bağlanmamalıdır. Etkin karar; kullanıcı rolü + organizasyon üyeliği + proje/taşınmaz ataması + nesne sınıflandırması + eylem + kayıt durumu kurallarıyla verilmelidir (RBAC + kaynak kapsamlı ABAC). Kullanıcı birden fazla rol üstlenebilir; sistem yöneticisi dahi iş/veri erişimini gerekçesiz genişletememelidir.

| Rol / kullanıcı grubu | Kendi kapsamındaki temel izin | Varsayılan olarak verilmemesi gereken izin | Mevcut prototip |
|---|---|---|---|
| Ziyaretçi | Yayındaki ilanları ve herkese açık sayfaları görme; hesap açma | Taslak ilan, özel iletişim, proje dokümanı veya maliyet görme | İlanları görür; herkese açık hesaplama araçları var |
| Malik / malik temsilcisi | Kendi taşınmazını ve doğrulanmış temsilini yönetme; kendisiyle paylaşılmış teklif, karar, görev ve ilerlemeyi görme | Diğer maliklerin kişisel bilgileri, şirket içi maliyet/kar marjı ve atanmamış taşınmaz | Rol hesabı ve kendi gönderdiği ilan/aşama durumunu görür; malik doğrulama/atama yok |
| Emlak danışmanı / fırsat kaynağı | Atanmış portföy ve müşteriyi yönetme; izinli fırsat bilgisi ekleme | Yetkisiz malik belgesi, başka danışmanın portföyü, özel sözleşme | Genel ilanları görür ve proje başvurusu gönderir |
| Geliştirici / proje yöneticisi | Atanmış fırsat ve projede senaryo, görev ve karar girdisi hazırlama | Organizasyon dışı projeler veya yetkisiz kişisel veri | Rol paneli, başvuru ve genel fizibilite erişimi var; proje üyeliği yok |
| Mimar / şehir plancısı | Atanmış tasarım kapsamı, disiplin girdisi ve revizyonlarını yönetme | Maliyet, malik kimliği veya tasarım dışı dokümanlar, özel yetki yoksa | Profil ve genel ilan/şerefiye erişimi |
| Mühendislik ekipleri | Atanmış disiplin, teknik girdi, risk ve dokümanları yönetme | Diğer disiplin/özel mali kayıtları, görev kapsamı dışı değiştirme | Mühendis rol profili var; proje/disiplin kapsamı yok |
| Müteahhit / saha yöneticisi / teknik ofis | Atanmış yapım teklifi, sözleşme kapsamı, iş paketi, saha ilerlemesi ve kanıt yükleme | Malik özel verileri, rakip teklifleri veya onaysız bütçe değiştirme | Müteahhit rolü ve yönetici tarafından güncellenen basit aşama kontrolü |
| Satın alma / tedarik / depo | Yetkili talep, tedarikçi, teklif, sipariş, teslimat ve stok hareketi | Onaysız sipariş/ödeme veya proje dışı satın alma | Yok |
| Finans / muhasebe | Yetkili bütçe, tahakkuk, nakit akışı ve raporlama | Onaysız ödeme ve tüm tenantların finans verileri | Basit maliyet varsayımı hesaplanır; gerçek finans kaydı yok |
| Hukuk / sözleşme uzmanı | Atanmış hukuki kontrol listesi, belge ve sözleşme sürümü | Hukuki onayı taklit etme; atanmadığı proje belgeleri | Yok |
| Kalite / İSG / kontrol | Atanmış kontrol, uygunsuzluk, kanıt ve kapatma akışını yönetme | Kendi uygunsuzluğunu bağımsız onaylayarak kapatma | Yok |
| Satış / kiralama / malik ilişkileri | Yetkili bağımsız bölüm, talep, müşteri ve teslim verisi | Diğer tarafların özel iletişim veya sözleşme verileri | İlan iletişim talebi admin listesine kaydedilir |
| Yönetim / yatırımcı / denetçi | Yetkili organizasyon/projelerde salt-okunur gösterge ve denetim görünümü | Kayıtları doğrudan değiştirme veya hassas belgeleri sınırsız indirme | Yok |
| Tenant yöneticisi | Kendi organizasyonunda üyelik, rol ataması, proje ve politika yönetimi | Başka tenant verisine erişim veya audit kaydını silme | Tenant kavramı yok |
| Platform operasyonu / güvenlik yöneticisi | Hesap/tenant yaşam döngüsü, güvenlik ve sistem sağlığı; erişim gerekçesiyle sınırlı | İş içeriğine olağan ve denetimsiz erişim; audit kaydını değiştirme | Tek bir site admini; tenant ve ayrık yönetici rolleri yok |

### Yetki eylemleri

Asgari eylem kümesi: `list`, `read`, `create`, `update`, `submit`, `approve`, `publish`, `assign`, `download`, `archive`, `delete` ve `administer`. Taslak, incelemede, onaylı, yürürlükte, tamamlandı ve arşiv durum geçişleri ayrıca politika ile sınırlandırılmalı. Hassas belge indirme, dışa aktarma, onay ve silme audit edilmelidir. Üretim yetki matrisi, kod öncesinde iş sahibi ve hukuk/KVKK sorumlularıyla onaylanmalıdır.

## 3. Önerilen veri modeli

### Ortak kayıt alanları

Tüm tenant-kapsamlı tablolarda değişmez `id`, `tenant_id`, `created_at`, `created_by`, `updated_at`, `updated_by`, `status`, `version` ve gerektiğinde `archived_at` tutulmalı. Kaynaklı kayıtlar için `source_type`, `source_reference`, `source_date`, `verification_status`, `verified_by`, `verified_at` ve `confidence` alanları kullanılmalı. Silme yerine kontrollü arşivleme tercih edilmeli; kişisel veri saklama/silme politikası ayrıca uygulanmalı.

| Alan / varlık grubu | Önerilen çekirdek varlıklar | Önemli ilişkiler / not |
|---|---|---|
| Organizasyon ve erişim | `tenants`, `users`, `memberships`, `roles`, `permissions`, `role_permissions`, `invitations`, `teams`, `team_members` | Tenant izolasyonunun temeli; bir kullanıcı birden çok organizasyona ve role katılabilir |
| Mülkiyet ve taşınmaz | `owners`, `representations`, `properties`, `parcels`, `ownership_shares`, `independent_units`, `property_documents` | Vekâlet/temsil ve hisse kaynak belgesiyle doğrulanmalı; parsel ile proje ilişkisi çoktan çoğa olabilir |
| CRM ve fırsat | `contacts`, `portfolios`, `opportunities`, `leads`, `consents`, `communications` | İletişim izinleri ve kayıt kaynağı tutulmalı; herkese açık ilanla özel müşteri kaydı ayrılmalı |
| Başvuru ve ortak proje brief'i | `intake_submissions`, `property_briefs`, `brief_revisions`, `brief_fields`, `field_sources`, `brief_approvals`, `project_access_grants` | Malik/arsa sahibi, geliştirici veya yetkili emlak danışmanının beyanı alan bazında kaynak ve doğrulama durumuyla sürümlenir; onaylanan brief proje uzmanlarına kontrollü paylaşılır |
| Proje ve süreç | `projects`, `project_members`, `phases`, `milestones`, `work_packages`, `tasks`, `task_assignments`, `approvals`, `decisions`, `risks`, `issues`, `change_requests`, `activity_events` | Proje üyeliği rol/varlık kapsamlı erişim sağlar; aşama kapısı belge/onay şartları taşımalı; kritik işlem geçmişi eklemeli olmalı |
| Fizibilite ve karar | `feasibility_scenarios`, `assumptions`, `formula_versions`, `scenario_results`, `offers`, `offer_revisions`, `negotiations` | Girdi, formül sürümü, hesap tarihi ve çıktı aynı revizyona bağlanmalı; hesap sonucu kaynak veriden ayrılmalı |
| Tasarım ve teknik | `design_packages`, `disciplines`, `drawings`, `revisions`, `technical_decisions`, `quantity_takeoffs`, `inspections`, `nonconformities` | Doküman revizyonu ve sorumlu disiplin izlenmeli; model/CAD kapsamı MVP dışı |
| Belge ve onay | `documents`, `document_versions`, `document_access_grants`, `document_requests`, `signatures`, `evidence_records` | Dosyanın kendisi özel nesne deposunda; metadata/izin DB'de; erişim proje ve malik kapsamıyla kısıtlı |
| Bütçe ve tedarik | `budgets`, `cost_codes`, `cost_items`, `purchase_requests`, `supplier_quotes`, `purchase_orders`, `deliveries`, `inventory_movements` | Sipariş/taahhüt/gerçekleşen maliyet ayrılmalı; onay iş akışı ve bütçe limiti olmalı |
| Finans ve insan kaynağı | `cashflow_lines`, `financial_transactions`, `invoices`, `payments`, `employees`, `timesheets` | Gerçek finans/bordro faz dışı; MVP yalnız gösterge/senaryo düzeyinde |
| Satış ve varlık işletimi | `unit_inventory`, `sales_cases`, `leases`, `handover_records`, `warranties`, `maintenance_requests` | Bağımsız bölüm, proje ve sözleşme ilişkili; teslim kanıtı ve açık işler korunmalı |
| Denetim ve iletişim | `audit_events`, `notifications`, `meetings`, `meeting_actions`, `integrations`, `webhook_events` | Denetim kaydı eklemeli ve değiştirilemez olmalı; hassas değerler gereksiz yere loglanmamalı |

### Veri güven sınırları

Tapu/kimlik, imar belgesi, vekâlet, imza, banka ve resmi API verileri yüksek hassasiyetlidir. MVP kapsamı, belgeleri yüklemeden önce erişim, şifreleme, kötü amaçlı dosya kontrolü, retention ve silme politikasını belirlemelidir. Bu politikalar hazır olmadan gerçek kimlik/tapu dosyası kabul edilmemeli. Mevcut JSON dosya deposu tenant izolasyonu, eşzamanlılık, audit ve ölçekli erişim denetiminin yerine geçmez.

## 4. Uçtan uca iş akışları

1. **Organizasyon ve erişim:** Organizasyon oluştur → yöneticiyi kur → kullanıcıyı davet et → rol ve kapsam ver → daveti kabul et → erişimi gözden geçir/revoke et.
2. **Fırsat ve başvuru:** Fırsat kaynağını kaydet → malik/temsil ilişkisini ve paylaşım iznini doğrula → malik/arsa sahibi, geliştirici veya yetkili emlak danışmanına role uygun başvuru formunu sun.
3. **Başvuru verisini derleme:** Parsel/konum, malik beyanı, mülkiyet ve temsil durumu, imar bilgisi, mevcut yapı, kullanım/miks, ticari hedefler, bütçe/takvim, pazar girdileri ve paylaşılmasına izin verilen belgeleri tek bir sürümlü proje brifinde birleştir. Her alanın kaynağı, veren kişisi, tarihi ve doğrulama durumu ayrı tutulur; eksik kritik veri varsayımla doldurulmaz.
4. **İnceleme ve onay kapısı:** Taslak → başvuruldu → eksik bilgi / incelemede → düzeltme istendi → onaylandı veya reddedildi. Onay kararı yetkili tarafından gerekçesi ve zaman damgasıyla kaydedilir. Onaylı sürüm değiştirilemez; değişiklik yeni revizyon ve yeniden onay gerektirir. Kritik bilgi eksikse çıktı “kavramsal/varsayımsal” olarak kalır ve bağlayıcı teklif/keşif temeli olarak işaretlenmez.
5. **Uzman çalışma alanlarına kontrollü aktarım:** Onaylanan ve paylaşım izni bulunan proje brifi, sadece projeye atanmış mimar, mühendis ve müteahhit ekranlarında rol kapsamına göre görünür. Mimar tasarım programını/kısıtları; mühendis disiplin girdilerini ve ölçülebilir metraj dayanaklarını; müteahhit yalnızca paylaşılmış uygulama kapsamını ve ticari olarak açılmış verileri görür. Malik kimliği, özel belgeler ve şirket içi maliyetler varsayılan olarak kapalıdır. Paylaşım, indirme ve dışa aktarma izinleri ayrıdır.
6. **Teklif ve keşif alt akışı:** Uzmanlar yalnızca onaylı brif ve kaynaklara bağlı varsayım/revizyonla teklif girdisi veya keşif/metraj taslağı üretir. Her miktar ölçüm kuralı, çizim/belge sürümü, birim ve sorumlu uzmanla ilişkilendirilir; görsel/AI tahmini nihai miktar sayılmaz. Değişen brief veya tasarım, bağlı teklif ve metrajları “yeniden inceleme gerekli” durumuna çeker; malik/iş sahibi revizyonu tekrar onaylar. Bu modül hukuki sözleşme, resmi keşif, hakediş veya mühendis onayı üretmez.
7. **Ön durum tespiti:** Gerekli tapu/imar/teknik belgeleri talep et → kaynak/tarih/veren ve doğrulama durumunu kaydet → hukuk, imar, zemin ve teknik riskleri atayıp karara bağla.
8. **Senaryo ve fizibilite:** Varsayımları sürümlendir → alan, maliyet, piyasa ve paylaşım senaryoları üret → hassasiyeti karşılaştır → uyarı ve sınırları içeren karar özeti hazırla.
9. **Teklif ve uzlaşma:** Yetkili teklif oluştur → revizyonla müzakere et → malik/şirket onaylarını topla → reddet/kabul et/sona erdir → sözleşme hazırlığına geç.
10. **Tasarım, izin, yapım ve saha:** Tasarım paketini disiplinlere ata → revizyon/teknik kararları kaydet → belge/onay kapılarını tamamla → bütçe, satın alma, saha, kalite/İSG ve kilometre taşlarını kanıtla izle.
11. **Finans, satış, teslim ve işletme:** Tahmin/taahhüt/gerçekleşeni ayır → nakit akışı ve sapma üret → rol bazlı görünüm ver → satış/teslim, garanti ve bakım işlerini varlık kaydına bağla.

### Başvuru formundan onaylı uzman brifine geçiş — VisionForge entegrasyonu

Bu, ana ürün akışına başvuru ve doğrulama ile uzman üretim modülleri arasında yerleştirilecek köprü aşamasıdır; ayrı ve başıboş bir form değildir.

- **Giriş rolleri:** Malik/arsa sahibi veya doğrulanmış temsilcisi; proje geliştirici/yatırımcı; izin ve portföy ilişkisi bulunan emlak danışmanı. Form ilgili role göre sadeleşir, ancak aynı ortak alan sözlüğüne kaydeder.
- **Derlenen girdiler:** Parsel/konum ve varsa kaynak referansı; mülkiyet/temsil durumu; imar ve mevcut yapı beyanları; kullanım ve bağımsız bölüm programı; hedefler, bütçe, zaman, paylaşım beklentisi; pazar/satış-kiralama varsayımları; proje gereksinimleri ve paylaşılmasına izin verilen belge referansları.
- **Alan düzeyinde köken ve doğrulama:** Her girdi `source`, `provided_by`, `source_date`, `verification_status`, `verified_by`, `verified_at`, `confidence` ve gerekirse not/revizyonla tutulur. Kullanıcı beyanı resmi olarak doğrulanmış veri gibi gösterilmez. Eksik kritik veri, tasarım veya maliyet motorunda sessizce türetilmez.
- **Onay kapısı:** Başvuru sahibi gönderir → inceleyici eksik alan/belge ister → başvuru sahibi revize eder → yetkili her brif sürümünü onaylar/reddeder. Onaylı sürüm sabittir; her düzenleme yeni revizyon ve yeniden onay doğurur. Reddedilen veya eksik sürüm uzman modüllerine “onaylı veri” olarak aktarılmaz.
- **Uzman ekranları:** Onaylı ve paylaşılabilir alanlar yalnız atanmış mimar, mühendis ve müteahhit çalışma alanlarında görünür. Erişim proje + rol/disiplin + alan sınıflandırması ile belirlenir. Mimar brief/kısıt/programı; mühendis kaynaklı teknik girdileri ve metraj dayanağını; müteahhit onaylı uygulama kapsamını ve yetkili ticari veriyi görür. Bir rolün tüm malik/finans belgelerini görmesi varsayılan değildir.
- **Teklif/keşif bağlantısı:** Onaylı proje brifi; teklif hazırlığı, yaklaşık keşif/metraj, maliyet ve satış-kiralama briefleri için referans girdisidir. Her çıktı brif sürümü, teknik belge/çizim revizyonu, hesap formülü, ölçüm kuralı, sorumlu ve hazırlanma tarihiyle bağlanır. Dayanağı değişen çıktı eski sürüm olarak kalır ve yeniden gözden geçirme ister.
- **Görünür durumlar:** Taslak, başvuruldu, eksik bilgi, incelemede, revizyon istendi, reddedildi, onaylandı, uzman çalışmasına açıldı, uzman incelemesi bekliyor, teklif/keşif taslağı, güncellik incelemesi gerekli. Yalnız `onaylandı` durumundaki sürüm yetkili paylaşım kurallarıyla downstream başlangıç verisi olur.
- **Güvenlik ve hukuki sınır:** Belge içeriği, mülkiyet, temsil, uzmanlık ve piyasa bilgileri kanıt gelmeden doğrulanmış sayılmaz. Tapu/kimlik gibi hassas dosyalar için özel depolama, malware taraması, saklama/imha ve açık paylaşım politikaları hazır olmadan gerçek dosya yükleme açılmaz. Onay; ruhsat, değerleme, sözleşme, resmi keşif, hakediş veya mühendislik tasdikinin yerine geçmez.

Bu akışın en erken uygulama adımı, gerçek hassas belge kabul etmeyen prototipte alan sözlüğü + kaynak/doğrulama metadata'sı + onaylı revizyon kaydıdır. Uzman ekranı ve teklif/keşif üretimi, tenant/proje erişim kontrolleri ve onay kapısı test edilmeden açılmaz.

Her geçişte sorumlu, ön koşul, gerekli kanıt/belge, onaylayan ve engel durumu belirlenmelidir. Bir kilometre taşı kaydı tek başına teknik kabul, hakediş, tapu devri veya ödeme onayı değildir.

## 5. MVP planı ve çıkış kapıları

| Faz | Kapsam | Çıkış ölçütü |
|---|---|---|
| Faz 0 — Ürün/alan keşfi | Paydaş ve rol haritası; süreç ve terimler sözlüğü; veri sınıfları; yasal değerlendirme; başarı ölçütleri; MVP sınırı | Malik, şirket ve proje temsilcileri ana senaryo, veri sahibi ve karar kapılarını onaylar |
| Faz 1 — Güvenli temel | Üretim kimliği, tenant izolasyonu, RBAC+ABAC, MFA/hesap kurtarma, audit, özel belge depolama, yedek/geri yükleme, KVKK süreç tasarımı | Negatif erişim testleri, tenant sınır testi, audit ve restore tatbikatı başarılı; tehdit modeli gözden geçirilmiş |
| Faz 2 — Malik ve taşınmaz | Organizasyon, davet, malik/temsil doğrulama, taşınmaz/parsel/hisse, belge talebi ve kontrollü malik görünümü | Yetkisiz malik/proje belge erişimi engellenir; doğrulama durumu ve kaynağı izlenir |
| Faz 3 — CRM ve fırsat | Portföy/fırsat, iletişim izni, malik/geliştirici/emlak danışmanı başvuru sihirbazı, alan kaynakları, eksik bilgi ve inceleme/onay/revizyon kapısı | Onaylı brief sürümünün kökeni ve onaylayanı izlenir; eksik kritik veri onaylı gibi işaretlenmez |
| Faz 4 — Fizibilite ve uzlaşma | Yalnız onaylı brief sürümünden başlayan sürümlü varsayımlar/formül, senaryo ve hassasiyet, teklif revizyonu, malik özeti ve karar; teklif/keşif/metraj taslakları brief ve teknik kaynak revizyonuna bağlanır | Sonuç tekrar üretilebilir; veri veya çizim değişince bağlı çıktılar yeniden incelemeye düşer; çıktı sınırlamaları açık |
| Faz 5 — Teknik proje çekirdeği | Onaylı ve paylaşılabilir brief alanlarının atanmış mimar/mühendis/müteahhit ekranlarına kapsamlı aktarımı; tasarım paketi, disiplin, görev/belge talebi, revizyon, ölçüm dayanağı ve teknik karar | Her uzman yalnız yetkili proje/rol/veri sınıfını görür ve değiştirir; ticari/kişisel özel alanlar varsayılan kapalıdır |
| Faz 6 — Yapım ve satın alma | Bütçe taahhüt/gerçekleşme, satın alma, saha/kalite/İSG ve kanıtlı kilometre taşları | Her onay görev kapsamına bağlı; maliyet ve fiziksel ilerleme izlenebilir |
| Faz 7 — Finans ve insan kaynağı | Nakit akışı, tahakkuk/ödeme, puantaj ve kontrollü finans raporları | Rol bazlı finans görünümü ve mutabakat kontrolleri çalışır |
| Faz 8 — Satış, teslim, varlık | Satış/kiralama, teslim, garanti ve bakım | Bağımsız bölümden teslim/işletmeye izlenebilir kayıt zinciri |
| Faz 9–10 — AI, entegrasyon ve ölçek | Kaynaklı AI karar desteği, güvenli entegrasyon, mobil ve çoklu organizasyon ölçeği | İnsan onayı, kaynak gösterimi, prompt injection kontrolleri, API geri dönüş/gözlem ve yük testleri |

### İlk uygulanabilir MVP'nin daraltılmış kullanıcı hikâyesi

Bir organizasyon yöneticisi güvenli biçimde tenant kurar; maliki davet eder ve taşınmaz/temsil ilişkisini kaynaklı olarak doğrular; malik, geliştirici veya yetkili emlak danışmanı role uygun formdan ortak proje brifini gönderir; eksikler tamamlanıp yetkili brif sürümünü onaylar; yalnız atanmış mimar, mühendis ve müteahhit rol kapsamındaki alanları görür; bu ekipler onaylı brif sürümüne bağlı tasarım, teknik girdi, teklif ve keşif/metraj taslağı hazırlar; kaynak değişirse bağlı çıktılar yeniden incelemeye düşer; malik karar verir; görevler atanır; audit kayıtları ve yedek/geri yükleme testi başarıyla tamamlanır. Bu zincir uçtan uca çalışmadan geniş meslek modüllerine geçilmemelidir.

## 6. Bugünkü uygulama ile karşılaştırma

| Alan | Şimdiye kadar yapılan | Durum | MVP gereği / boşluk |
|---|---|---|---|
| Web prototipi ve ilan deneyimi | PHP çok sayfalı portal; ilan arama/detay; moderasyon; örnek kayıtlar | Kısmi prototip | Ürün keşfi, organizasyon izolasyonu, gerçek veri operasyonu ve üretim işletimi yok |
| Kimlik ve rol | Üye kayıt/giriş; altı temel meslek rolü; tek site admini; profil düzenleme | Kısmi | Tenant üyeliği/davet, çoklu rol, MFA/kurtarma, admin ayrımı ve kapsamlı yetki yok |
| Profil düzenleme | Oturum sahibinin profil alanları güncellenir; e-posta benzersizliği kontrol edilir | Tamamlandı (bu iterasyonda test edildi) | E-posta doğrulama, onaylı mesleki sicil ve hesap yaşam döngüsü yok |
| Proje ve taşınmaz | Proje başvurusu, yayın moderasyonu, ilan sahibi kimliği | Kısmi | Malik/vekalet/hisse/parsel ve proje üyeliği/erişim politikaları yok |
| Onaylı uzman proje brifi | Role uygun başvuru alanları; beyan kaynağı/tarihi; admin eksik bilgi/ret/onay; malik revizyonu; onaylı snapshot geçmişi; proje bazında mimar/mühendis/müteahhit ataması ve sınırlı uzman görünümü | Kısmi, sentetik HTTP uçtan uca test edildi | Belge dosyası/kanıt ve doğrulama, tenant izolasyonu, ayrıntılı alan düzeyli ACL, uzman arayüzünde sürüm karşılaştırma ve kaynaklı teklif/keşif taslakları henüz yok |
| Belgeler | Form metni dışında dosya kasası yok | Eksik | Belge talepleri, erişim, sürüm, virüs taraması, kaynak ve doğrulama durumu |
| Fizibilite | Kullanıcı varsayımı ile alan/maliyet/satış/pay göstergesi ve hassasiyet | Kısmi | Varsayım/formül revizyon saklama, veri kaynakları, audit, onay ve projeye bağlama |
| Şerefiye | 7 kriterli kullanıcı ağırlıklı endeks, tam 10.000 baz puan, CSV | Kısmi | Saaty AHP değil; onay/itiraz süreci, sürümlü mutabakat ve bağımsız doğrulama yok |
| Yapım takibi | Adminin güncellediği beş aşamalı durum listesi; malik panelinde görünüm | Kısmi | Kanıt, görev ataması, onay, program, saha, kalite/İSG ve maliyet gerçekleşmesi yok |
| Teklif/karar/sözleşme | Bağlayıcı teklif/karar/sözleşme akışı yok | Eksik | Teklif sürümü, malik/şirket onayı, imza ve hukuki inceleme |
| Veri mimarisi | Tek sunuculu JSON dosyaları ve dosya kilidi; veriyi web kökü dışında tutma | MVP hedefi değil | İlişkisel DB, tenant güvenlik sınırı, migrasyon, audit, retention, yedekleme/restore tatbikatı |
| Görev/uyarı/işbirliği | Yok | Eksik | Atama, belge talebi, bildirim, toplantı aksiyonu, risk ve karar kütüğü |
| Operasyon ve yayın | Yerel PHP sunucusunda çalışıyor; README yayına hazırlık notları var | Yerel prototip | Hosting/HTTPS/izleme/operasyon ve güvenlik doğrulaması yapılmadı |

## 7. Önerilen uygulama sırası

1. Faz 0 kararlarını (ilk pilot organizasyon, malik senaryosu, veri sınıfları, başarı ölçütü) yazılı onaylayın.
2. Tenant ve erişim modeli, ilişkisel veri şeması, belge güvenliği ve audit tasarımını teknik karar kaydı olarak netleştirin.
3. Geniş MVP'den önce dar uçtan uca tenant + malik + taşınmaz + belge talebi + sürümlü fizibilite + teklif/onay iş akışını kurun.
4. Mevcut JSON prototipini üretim verisi için kullanmayın; gerekiyorsa açık veri migrasyonu ve doğrulama planı hazırlayın.
5. Her fazı pozitif/negatif yetki testleri, audit kontrolü, yedek/restore ve kullanıcı kabul ölçütleriyle kapatın.

## Kaynak

- Master prompt: [ASATA_ZERO_TO_ONE_ULTRA_MASTER_PROMPT.md](./ASATA_ZERO_TO_ONE_ULTRA_MASTER_PROMPT.md)
- Ürün misyonu ve ilkeler: §§0–1, satır 77–149
- Kullanıcı ve yetki evreni: §2, satır 159–348
- Veri omurgası: §3.2, satır 381–408
- Yaşam döngüsü: §4, satır 412–437 ve sonrası
- Aşamalı yol haritası: §15, satır 1444–1573
- İlk MVP ve kapsam dışı: §16, satır 1577–1601
- Risk kütüphanesi: §18, satır 1641–1669
- VisionForge rol-temelli veri toplama ve veri durumları: §§2.1–3.3, satır 33–128
- VisionForge proje brifi ve teslim paketleri: §2.2, satır 63–78
- VisionForge uzman teslimi ve rol/proje erişimi: §§12, 14, satır 553–570 ve 592–603
- VisionForge geçerlilik/onay durumları ve miktar kuralları: satır 306, 335–360 ve 534–549
- İkinci promptun proje klasörü yedeği: [ASATA_VisionForge_Studio_MASTER_PROMPT_V2.md](./ASATA_VisionForge_Studio_MASTER_PROMPT_V2.md)
