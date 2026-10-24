<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function data_dir(): string
{
    $configured = getenv('ASATA_DATA_DIR');
    return $configured !== false && $configured !== ''
        ? rtrim($configured, DIRECTORY_SEPARATOR)
        : dirname(__DIR__, 2) . '/asata-data';
}

function ensure_data_dir(): void
{
    $directory = data_dir();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Veri klasörü oluşturulamadı.');
    }
}

function read_json(string $name): array
{
    ensure_data_dir();
    $path = data_dir() . '/' . basename($name);
    if (!is_file($path)) {
        if (basename($name) === 'projects.json') {
            return [
                [
                    'id' => 'demo-istanbul-001',
                    'title' => 'Örnek kayıt — İstanbul merkezde geliştirme fırsatı',
                    'city' => 'İstanbul',
                    'district' => 'Kadıköy',
                    'neighborhood' => 'Örnek Mahalle',
                    'area' => 920,
                    'zoning' => 'Örnek bilgi; resmî olarak doğrulanmamıştır',
                    'share' => '',
                    'contact_name' => 'Örnek başvuru',
                    'contact_email' => '',
                    'contact_phone' => '',
                    'description' => 'Bu, portalın görünümünü göstermek için eklenmiş örnek ilandır. Parsel, imar, mülkiyet veya teklif bilgisi gerçek değildir ve yatırım kararı için kullanılamaz.',
                    'created_at' => '2026-10-24T00:00:00+03:00',
                    'status' => 'published',
                    'demo' => true,
                ],
                [
                    'id' => 'demo-ankara-001',
                    'title' => 'Örnek kayıt — Ankara arsa geliştirme senaryosu',
                    'city' => 'Ankara',
                    'district' => 'Çankaya',
                    'neighborhood' => 'Örnek Mahalle',
                    'area' => 1280,
                    'zoning' => 'Örnek bilgi; resmî olarak doğrulanmamıştır',
                    'share' => '',
                    'contact_name' => 'Örnek başvuru',
                    'contact_email' => '',
                    'contact_phone' => '',
                    'description' => 'Bu, portalın görünümünü göstermek için eklenmiş örnek ilandır. Parsel, imar, mülkiyet veya teklif bilgisi gerçek değildir ve yatırım kararı için kullanılamaz.',
                    'created_at' => '2026-10-24T00:00:00+03:00',
                    'status' => 'published',
                    'demo' => true,
                ],
            ];
        }
        return [];
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Veri dosyası okunamadı.');
    }
    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    return is_array($decoded) ? $decoded : [];
}

function write_json(string $name, array $data): void
{
    ensure_data_dir();
    $directory = data_dir();
    $path = $directory . '/' . basename($name);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $temporary = tempnam($directory, '.write-');
    if ($temporary === false) {
        throw new RuntimeException('Geçici veri dosyası oluşturulamadı.');
    }
    try {
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Veri dosyası yazılamadı.');
        }
        chmod($temporary, 0640);
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Veri dosyası güncellenemedi.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

function json_update(string $name, callable $callback): void
{
    ensure_data_dir();
    $lockPath = data_dir() . '/' . basename($name) . '.lock';
    $lock = fopen($lockPath, 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Veri güncelleme kilidi alınamadı.');
    }
    try {
        write_json($name, $callback(read_json($name)));
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(mixed $token): bool
{
    return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function unrestricted_local_demo_enabled(): bool
{
    if (getenv('ASATA_DEMO_FULL_ACCESS') !== '1') {
        return false;
    }
    $host = parse_url('http://' . (string)($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $demoDataDirectory = dirname(__DIR__, 2) . '/asata-demo-data';

    return in_array($host, ['127.0.0.1', 'localhost', '[::1]', '::1'], true)
        && in_array($remoteAddress, ['127.0.0.1', '::1'], true)
        && rtrim(data_dir(), DIRECTORY_SEPARATOR) === $demoDataDirectory;
}

function is_admin(): bool
{
    return ($_SESSION['is_admin'] ?? false) === true || unrestricted_local_demo_enabled();
}

function user_roles(): array
{
    return [
        'malik' => ['label' => 'Malik / arsa sahibi', 'summary' => 'Parselini tanıt, gelen proje başvurularını ve fizibilite adımlarını takip et.'],
        'gelistirici' => ['label' => 'Proje geliştirici', 'summary' => 'Geliştirme fırsatlarını ve proje hazırlık çalışmalarını düzenle.'],
        'emlakci' => ['label' => 'Emlak danışmanı', 'summary' => 'Portföyündeki arsaları ve müşterilerden gelen talepleri takip et.'],
        'mimar' => ['label' => 'Mimar', 'summary' => 'Tasarım kapsamını, avan proje çalışmalarını ve proje gereksinimlerini paylaş.'],
        'muhendis' => ['label' => 'Mühendis', 'summary' => 'Disiplin, teknik analiz ve proje teslim girdilerini düzenle.'],
        'muteahhit' => ['label' => 'Müteahhit', 'summary' => 'İş geliştirme fırsatlarını ve yapım tekliflerini takip et.'],
    ];
}

function project_milestones(): array
{
    return [
        'subasman' => ['label' => 'Temel / subasman aşaması', 'allocation' => 10],
        'kaba_yapi' => ['label' => 'Taşıyıcı kaba yapı tamamlandı', 'allocation' => 30],
        'cephe_duvar' => ['label' => 'Çatı, dış cephe ve duvarlar', 'allocation' => 20],
        'ince_isler' => ['label' => 'İnce işler ve mekanik tesisat', 'allocation' => 20],
        'iskan' => ['label' => 'İskan ve kat mülkiyeti süreci', 'allocation' => 20],
    ];
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_string($userId) || $userId === '') {
        return null;
    }
    foreach (read_json('users.json') as $user) {
        if (($user['id'] ?? null) === $userId && ($user['role'] ?? null) === ($_SESSION['user_role'] ?? null)) {
            return $user;
        }
    }
    unset($_SESSION['user_id'], $_SESSION['user_role']);
    return null;
}

function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        throw new RuntimeException('Bu işlem için yönetici girişi gereklidir.');
    }
}

function read_admin(): ?array
{
    $admins = read_json('admin.json');
    return isset($admins['username'], $admins['password_hash']) ? $admins : null;
}

function validate_project(array $input): array
{
    $fields = [
        'title', 'city', 'district', 'neighborhood', 'zoning',
        'ownership_status', 'current_building', 'intended_use',
        'budget_range', 'target_timeline', 'market_assumptions',
        'contact_name', 'contact_email', 'contact_phone', 'description',
    ];
    $project = [];
    foreach ($fields as $field) {
        $project[$field] = trim((string)($input[$field] ?? ''));
    }
    $applicantRole = trim((string)($input['applicant_role'] ?? ''));
    if (!in_array($applicantRole, ['malik', 'gelistirici', 'emlakci'], true)) {
        throw new RuntimeException('Başvuru sahibi rolünü kontrol edin.');
    }
    $project['applicant_role'] = $applicantRole;
    $area = filter_var($input['area'] ?? null, FILTER_VALIDATE_FLOAT);
    $shareInput = trim((string)($input['share'] ?? ''));
    $share = $shareInput === '' ? '' : filter_var($shareInput, FILTER_VALIDATE_FLOAT);
    if (strlen($project['title']) < 5 || $project['city'] === '' || $project['district'] === '' || $project['neighborhood'] === '') {
        throw new RuntimeException('İlan başlığı, il, ilçe ve mahalle alanlarını kontrol edin.');
    }
    if ($area === false || $area <= 0 || $area > 10000000) {
        throw new RuntimeException('Parsel alanı 0 ile 10.000.000 m² arasında olmalıdır.');
    }
    if ($shareInput !== '' && ($share === false || $share < 0 || $share > 100)) {
        throw new RuntimeException('Paylaşım beklentisi 0–100 arasında olmalıdır.');
    }
    if (!filter_var($project['contact_email'], FILTER_VALIDATE_EMAIL) || $project['contact_phone'] === '') {
        throw new RuntimeException('Geçerli bir e-posta ve telefon girin.');
    }
    if (mb_strlen($project['description'], 'UTF-8') < 30 || mb_strlen($project['description'], 'UTF-8') > 3000) {
        throw new RuntimeException('Açıklama 30–3000 karakter arasında olmalıdır.');
    }
    foreach (['ownership_status' => 80, 'current_building' => 120, 'intended_use' => 160, 'budget_range' => 100, 'target_timeline' => 100, 'market_assumptions' => 1000] as $field => $maxLength) {
        if (mb_strlen($project[$field], 'UTF-8') > $maxLength) {
            throw new RuntimeException('Başvuru alanlarından biri izin verilen uzunluğu aşıyor.');
        }
    }
    if (($input['consent'] ?? '') !== 'yes') {
        throw new RuntimeException('Başvuru için bilgilendirme onayı gereklidir.');
    }
    $project['area'] = (float)$area;
    $project['share'] = $share === '' ? '' : (float)$share;
    return $project;
}

function validate_lead(array $input): array
{
    $lead = [
        'name' => trim((string)($input['name'] ?? '')),
        'phone' => trim((string)($input['phone'] ?? '')),
        'email' => trim((string)($input['email'] ?? '')),
        'message' => trim((string)($input['message'] ?? '')),
        'project_id' => trim((string)($input['project_id'] ?? '')),
        'project_title' => trim((string)($input['project_title'] ?? '')),
    ];
    if ($lead['name'] === '' || $lead['phone'] === '' || ($lead['email'] !== '' && !filter_var($lead['email'], FILTER_VALIDATE_EMAIL))) {
        throw new RuntimeException('Ad, telefon ve geçerliyse e-posta bilgilerini kontrol edin.');
    }
    if (($input['consent'] ?? '') !== 'yes') {
        throw new RuntimeException('Talep için bilgilendirme onayı gereklidir.');
    }
    return $lead;
}

function validate_user(array $input): array
{
    $roles = user_roles();
    $user = [
        'name' => trim((string)($input['name'] ?? '')),
        'email' => mb_strtolower(trim((string)($input['email'] ?? '')), 'UTF-8'),
        'phone' => trim((string)($input['phone'] ?? '')),
        'role' => trim((string)($input['role'] ?? '')),
        'city' => trim((string)($input['city'] ?? '')),
        'company' => trim((string)($input['company'] ?? '')),
        'professional_detail' => trim((string)($input['professional_detail'] ?? '')),
        'authorization_number' => trim((string)($input['authorization_number'] ?? '')),
        'password' => (string)($input['password'] ?? ''),
    ];
    if ($user['name'] === '' || mb_strlen($user['name'], 'UTF-8') > 100 || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ad soyad ve geçerli e-posta adresi girin.');
    }
    if ($user['phone'] === '' || mb_strlen($user['phone'], 'UTF-8') > 30) {
        throw new RuntimeException('Geçerli bir telefon numarası girin.');
    }
    foreach (['city' => 100, 'company' => 140, 'professional_detail' => 180, 'authorization_number' => 100] as $field => $maxLength) {
        if (mb_strlen($user[$field], 'UTF-8') > $maxLength) {
            throw new RuntimeException('Profil alanlarından biri izin verilen uzunluğu aşıyor.');
        }
    }
    if (!isset($roles[$user['role']])) {
        throw new RuntimeException('Kayıt türünü listeden seçin.');
    }
    if (strlen($user['password']) < 12) {
        throw new RuntimeException('Parola en az 12 karakter olmalıdır.');
    }
    if (($input['consent'] ?? '') !== 'yes') {
        throw new RuntimeException('Hesap oluşturmak için bilgilendirme onayı gereklidir.');
    }
    return $user;
}

function validate_profile_update(array $input): array
{
    $profile = [
        'name' => trim((string)($input['name'] ?? '')),
        'email' => mb_strtolower(trim((string)($input['email'] ?? '')), 'UTF-8'),
        'phone' => trim((string)($input['phone'] ?? '')),
        'city' => trim((string)($input['city'] ?? '')),
        'company' => trim((string)($input['company'] ?? '')),
        'professional_detail' => trim((string)($input['professional_detail'] ?? '')),
        'authorization_number' => trim((string)($input['authorization_number'] ?? '')),
    ];
    if ($profile['name'] === '' || mb_strlen($profile['name'], 'UTF-8') > 100 || !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ad soyad ve geçerli e-posta adresi girin.');
    }
    if ($profile['phone'] === '' || mb_strlen($profile['phone'], 'UTF-8') > 30) {
        throw new RuntimeException('Geçerli bir telefon numarası girin.');
    }
    foreach (['city' => 100, 'company' => 140, 'professional_detail' => 180, 'authorization_number' => 100] as $field => $maxLength) {
        if (mb_strlen($profile[$field], 'UTF-8') > $maxLength) {
            throw new RuntimeException('Profil alanlarından biri izin verilen uzunluğu aşıyor.');
        }
    }
    return $profile;
}

function calculate_feasibility(array $input): array
{
    $definitions = [
        'parcel_area' => ['label' => 'Parsel alanı', 'default' => 1000.0, 'min' => 1, 'max' => 10000000],
        'kaks' => ['label' => 'KAKS / emsal', 'default' => 1.2, 'min' => 0, 'max' => 10],
        'extra_area_ratio' => ['label' => 'Resmî uygunluğu ayrıca doğrulanacak emsal dışı alan', 'default' => 0.0, 'min' => 0, 'max' => 30],
        'basement_area' => ['label' => 'Varsayımsal bodrum brüt alanı', 'default' => 0.0, 'min' => 0, 'max' => 50000000],
        'saleable_ratio' => ['label' => 'Satılabilir alan / emsal alanı', 'default' => 82.0, 'min' => 1, 'max' => 100],
        'construction_unit_cost' => ['label' => 'Brüt inşaat alanı birim maliyeti', 'default' => 30000.0, 'min' => 0, 'max' => 100000000],
        'other_costs' => ['label' => 'Diğer proje giderleri', 'default' => 4000000.0, 'min' => 0, 'max' => 1000000000000],
        'sale_unit_price' => ['label' => 'Satış birim fiyatı', 'default' => 70000.0, 'min' => 0, 'max' => 1000000000],
        'target_margin' => ['label' => 'Hedef müteahhit kâr oranı', 'default' => 25.0, 'min' => 0, 'max' => 100],
        'concrete_per_sqm' => ['label' => 'Beton katsayısı', 'default' => 0.0, 'min' => 0, 'max' => 20],
        'steel_kg_per_sqm' => ['label' => 'Donatı çeliği katsayısı', 'default' => 0.0, 'min' => 0, 'max' => 2000],
        'formwork_per_sqm' => ['label' => 'Kalıp katsayısı', 'default' => 0.0, 'min' => 0, 'max' => 50],
        'masonry_per_sqm' => ['label' => 'Duvar katsayısı', 'default' => 0.0, 'min' => 0, 'max' => 50],
    ];
    $values = [];
    foreach ($definitions as $key => $definition) {
        $rawInput = $input[$key] ?? null;
        if ($rawInput !== null && !is_scalar($rawInput)) {
            throw new RuntimeException($definition['label'] . ' alanını kontrol edin.');
        }
        $raw = trim((string)($rawInput ?? ''));
        $value = $raw === '' ? $definition['default'] : filter_var($raw, FILTER_VALIDATE_FLOAT);
        if ($value === false || !is_finite((float)$value) || $value < $definition['min'] || $value > $definition['max']) {
            throw new RuntimeException($definition['label'] . ' alanını kontrol edin.');
        }
        $values[$key] = (float)$value;
    }
    $netArea = $values['parcel_area'] * $values['kaks'];
    $extraArea = $netArea * $values['extra_area_ratio'] / 100;
    $grossArea = $netArea + $extraArea + $values['basement_area'];
    $saleableArea = $netArea * $values['saleable_ratio'] / 100;
    $constructionCost = $grossArea * $values['construction_unit_cost'] + $values['other_costs'];
    $grossDevelopmentValue = $saleableArea * $values['sale_unit_price'];
    $requiredContractorReturn = $constructionCost * (1 + $values['target_margin'] / 100);
    $ownerShare = $grossDevelopmentValue > 0
        ? min(100.0, max(0.0, ($grossDevelopmentValue - $requiredContractorReturn) / $grossDevelopmentValue * 100))
        : 0.0;
    $scenarios = [];
    foreach ([
        ['name' => 'Olumsuz', 'cost_factor' => 1.15, 'sale_factor' => 0.90],
        ['name' => 'Temel', 'cost_factor' => 1.0, 'sale_factor' => 1.0],
        ['name' => 'Olumlu', 'cost_factor' => 0.90, 'sale_factor' => 1.10],
    ] as $scenario) {
        $scenarioCost = $constructionCost * $scenario['cost_factor'];
        $scenarioValue = $grossDevelopmentValue * $scenario['sale_factor'];
        $scenarios[] = [
            'name' => $scenario['name'],
            'cost' => $scenarioCost,
            'value' => $scenarioValue,
            'owner_share' => $scenarioValue > 0
                ? min(100.0, max(0.0, ($scenarioValue - $scenarioCost * (1 + $values['target_margin'] / 100)) / $scenarioValue * 100))
                : 0.0,
        ];
    }
    return [
        'inputs' => $values,
        'net_area' => $netArea,
        'extra_area' => $extraArea,
        'gross_area' => $grossArea,
        'saleable_area' => $saleableArea,
        'construction_cost' => $constructionCost,
        'gross_development_value' => $grossDevelopmentValue,
        'required_contractor_return' => $requiredContractorReturn,
        'owner_share' => $ownerShare,
        'owner_area' => $saleableArea * $ownerShare / 100,
        'scenarios' => $scenarios,
        'materials' => [
            'concrete' => $grossArea * $values['concrete_per_sqm'],
            'steel_tons' => $grossArea * $values['steel_kg_per_sqm'] / 1000,
            'formwork' => $grossArea * $values['formwork_per_sqm'],
            'masonry' => $grossArea * $values['masonry_per_sqm'],
        ],
    ];
}

function calculate_unit_fairness(array $input): array
{
    $criteria = [
        'floor' => ['label' => 'Kat', 'default_weight' => 20],
        'facade' => ['label' => 'Cephe / yön', 'default_weight' => 15],
        'daylight' => ['label' => 'Gün ışığı', 'default_weight' => 15],
        'view' => ['label' => 'Manzara / açıklık', 'default_weight' => 15],
        'noise' => ['label' => 'Gürültü konforu', 'default_weight' => 15],
        'balcony' => ['label' => 'Balkon / teras', 'default_weight' => 10],
        'parking' => ['label' => 'Otopark / depo', 'default_weight' => 10],
    ];
    $inputWeights = $input['weight'] ?? [];
    if (!is_array($inputWeights)) {
        throw new RuntimeException('Kriter ağırlıklarını kontrol edin.');
    }
    $weights = [];
    $weightTotal = 0.0;
    foreach ($criteria as $key => $criterion) {
        $raw = $inputWeights[$key] ?? $criterion['default_weight'];
        if (!is_scalar($raw) || filter_var((string)$raw, FILTER_VALIDATE_FLOAT) === false) {
            throw new RuntimeException('Kriter ağırlıklarını kontrol edin.');
        }
        $weight = (float)$raw;
        if (!is_finite($weight) || $weight < 0 || $weight > 100) {
            throw new RuntimeException('Kriter ağırlıkları 0–100 arasında olmalıdır.');
        }
        $weights[$key] = $weight;
        $weightTotal += $weight;
    }
    if ($weightTotal <= 0) {
        throw new RuntimeException('En az bir kriter ağırlığı sıfırdan büyük olmalıdır.');
    }

    $names = $input['unit_name'] ?? [];
    if (!is_array($names)) {
        throw new RuntimeException('Bağımsız bölüm adlarını kontrol edin.');
    }
    $inputScores = $input['score'] ?? [];
    if (!is_array($inputScores)) {
        throw new RuntimeException('Bağımsız bölüm puanlarını kontrol edin.');
    }
    $units = [];
    foreach (array_slice($names, 0, 30, true) as $index => $rawName) {
        if (!is_scalar($rawName)) {
            throw new RuntimeException('Bağımsız bölüm adlarını kontrol edin.');
        }
        $name = trim((string)$rawName);
        if ($name === '') {
            continue;
        }
        if (mb_strlen($name, 'UTF-8') > 60) {
            throw new RuntimeException('Bağımsız bölüm adı 60 karakterden uzun olamaz.');
        }
        $scores = [];
        $weightedScore = 0.0;
        foreach ($criteria as $key => $criterion) {
            $criterionScores = $inputScores[$key] ?? [];
            if (!is_array($criterionScores)) {
                throw new RuntimeException('Bağımsız bölüm puanlarını kontrol edin.');
            }
            $rawScore = $criterionScores[$index] ?? null;
            if (!is_scalar($rawScore) || filter_var((string)$rawScore, FILTER_VALIDATE_INT) === false) {
                throw new RuntimeException($name . ' için "' . $criterion['label'] . '" puanını 1–5 arasında seçin.');
            }
            $score = (int)$rawScore;
            if ($score < 1 || $score > 5) {
                throw new RuntimeException($name . ' için "' . $criterion['label'] . '" puanı 1–5 arasında olmalıdır.');
            }
            $scores[$key] = $score;
            $weightedScore += $score * ($weights[$key] / $weightTotal);
        }
        $units[] = ['name' => $name, 'scores' => $scores, 'weighted_score' => $weightedScore];
    }
    if (count($units) < 2) {
        throw new RuntimeException('Hakkaniyet karşılaştırması için en az iki bağımsız bölüm girin.');
    }
    $scoreTotal = array_sum(array_column($units, 'weighted_score'));
    if ($scoreTotal <= 0) {
        throw new RuntimeException('Puan dağılımı hesaplanamadı.');
    }
    $remainders = [];
    $allocatedPoints = 0;
    foreach ($units as $index => &$unit) {
        $exactPoints = $unit['weighted_score'] / $scoreTotal * 10000;
        $unit['points'] = (int)floor($exactPoints);
        $unit['share_percent'] = $exactPoints / 100;
        $allocatedPoints += $unit['points'];
        $remainders[] = ['index' => $index, 'fraction' => $exactPoints - $unit['points']];
    }
    unset($unit);
    usort($remainders, static fn(array $left, array $right): int => $right['fraction'] <=> $left['fraction'] ?: $left['index'] <=> $right['index']);
    for ($remaining = 10000 - $allocatedPoints, $i = 0; $remaining > 0; $remaining--, $i++) {
        $units[$remainders[$i % count($remainders)]['index']]['points']++;
    }
    return ['criteria' => $criteria, 'weights' => $weights, 'weight_total' => $weightTotal, 'units' => $units];
}

function render_project_card(array $project): void
{
    ?>
    <article class="listing-card">
      <div class="listing-visual"><span class="listing-symbol" aria-hidden="true">⌂</span><span class="listing-status">Kat karşılığı</span></div>
      <div class="listing-content">
        <p class="listing-location"><?= e($project['city'] ?? '') ?> · <?= e($project['district'] ?? '') ?></p>
        <h3><a href="?page=ilan&amp;id=<?= e($project['id'] ?? '') ?>"><?= e($project['title'] ?? '') ?></a></h3>
        <p class="listing-description"><?= e(mb_strimwidth((string)($project['description'] ?? ''), 0, 132, '…', 'UTF-8')) ?></p>
        <div class="listing-facts"><span><?= number_format((float)($project['area'] ?? 0), 0, ',', '.') ?> m²</span><span><?= e($project['neighborhood'] ?? '') ?></span></div>
        <a class="text-link" href="?page=ilan&amp;id=<?= e($project['id'] ?? '') ?>">İlan detayını gör <span aria-hidden="true">→</span></a>
      </div>
    </article>
    <?php
}
