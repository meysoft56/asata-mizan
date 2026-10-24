<?php
declare(strict_types=1);

require __DIR__ . '/inc/app.php';

$page = (string)($_GET['page'] ?? 'home');
$routes = ['home', 'ilanlar', 'ilan', 'proje-ekle', 'fizibilite', 'serefiye', 'admin', 'giris', 'kayit', 'uye-giris', 'hesabim', 'cikis', 'kurulum'];
if (!in_array($page, $routes, true)) {
    http_response_code(404);
    $page = '404';
}
$error = '';
$fairnessQueryWeights = is_array($_GET['weight'] ?? null) ? $_GET['weight'] : [];
$fairnessQueryNames = is_array($_GET['unit_name'] ?? null) ? $_GET['unit_name'] : [];
$rawFairnessScores = is_array($_GET['score'] ?? null) ? $_GET['score'] : [];
$fairnessQueryScores = [];
foreach (['floor', 'facade', 'daylight', 'view', 'noise', 'balcony', 'parking'] as $criterionKey) {
    $criterionScores = $rawFairnessScores[$criterionKey] ?? [];
    $fairnessQueryScores[$criterionKey] = is_array($criterionScores) ? $criterionScores : [];
}
$fairnessExportUrl = '?' . http_build_query(array_merge($_GET, ['export' => 'csv']));
$feasibilityExportUrl = '?' . http_build_query(array_merge($_GET, ['export' => 'csv']));
$fairness = null;
if ($page === 'serefiye' && isset($_GET['calculate'])) {
    try {
        $fairness = calculate_unit_fairness($_GET);
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}
if ($page === 'serefiye' && ($_GET['export'] ?? '') === 'csv' && $fairness !== null) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="asata-mizan-serefiye-endeksi.csv"');
    $stream = fopen('php://output', 'w');
    if ($stream === false) {
        throw new RuntimeException('CSV çıktısı başlatılamadı.');
    }
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, ['ASATA MIZAN - Şerefiye karşılaştırması (ön çalışma)'], ';', '"', '');
    fputcsv($stream, ['Kriter', 'Ağırlık (%)'], ';', '"', '');
    foreach ($fairness['criteria'] as $key => $criterion) {
        fputcsv($stream, [$criterion['label'], number_format($fairness['weights'][$key] / $fairness['weight_total'] * 100, 2, '.', '')], ';', '"', '');
    }
    fputcsv($stream, [], ';', '"', '');
    fputcsv($stream, ['Bağımsız bölüm', 'Ağırlıklı puan (1-5)', 'Endeks payı (%)', 'Baz puan / 10.000'], ';', '"', '');
    foreach ($fairness['units'] as $unit) {
        $safeName = preg_match('/^[=+\-@]/u', $unit['name']) ? "'" . $unit['name'] : $unit['name'];
        fputcsv($stream, [$safeName, number_format($unit['weighted_score'], 4, '.', ''), number_format($unit['share_percent'], 4, '.', ''), $unit['points']], ';', '"', '');
    }
    fclose($stream);
    exit;
}

$notice = '';
$csrf = csrf_token();
$member = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        $error = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'submit_project') {
                $projectInput = $_POST;
                if ($member && !in_array($member['role'] ?? '', ['malik', 'gelistirici', 'emlakci'], true)) {
                    throw new RuntimeException('Proje başvurusu malik, proje geliştirici veya emlak danışmanı hesabıyla gönderilebilir.');
                }
                if ($member) {
                    $projectInput['applicant_role'] = $member['role'];
                }
                $project = validate_project($projectInput);
                $project['id'] = bin2hex(random_bytes(8));
                $project['created_at'] = date(DATE_ATOM);
                $project['status'] = 'pending';
                $project['review_status'] = 'submitted';
                $project['review_note'] = '';
                $project['demo'] = false;
                $project['submitter_id'] = $member['id'] ?? '';
                $sourceDate = date('Y-m-d');
                $project['field_sources'] = [];
                foreach (['title', 'area', 'city', 'district', 'neighborhood', 'zoning', 'ownership_status', 'current_building', 'intended_use', 'budget_range', 'target_timeline', 'market_assumptions', 'share', 'description'] as $field) {
                    $project['field_sources'][$field] = [
                        'source_type' => 'applicant_statement',
                        'provided_by' => $member['id'] ?? $project['contact_name'],
                        'source_date' => $sourceDate,
                        'verification_status' => 'unverified',
                    ];
                }
                $project['activity'] = [[
                    'action' => 'submitted',
                    'actor_id' => $member['id'] ?? '',
                    'actor_name' => $member['name'] ?? $project['contact_name'],
                    'at' => $project['created_at'],
                    'from' => '',
                    'to' => 'submitted',
                ]];
                $project['milestones'] = array_fill_keys(
                    array_keys(project_milestones()),
                    ['status' => 'pending', 'completed_at' => '']
                );
                json_update('projects.json', static function (array $projects) use ($project): array {
                    array_unshift($projects, $project);
                    return $projects;
                });
                $notice = 'Proje başvurunuz alındı. İnceleme sonrasında yayına alınacaktır.';
            } elseif ($action === 'update_project_submission') {
                if (!$member || !in_array($member['role'] ?? '', ['malik', 'gelistirici', 'emlakci'], true)) {
                    throw new RuntimeException('Başvuruyu güncellemek için başvuru sahibi hesabıyla giriş yapın.');
                }
                $projectId = trim((string)($_POST['project_id'] ?? ''));
                $projectInput = $_POST;
                $projectInput['applicant_role'] = $member['role'];
                $updatedProject = validate_project($projectInput);
                $sourceDate = date('Y-m-d');
                $updatedSources = [];
                foreach (['title', 'area', 'city', 'district', 'neighborhood', 'zoning', 'ownership_status', 'current_building', 'intended_use', 'budget_range', 'target_timeline', 'market_assumptions', 'share', 'description'] as $field) {
                    $updatedSources[$field] = [
                        'source_type' => 'applicant_statement',
                        'provided_by' => $member['id'],
                        'source_date' => $sourceDate,
                        'verification_status' => 'unverified',
                    ];
                }
                json_update('projects.json', static function (array $projects) use ($projectId, $member, $updatedProject, $updatedSources): array {
                    foreach ($projects as &$project) {
                        if (($project['id'] ?? '') !== $projectId || ($project['submitter_id'] ?? '') !== $member['id']) {
                            continue;
                        }
                        if (($project['review_status'] ?? '') !== 'needs_info' || !empty($project['archived_at'])) {
                            throw new RuntimeException('Yalnızca düzeltme istenmiş etkin başvurular güncellenebilir.');
                        }
                        foreach ($updatedProject as $field => $value) {
                            $project[$field] = $value;
                        }
                        $project['field_sources'] = $updatedSources;
                        $project['review_status'] = 'submitted';
                        $project['review_note'] = '';
                        $project['expert_assignments'] = [];
                        unset($project['approved_brief']);
                        $project['status'] = 'pending';
                        $project['updated_at'] = date(DATE_ATOM);
                        $project['activity'] = is_array($project['activity'] ?? null) ? $project['activity'] : [];
                        $project['activity'][] = [
                            'action' => 'resubmitted',
                            'actor_id' => $member['id'],
                            'actor_name' => $member['name'],
                            'at' => $project['updated_at'],
                            'from' => 'needs_info',
                            'to' => 'submitted',
                        ];
                        return $projects;
                    }
                    unset($project);
                    throw new RuntimeException('Size ait güncellenebilir başvuru bulunamadı.');
                });
                header('Location: ?page=hesabim');
                exit;
            } elseif ($action === 'admin_review_project') {
                require_admin();
                $projectId = trim((string)($_POST['project_id'] ?? ''));
                $reviewStatus = trim((string)($_POST['review_status'] ?? ''));
                $reviewNote = trim((string)($_POST['review_note'] ?? ''));
                if (!in_array($reviewStatus, ['needs_info', 'approved', 'rejected'], true) || mb_strlen($reviewNote, 'UTF-8') > 1000) {
                    throw new RuntimeException('İnceleme durumu veya notu geçersiz.');
                }
                if ($reviewStatus !== 'approved' && $reviewNote === '') {
                    throw new RuntimeException('Eksik bilgi veya ret kararı için açıklama yazın.');
                }
                $adminIdentity = read_admin();
                $actorName = (string)($adminIdentity['username'] ?? 'Yönetici');
                json_update('projects.json', static function (array $projects) use ($projectId, $reviewStatus, $reviewNote, $actorName): array {
                    foreach ($projects as &$project) {
                        if (($project['id'] ?? '') !== $projectId || !empty($project['demo']) || !empty($project['archived_at'])) {
                            continue;
                        }
                        $previousStatus = (string)($project['review_status'] ?? 'submitted');
                        $project['review_status'] = $reviewStatus;
                        $project['review_note'] = $reviewNote;
                        $project['reviewed_at'] = date(DATE_ATOM);
                        $project['reviewed_by'] = $actorName;
                        if ($reviewStatus === 'approved') {
                            $approvedFields = [
                                'title', 'city', 'district', 'neighborhood', 'area', 'zoning',
                                'ownership_status', 'current_building', 'intended_use',
                                'budget_range', 'target_timeline', 'market_assumptions',
                                'share', 'description',
                            ];
                            $brief = [];
                            foreach ($approvedFields as $field) {
                                $brief[$field] = $project[$field] ?? '';
                            }
                            $revisions = is_array($project['brief_revisions'] ?? null) ? $project['brief_revisions'] : [];
                            $project['approved_brief'] = [
                                'version' => count($revisions) + 1,
                                'approved_at' => $project['reviewed_at'],
                                'approved_by' => $actorName,
                                'fields' => $brief,
                                'field_sources' => is_array($project['field_sources'] ?? null) ? $project['field_sources'] : [],
                                'downstream_status' => 'concept_only',
                            ];
                            $revisions[] = $project['approved_brief'];
                            $project['brief_revisions'] = $revisions;
                        } else {
                            $project['expert_assignments'] = [];
                            unset($project['approved_brief']);
                            $project['status'] = 'pending';
                        }
                        $project['activity'] = is_array($project['activity'] ?? null) ? $project['activity'] : [];
                        $project['activity'][] = [
                            'action' => 'review_' . $reviewStatus,
                            'actor_id' => 'admin:' . $actorName,
                            'actor_name' => $actorName,
                            'at' => $project['reviewed_at'],
                            'from' => $previousStatus,
                            'to' => $reviewStatus,
                        ];
                        return $projects;
                    }
                    unset($project);
                    throw new RuntimeException('İncelemeye uygun etkin başvuru bulunamadı.');
                });
                $notice = $reviewStatus === 'approved'
                    ? 'Başvuru verisi onaylı brief sürümü olarak kaydedildi. Bu işlem resmi doğrulama değildir.'
                    : 'Başvuru inceleme durumu ve notu kaydedildi.';
            } elseif ($action === 'admin_assign_experts') {
                require_admin();
                $projectId = trim((string)($_POST['project_id'] ?? ''));
                $selectedIds = $_POST['expert_ids'] ?? [];
                if (!is_array($selectedIds)) {
                    throw new RuntimeException('Uzman atamalarını kontrol edin.');
                }
                $eligibleUsers = array_values(array_filter(read_json('users.json'), static fn(array $user): bool => in_array($user['role'] ?? '', ['mimar', 'muhendis', 'muteahhit'], true)));
                $eligibleById = [];
                foreach ($eligibleUsers as $user) {
                    $eligibleById[(string)$user['id']] = $user;
                }
                $expertIds = [];
                foreach ($selectedIds as $selectedId) {
                    if (!is_string($selectedId) || !isset($eligibleById[$selectedId])) {
                        throw new RuntimeException('Yalnızca kayıtlı mimar, mühendis veya müteahhit hesapları atanabilir.');
                    }
                    $expertIds[] = $selectedId;
                }
                $expertIds = array_values(array_unique($expertIds));
                $adminIdentity = read_admin();
                $actorName = (string)($adminIdentity['username'] ?? 'Yönetici');
                json_update('projects.json', static function (array $projects) use ($projectId, $expertIds, $actorName): array {
                    foreach ($projects as &$project) {
                        if (($project['id'] ?? '') !== $projectId || !empty($project['demo']) || !empty($project['archived_at'])) {
                            continue;
                        }
                        if (($project['review_status'] ?? '') !== 'approved' || !is_array($project['approved_brief'] ?? null)) {
                            throw new RuntimeException('Uzman ataması için onaylı brief gerekir.');
                        }
                        $previousIds = is_array($project['expert_assignments'] ?? null) ? $project['expert_assignments'] : [];
                        $project['expert_assignments'] = $expertIds;
                        $project['activity'] = is_array($project['activity'] ?? null) ? $project['activity'] : [];
                        $project['activity'][] = [
                            'action' => 'experts_assigned',
                            'actor_id' => 'admin:' . $actorName,
                            'actor_name' => $actorName,
                            'at' => date(DATE_ATOM),
                            'from' => $previousIds,
                            'to' => $expertIds,
                        ];
                        return $projects;
                    }
                    unset($project);
                    throw new RuntimeException('Uzman atanabilecek etkin proje bulunamadı.');
                });
                $notice = 'Uzman erişim listesi güncellendi.';
            } elseif ($action === 'submit_lead') {
                $lead = validate_lead($_POST);
                $lead['id'] = bin2hex(random_bytes(8));
                $lead['created_at'] = date(DATE_ATOM);
                json_update('leads.json', static function (array $leads) use ($lead): array {
                    array_unshift($leads, $lead);
                    return $leads;
                });
                $notice = 'İlginiz için teşekkürler. Talebiniz kaydedildi.';
            } elseif ($action === 'login') {
                $username = trim((string)($_POST['username'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                $admin = read_admin();
                if ($admin && hash_equals($admin['username'], $username) && password_verify($password, $admin['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['is_admin'] = true;
                    header('Location: ?page=admin');
                    exit;
                }
                $error = 'Kullanıcı adı veya parola hatalı.';
            } elseif ($action === 'register_user') {
                $user = validate_user($_POST);
                $user['id'] = bin2hex(random_bytes(12));
                $user['password_hash'] = password_hash($user['password'], PASSWORD_DEFAULT);
                unset($user['password']);
                $user['created_at'] = date(DATE_ATOM);
                $user['profile_status'] = 'unverified';
                json_update('users.json', static function (array $users) use ($user): array {
                    foreach ($users as $existingUser) {
                        if (mb_strtolower((string)($existingUser['email'] ?? ''), 'UTF-8') === mb_strtolower($user['email'], 'UTF-8')) {
                            throw new RuntimeException('Bu e-posta adresiyle zaten hesap açılmış.');
                        }
                    }
                    array_unshift($users, $user);
                    return $users;
                });
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                session_regenerate_id(true);
                header('Location: ?page=hesabim');
                exit;
            } elseif ($action === 'member_login') {
                $email = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
                $password = (string)($_POST['password'] ?? '');
                $matchedUser = null;
                foreach (read_json('users.json') as $user) {
                    if (mb_strtolower((string)($user['email'] ?? ''), 'UTF-8') === $email && password_verify($password, (string)($user['password_hash'] ?? ''))) {
                        $matchedUser = $user;
                        break;
                    }
                }
                if ($matchedUser) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $matchedUser['id'];
                    $_SESSION['user_role'] = $matchedUser['role'];
                    header('Location: ?page=hesabim');
                    exit;
                }
                $error = 'E-posta veya parola hatalı.';
            } elseif ($action === 'update_profile') {
                if (!$member) {
                    throw new RuntimeException('Profilinizi güncellemek için üye girişi yapın.');
                }
                $profile = validate_profile_update($_POST);
                json_update('users.json', static function (array $users) use ($member, $profile): array {
                    $memberIndex = null;
                    foreach ($users as $index => $user) {
                        if (($user['id'] ?? '') === $member['id']) {
                            $memberIndex = $index;
                        } elseif (mb_strtolower((string)($user['email'] ?? ''), 'UTF-8') === $profile['email']) {
                            throw new RuntimeException('Bu e-posta adresi başka bir hesap tarafından kullanılıyor.');
                        }
                    }
                    if ($memberIndex === null) {
                        throw new RuntimeException('Güncellenecek üye profili bulunamadı.');
                    }
                    foreach ($profile as $field => $value) {
                        $users[$memberIndex][$field] = $value;
                    }
                    return $users;
                });
                $notice = 'Profil bilgileriniz güncellendi.';
                $member = current_user();
            } elseif ($action === 'setup_admin') {
                $setupKey = (string)(getenv('ASATA_ADMIN_SETUP_KEY') ?: '');
                $givenKey = (string)($_POST['setup_key'] ?? '');
                $username = trim((string)($_POST['username'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                if (read_admin()) {
                    throw new RuntimeException('Yönetici hesabı zaten oluşturulmuş.');
                }
                if ($setupKey === '' || !hash_equals($setupKey, $givenKey)) {
                    throw new RuntimeException('Kurulum anahtarı geçersiz veya sunucuda tanımlı değil.');
                }
                if (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $username) || strlen($password) < 12) {
                    throw new RuntimeException('Kullanıcı adı 3–40 karakter olmalı; parola en az 12 karakter olmalıdır.');
                }
                write_json('admin.json', ['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['is_admin'] = true;
                header('Location: ?page=admin');
                exit;
            } elseif ($action === 'admin_project') {
                require_admin();
                $id = (string)($_POST['id'] ?? '');
                $operation = (string)($_POST['operation'] ?? '');
                if (!in_array($operation, ['publish', 'hide', 'archive', 'restore'], true) || $id === '') {
                    throw new RuntimeException('Proje işlemi geçersiz.');
                }
                $adminIdentity = read_admin();
                $actorName = (string)($adminIdentity['username'] ?? 'Yönetici');
                json_update('projects.json', static function (array $projects) use ($id, $operation, $actorName): array {
                    $found = false;
                    foreach ($projects as &$project) {
                        if (($project['id'] ?? '') !== $id) {
                            continue;
                        }
                        if (!empty($project['demo'])) {
                            break;
                        }
                        $previousStatus = (string)($project['status'] ?? 'pending');
                        if ($operation === 'publish' && (($project['review_status'] ?? '') !== 'approved' || !is_array($project['approved_brief'] ?? null))) {
                            throw new RuntimeException('İlanı yayınlamak için başvurunun önce incelenip onaylanması gerekir.');
                        }
                        $nextStatus = match ($operation) {
                            'publish' => 'published',
                            'hide' => 'pending',
                            'archive' => 'archived',
                            'restore' => 'pending',
                        };
                        if ($previousStatus === 'archived' && $operation !== 'restore') {
                            throw new RuntimeException('Arşivlenmiş proje önce geri yüklenmelidir.');
                        }
                        if ($previousStatus !== 'archived' && $operation === 'restore') {
                            throw new RuntimeException('Yalnızca arşivlenmiş proje geri yüklenebilir.');
                        }
                        $project['status'] = $nextStatus;
                        $project['archived_at'] = $operation === 'archive' ? date(DATE_ATOM) : '';
                        $project['activity'] = is_array($project['activity'] ?? null) ? $project['activity'] : [];
                        $project['activity'][] = [
                            'action' => $operation,
                            'actor_id' => 'admin:' . $actorName,
                            'actor_name' => $actorName,
                            'at' => date(DATE_ATOM),
                            'from' => $previousStatus,
                            'to' => $nextStatus,
                        ];
                        $found = true;
                        break;
                    }
                    unset($project);
                    if (!$found) {
                        throw new RuntimeException('Proje bulunamadı veya örnek kayıt değiştirilemez.');
                    }
                    return $projects;
                });
                $notice = $operation === 'archive'
                    ? 'Proje arşivlendi; geçmiş kayıt korunuyor.'
                    : ($operation === 'restore' ? 'Proje arşivden geri yüklendi.' : 'Proje durumu güncellendi.');
            } elseif ($action === 'admin_milestone') {
                require_admin();
                $projectId = (string)($_POST['project_id'] ?? '');
                $milestoneKey = (string)($_POST['milestone'] ?? '');
                $milestoneStatus = (string)($_POST['status'] ?? '');
                $milestones = project_milestones();
                if (!isset($milestones[$milestoneKey]) || !in_array($milestoneStatus, ['pending', 'completed'], true)) {
                    throw new RuntimeException('İnşaat aşaması veya durumu geçersiz.');
                }
                $adminIdentity = read_admin();
                $actorName = (string)($adminIdentity['username'] ?? 'Yönetici');
                json_update('projects.json', static function (array $projects) use ($projectId, $milestoneKey, $milestoneStatus, $actorName): array {
                    $found = false;
                    foreach ($projects as &$project) {
                        if (($project['id'] ?? '') === $projectId && empty($project['demo']) && empty($project['archived_at'])) {
                            $states = $project['milestones'] ?? [];
                            if (!is_array($states)) {
                                $states = [];
                            }
                            $previousStatus = (string)($states[$milestoneKey]['status'] ?? 'pending');
                            $states[$milestoneKey] = [
                                'status' => $milestoneStatus,
                                'completed_at' => $milestoneStatus === 'completed' ? date(DATE_ATOM) : '',
                            ];
                            $project['milestones'] = $states;
                            $project['activity'] = is_array($project['activity'] ?? null) ? $project['activity'] : [];
                            $project['activity'][] = [
                                'action' => 'milestone_' . $milestoneStatus,
                                'actor_id' => 'admin:' . $actorName,
                                'actor_name' => $actorName,
                                'at' => date(DATE_ATOM),
                                'milestone' => $milestoneKey,
                                'from' => $previousStatus,
                                'to' => $milestoneStatus,
                            ];
                            $found = true;
                            break;
                        }
                    }
                    unset($project);
                    if (!$found) {
                        throw new RuntimeException('Düzenlenecek etkin proje bulunamadı; örnek veya arşivlenmiş kayıt değiştirilemez.');
                    }
                    return $projects;
                });
                $notice = 'Aşama takip durumu kaydedildi.';
            } elseif ($action === 'admin_lead') {
                require_admin();
                $id = (string)($_POST['id'] ?? '');
                json_update('leads.json', static function (array $leads) use ($id): array {
                    return array_values(array_filter($leads, static fn(array $lead): bool => ($lead['id'] ?? '') !== $id));
                });
                $notice = 'Talep kaydı silindi.';
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('ASATA MIZAN: ' . $exception->getMessage());
            http_response_code(500);
            $error = 'İşlem tamamlanamadı. Sunucu kayıtlarını kontrol edin.';
        }
    }
}

if ($page === 'cikis' && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    $_SESSION = [];
    session_destroy();
    header('Location: ?page=home');
    exit;
}
if ($page === 'cikis') {
    http_response_code(405);
    header('Allow: POST');
    $error = 'Oturumu kapatmak için sayfadaki güvenli çıkış düğmesini kullanın.';
    $page = 'admin';
}

if ($page === 'admin' && !is_admin()) {
    header('Location: ?page=giris');
    exit;
}
if ($page === 'hesabim' && !current_user()) {
    header('Location: ?page=uye-giris');
    exit;
}

$titleMap = [
    'home' => 'Gayrimenkul Geliştirme Platformu',
    'ilanlar' => 'Arsa ve Proje İlanları',
    'ilan' => 'Proje Detayı',
    'proje-ekle' => 'Proje Başvurusu',
    'fizibilite' => 'Alan ve Maliyet Fizibilitesi',
    'serefiye' => 'Bağımsız Bölüm Şerefiye Karşılaştırması',
    'admin' => 'Yönetim Paneli',
    'giris' => 'Yönetici Girişi',
    'kayit' => 'Rolünüze Özel Hesap Oluşturun',
    'uye-giris' => 'Üye Girişi',
    'hesabim' => 'Mesleki Çalışma Alanı',
    'kurulum' => 'Yönetici Kurulumu',
    '404' => 'Sayfa Bulunamadı',
];
$title = $titleMap[$page] ?? $titleMap['404'];
$projects = read_json('projects.json');
$milestoneDefinitions = project_milestones();
$published = array_values(array_filter($projects, static fn(array $project): bool => ($project['status'] ?? '') === 'published' && ($project['archived_at'] ?? '') === ''));
$leads = read_json('leads.json');
$userRoles = user_roles();
$eligibleExperts = array_values(array_filter(read_json('users.json'), static fn(array $user): bool => in_array($user['role'] ?? '', ['mimar', 'muhendis', 'muteahhit'], true)));
$myProjects = $member
    ? array_values(array_filter($projects, static fn(array $project): bool => ($project['submitter_id'] ?? '') === $member['id'] && ($project['archived_at'] ?? '') === ''))
    : [];
$editableProject = null;
if ($page === 'proje-ekle' && isset($_GET['id']) && $member) {
    foreach ($myProjects as $candidateProject) {
        if (($candidateProject['id'] ?? '') === (string)$_GET['id'] && ($candidateProject['review_status'] ?? '') === 'needs_info') {
            $editableProject = $candidateProject;
            break;
        }
    }
    if ($editableProject === null) {
        http_response_code(404);
        $error = 'Yalnızca size ait ve düzeltme istenmiş başvurular düzenlenebilir.';
    }
}
$intakeValues = $editableProject ?? [];
$intakeValue = static fn(string $field, mixed $default = ''): mixed => $intakeValues[$field] ?? $default;
$assignedBriefs = $member && in_array($member['role'] ?? '', ['mimar', 'muhendis', 'muteahhit'], true)
    ? array_values(array_filter($projects, static fn(array $project): bool => ($project['review_status'] ?? '') === 'approved'
        && empty($project['archived_at'])
        && is_array($project['approved_brief'] ?? null)
        && in_array($member['id'], is_array($project['expert_assignments'] ?? null) ? $project['expert_assignments'] : [], true)))
    : [];
$feasibility = null;
if ($page === 'fizibilite' && isset($_GET['calc'])) {
    try {
        $feasibility = calculate_feasibility($_GET);
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}
if ($page === 'fizibilite' && ($_GET['export'] ?? '') === 'csv' && $feasibility !== null) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="asata-mizan-fizibilite-raporu.csv"');
    $stream = fopen('php://output', 'w');
    if ($stream === false) {
        throw new RuntimeException('CSV çıktısı başlatılamadı.');
    }
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, ['ASATA MIZAN - Ön fizibilite raporu'], ';', '"', '');
    fputcsv($stream, ['Rapor tarihi', date('Y-m-d H:i:s')], ';', '"', '');
    fputcsv($stream, ['Değer kaynağı', 'Kullanıcı girdileri; resmi veya piyasa verisiyle doğrulanmamıştır.'], ';', '"', '');
    fputcsv($stream, [], ';', '"', '');

    $inputLabels = [
        'parcel_area' => ['Parsel alanı', 'm²'],
        'kaks' => ['KAKS / emsal', ''],
        'extra_area_ratio' => ['Emsal dışı alan varsayımı', '%'],
        'basement_area' => ['Varsayımsal bodrum brüt alanı', 'm²'],
        'saleable_ratio' => ['Satılabilir alan / emsal alanı', '%'],
        'construction_unit_cost' => ['Brüt alan birim maliyeti', 'TL/m²'],
        'other_costs' => ['Diğer proje giderleri', 'TL'],
        'sale_unit_price' => ['Satış birim fiyatı', 'TL/m²'],
        'target_margin' => ['Hedef müteahhit kâr oranı', '%'],
        'concrete_per_sqm' => ['Beton katsayısı', 'm³/brüt m²'],
        'steel_kg_per_sqm' => ['Donatı çeliği katsayısı', 'kg/brüt m²'],
        'formwork_per_sqm' => ['Kalıp katsayısı', 'm²/brüt m²'],
        'masonry_per_sqm' => ['Duvar katsayısı', 'm²/brüt m²'],
    ];
    fputcsv($stream, ['Girdiler', 'Değer', 'Birim'], ';', '"', '');
    foreach ($inputLabels as $key => [$label, $unit]) {
        fputcsv($stream, [$label, number_format($feasibility['inputs'][$key], 4, '.', ''), $unit], ';', '"', '');
    }

    fputcsv($stream, [], ';', '"', '');
    fputcsv($stream, ['Hesap özeti', 'Değer', 'Birim'], ';', '"', '');
    foreach ([
        ['Emsale dahil alan', $feasibility['net_area'], 'm²'],
        ['Emsal dışı alan varsayımı', $feasibility['extra_area'], 'm²'],
        ['Model brüt alanı', $feasibility['gross_area'], 'm²'],
        ['Satılabilir alan varsayımı', $feasibility['saleable_area'], 'm²'],
        ['Toplam proje maliyeti varsayımı', $feasibility['construction_cost'], 'TL'],
        ['Toplam satış değeri varsayımı', $feasibility['gross_development_value'], 'TL'],
        ['Teorik arsa payı göstergesi', $feasibility['owner_share'], '%'],
        ['Arsa sahibi alan eşdeğeri', $feasibility['owner_area'], 'm²'],
    ] as [$label, $value, $unit]) {
        fputcsv($stream, [$label, number_format($value, 4, '.', ''), $unit], ';', '"', '');
    }

    fputcsv($stream, [], ';', '"', '');
    fputcsv($stream, ['Metraj tahminleri', 'Miktar', 'Birim'], ';', '"', '');
    foreach ([
        ['Beton', $feasibility['materials']['concrete'], 'm³', 'concrete_per_sqm'],
        ['Donatı çeliği', $feasibility['materials']['steel_tons'], 'ton', 'steel_kg_per_sqm'],
        ['Kalıp', $feasibility['materials']['formwork'], 'm²', 'formwork_per_sqm'],
        ['Duvar', $feasibility['materials']['masonry'], 'm²', 'masonry_per_sqm'],
    ] as [$label, $quantity, $unit, $inputKey]) {
        $value = $feasibility['inputs'][$inputKey] > 0 ? number_format($quantity, 4, '.', '') : 'Katsayı girilmedi';
        fputcsv($stream, [$label, $value, $unit], ';', '"', '');
    }

    fputcsv($stream, [], ';', '"', '');
    fputcsv($stream, ['Hassasiyet senaryoları', 'Model maliyeti (TL)', 'Model satış değeri (TL)', 'Teorik arsa payı (%)'], ';', '"', '');
    foreach ($feasibility['scenarios'] as $scenario) {
        fputcsv($stream, [
            $scenario['name'],
            number_format($scenario['cost'], 2, '.', ''),
            number_format($scenario['value'], 2, '.', ''),
            number_format($scenario['owner_share'], 4, '.', ''),
        ], ';', '"', '');
    }

    fputcsv($stream, [], ';', '"', '');
    fputcsv($stream, ['Sınırlamalar', 'Bu çıktı ön fizibilite senaryosudur; imar hakkı, değerleme, teknik metraj, finansman, vergi, hukuki görüş veya taraflar arası paylaşım taahhüdü değildir.'], ';', '"', '');
    fclose($stream);
    exit;
}
$query = trim((string)($_GET['q'] ?? ''));
$cityFilter = trim((string)($_GET['city'] ?? ''));
$filtered = array_values(array_filter($published, static function (array $project) use ($query, $cityFilter): bool {
    $searchable = mb_strtolower(implode(' ', [
        $project['title'] ?? '', $project['city'] ?? '', $project['district'] ?? '',
        $project['neighborhood'] ?? '', $project['description'] ?? '',
    ]), 'UTF-8');
    return ($query === '' || mb_strpos($searchable, mb_strtolower($query, 'UTF-8')) !== false)
        && ($cityFilter === '' || ($project['city'] ?? '') === $cityFilter);
}));
$selectedProject = null;
if ($page === 'ilan') {
    $projectId = (string)($_GET['id'] ?? '');
    foreach ($published as $project) {
        if (($project['id'] ?? '') === $projectId) {
            $selectedProject = $project;
            break;
        }
    }
    if (!$selectedProject) {
        http_response_code(404);
        $page = '404';
        $title = $titleMap['404'];
    }
}
$cities = array_values(array_unique(array_filter(array_map(static fn(array $project): string => (string)($project['city'] ?? ''), $published))));
sort($cities);

require __DIR__ . '/inc/header.php';
?>
<main id="icerik">
<?php if ($notice !== ''): ?><div class="wrap alert success" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="wrap alert error" role="alert"><?= e($error) ?></div><?php endif; ?>

<?php if ($page === 'home'): ?>
  <section class="hero">
    <div class="wrap hero-grid">
      <div>
        <span class="eyebrow">Kat karşılığı geliştirmede yeni standart</span>
        <h1>Arsanızın potansiyeli, <em>hakkaniyetle</em> buluşsun.</h1>
        <p class="hero-copy">İmar, maliyet ve paylaşım senaryolarını tek yerde değerlendirin. Projeleri keşfedin, teklifleri karşılaştırın ve kararınızı görünür verilere dayandırın.</p>
        <div class="hero-buttons">
          <a class="button button-lime" href="?page=ilanlar">Projeleri keşfet <span aria-hidden="true">→</span></a>
          <a class="button button-outline" href="?page=proje-ekle">Proje başvurusu yap</a>
          <a class="button button-outline" href="?page=fizibilite">Fizibilite motoru</a>
          <a class="button button-outline" href="?page=serefiye">Şerefiye karşılaştırması</a>
        </div>
        <div class="trust-line"><span class="trust-dot"></span> Şeffaf ön fizibilite. Taraflar için ortak zemin.</div>
      </div>
      <div class="calc-card">
        <div class="calc-head"><div><h2>Hızlı alan senaryosu</h2><p>İmar verilerinizi girerek tahmini alanı görün.</p></div><span class="live-tag">Ön hesaplama</span></div>
        <form id="quickCalc" onsubmit="return false">
          <div class="form-grid">
            <div class="field"><label for="area">Parsel alanı (m²)</label><input id="area" type="number" min="1" step="0.01" value="1000"></div>
            <div class="field"><label for="floor">Emsal (KAKS)</label><input id="floor" type="number" min="0" max="10" step="0.01" value="1.20"></div>
          </div>
        </form>
        <div class="calc-divider"></div>
        <div class="result-top"><div><div class="result-label">Emsale dahil tahmini alan</div><div class="result-number"><span id="resultArea">1.200</span> <span>m²</span></div></div><span class="split-pill">Ön fizibilite</span></div>
        <p class="calc-note"><b>Bilgilendirme:</b> Sonuç; çekme mesafeleri, plan notları, emsal dışı alanlar ve ilgili mevzuatın yerine geçmez. Resmî imar verileriyle uzman doğrulaması gerekir.</p>
      </div>
    </div>
  </section>
<?php elseif ($page === 'fizibilite' || $page === 'serefiye'): ?>
  <?php if ($page === 'fizibilite'): ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow">Mizan hesaplama çekirdeği</span><h1>Varsayımları <em>görünür</em> kılın.</h1><p>Alan, yaklaşık maliyet, satış değeri ve hedef kâr girdileriyle ön fizibilite senaryosu oluşturun. Bu araç imar hakkını, ekspertiz değerini veya sözleşme payını tespit etmez.</p></div></section>
  <?php endif; ?>
  <?php if ($page === 'serefiye'): ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow">Bağımsız bölüm karşılaştırması</span><h1>Paylaşımı <em>ölçütlerle</em> görünür kılın.</h1><p>Kat, yön, gün ışığı, açıklık, gürültü konforu ve donatı ölçütlerini 1–5 arasında puanlayın. Ağırlıklı endeks eşit 10.000 baz puana dönüştürülür.</p></div></section>
  <section class="section"><div class="wrap">
    <form class="form-card fairness-form" method="get"><input type="hidden" name="page" value="serefiye"><input type="hidden" name="calculate" value="1">
      <div class="section-heading"><span class="eyebrow">1 · Kriter ağırlıkları</span><h2 class="section-title">Taraflarla önceden mutabık kalın</h2><p>Ağırlıklar yüzde puanıdır ve hesaplamada toplamlarına göre normalize edilir. Başlangıç önerisi toplamı %100’dür.</p></div>
      <div class="weight-grid"><?php foreach (['floor' => 'Kat', 'facade' => 'Cephe / yön', 'daylight' => 'Gün ışığı', 'view' => 'Manzara / açıklık', 'noise' => 'Gürültü konforu', 'balcony' => 'Balkon / teras', 'parking' => 'Otopark / depo'] as $key => $label): $defaultWeight = ['floor'=>20,'facade'=>15,'daylight'=>15,'view'=>15,'noise'=>15,'balcony'=>10,'parking'=>10][$key]; $weightValue = $fairnessQueryWeights[$key] ?? $defaultWeight; if (!is_scalar($weightValue)) { $weightValue = $defaultWeight; } ?><div class="field"><label for="weight-<?= e($key) ?>"><?= e($label) ?> ağırlığı (%)</label><input id="weight-<?= e($key) ?>" name="weight[<?= e($key) ?>]" type="number" min="0" max="100" step="1" value="<?= e($weightValue) ?>"></div><?php endforeach; ?></div>
      <div class="section-heading engine-subhead"><span class="eyebrow">2 · Bağımsız bölüm puanları</span><h2 class="section-title">Her bölüm için 1–5 seçin</h2><p>1 düşük, 5 yüksek göreli değer anlamındadır. Gürültü kriterinde 5 = daha sessiz/konforlu. Boş ad bırakılan satırlar dikkate alınmaz.</p></div>
      <div class="workspace-table-wrap"><table class="workspace-table fairness-table"><thead><tr><th>Bağımsız bölüm</th><?php foreach (['floor'=>'Kat','facade'=>'Cephe','daylight'=>'Gün ışığı','view'=>'Manzara','noise'=>'Sessizlik','balcony'=>'Balkon','parking'=>'Otopark/depo'] as $key => $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead><tbody>
        <?php for ($index = 0; $index < 8; $index++): $unitName = $fairnessQueryNames[$index] ?? ''; if (!is_scalar($unitName)) { $unitName = ''; } ?><tr><td><input class="unit-name-field" name="unit_name[<?= $index ?>]" maxlength="60" value="<?= e($unitName) ?>" placeholder="Örn. Daire 1"></td><?php foreach (['floor'=>'Kat','facade'=>'Cephe','daylight'=>'Gün ışığı','view'=>'Manzara','noise'=>'Sessizlik','balcony'=>'Balkon','parking'=>'Otopark/depo'] as $key => $label): $selectedScore = $fairnessQueryScores[$key][$index] ?? ''; if (!is_scalar($selectedScore)) { $selectedScore = ''; } ?><td><select name="score[<?= e($key) ?>][<?= $index ?>]" aria-label="<?= e($label) ?> puanı"><option value="">–</option><?php for ($score = 1; $score <= 5; $score++): ?><option value="<?= $score ?>" <?= (string)$selectedScore === (string)$score ? 'selected' : '' ?>><?= $score ?></option><?php endfor; ?></select></td><?php endforeach; ?></tr><?php endfor; ?>
      </tbody></table></div>
      <p class="workspace-hint warning">Bu araç gerçek piyasa değeri, hukuki hak, malik payı veya sözleşme dağılımı belirlemez. Puanları taraflar birlikte belirlemeli; ölçütleri, ağırlıkları ve dayanaklarını kayıt altına almalıdır. Adalet veya uyuşmazlıksız sonuç garantisi verilmez.</p>
      <button class="button button-dark" type="submit">Şerefiye endeksini hesapla</button>
    </form>
    <?php if ($fairness): ?>
      <section class="engine-results">
        <div class="section-heading"><span class="eyebrow">Normalize edilmiş sonuç</span><h2 class="section-title">10.000 baz puanlık karşılaştırma</h2><p>Ağırlıklı puan = Σ (kriter puanı × normalize edilmiş ağırlık). Her bölümün puanı toplamı tam 10.000 olacak şekilde yuvarlanmıştır.</p></div>
        <article class="detail-panel"><div class="workspace-table-wrap"><table class="workspace-table"><thead><tr><th>Bölüm</th><th>Ağırlıklı puan (1–5)</th><th>Endeks payı</th><th>Baz puan</th><th>Görsel pay</th></tr></thead><tbody><?php foreach ($fairness['units'] as $unit): ?><tr><td><strong><?= e($unit['name']) ?></strong></td><td><?= number_format($unit['weighted_score'], 2, ',', '.') ?></td><td>%<?= number_format($unit['share_percent'], 2, ',', '.') ?></td><td><strong><?= number_format($unit['points'], 0, ',', '.') ?></strong></td><td><div class="fairness-bar"><span style="width:<?= e(number_format($unit['share_percent'], 4, '.', '')) ?>%"></span></div></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="3">Toplam</th><th><?= number_format(array_sum(array_column($fairness['units'], 'points')), 0, ',', '.') ?></th><th>10.000</th></tr></tfoot></table></div></article>
        <p class="workspace-hint">Bu, kullanıcı puanlarına dayalı ağırlıklı endekstir; Saaty ikili karşılaştırma matrisiyle tutarlılık oranı hesaplayan tam AHP uygulaması değildir. Bağımsız değerleme ve taraflar arasında yazılı mutabakat yerine geçmez.</p>
        <a class="button button-dark" href="<?= e($fairnessExportUrl) ?>">CSV raporunu indir</a>
      </section>
    <?php endif; ?>
  </div></section>
  <?php endif; ?>
  <?php if ($page === 'fizibilite'): ?>
  <section class="section"><div class="wrap">
    <form class="form-card engine-form" method="get"><input type="hidden" name="page" value="fizibilite"><input type="hidden" name="calc" value="1">
      <div class="section-heading"><span class="eyebrow">1 · Parsel ve alan varsayımları</span><h2 class="section-title">Proje girdileri</h2><p>Değerleri elinizdeki resmî belge ve profesyonel maliyet çalışmasına göre girin. Ön değerler yalnızca örnek hesaplama içindir.</p></div>
      <div class="form-grid">
        <div class="field"><label for="parcelArea">Parsel alanı (m²)</label><input id="parcelArea" name="parcel_area" type="number" min="1" max="10000000" step="0.01" value="<?= e($_GET['parcel_area'] ?? 1000) ?>" required></div>
        <div class="field"><label for="kaks">KAKS / emsal</label><input id="kaks" name="kaks" type="number" min="0" max="10" step="0.01" value="<?= e($_GET['kaks'] ?? 1.2) ?>" required></div>
        <div class="field"><label for="extraAreaRatio">Emsal dışı alan varsayımı (%)</label><input id="extraAreaRatio" name="extra_area_ratio" type="number" min="0" max="30" step="0.1" value="<?= e($_GET['extra_area_ratio'] ?? 0) ?>"><small>Varsayılan 0. Yalnızca ilgili parsel ve güncel mevzuat için uygunluğu resmen doğrulanırsa değiştirin.</small></div>
        <div class="field"><label for="basementArea">Varsayımsal bodrum brüt alanı (m²)</label><input id="basementArea" name="basement_area" type="number" min="0" max="50000000" step="0.01" value="<?= e($_GET['basement_area'] ?? 0) ?>"></div>
        <div class="field"><label for="saleableRatio">Satılabilir alan / emsal alanı (%)</label><input id="saleableRatio" name="saleable_ratio" type="number" min="1" max="100" step="0.1" value="<?= e($_GET['saleable_ratio'] ?? 82) ?>"></div>
      </div>
      <div class="section-heading engine-subhead"><span class="eyebrow">2 · Finans varsayımları</span><h2 class="section-title">Maliyet, satış ve hedef getiri</h2></div>
      <div class="form-grid">
        <div class="field"><label for="cost">Brüt alan birim maliyeti (TL/m²)</label><input id="cost" name="construction_unit_cost" type="number" min="0" max="100000000" step="100" value="<?= e($_GET['construction_unit_cost'] ?? 30000) ?>"></div>
        <div class="field"><label for="otherCosts">Diğer proje giderleri (TL)</label><input id="otherCosts" name="other_costs" type="number" min="0" max="1000000000000" step="1000" value="<?= e($_GET['other_costs'] ?? 4000000) ?>"></div>
        <div class="field"><label for="salePrice">Satılabilir alan satış varsayımı (TL/m²)</label><input id="salePrice" name="sale_unit_price" type="number" min="0" max="1000000000" step="100" value="<?= e($_GET['sale_unit_price'] ?? 70000) ?>"></div>
        <div class="field"><label for="margin">Hedef müteahhit kâr oranı (%)</label><input id="margin" name="target_margin" type="number" min="0" max="100" step="0.1" value="<?= e($_GET['target_margin'] ?? 25) ?>"></div>
      </div>
      <div class="section-heading engine-subhead"><span class="eyebrow">3 · İsteğe bağlı metraj katsayıları</span><h2 class="section-title">Mühendislik girdileri</h2><p>Katsayıları proje mühendisi/metraj çalışmasından alın. Boş veya sıfır bırakılan kalemler için miktar üretilmez.</p></div>
      <div class="form-grid">
        <div class="field"><label for="concrete">Beton (m³ / brüt m²)</label><input id="concrete" name="concrete_per_sqm" type="number" min="0" max="20" step="0.001" value="<?= e($_GET['concrete_per_sqm'] ?? 0) ?>"></div>
        <div class="field"><label for="steel">Donatı çeliği (kg / brüt m²)</label><input id="steel" name="steel_kg_per_sqm" type="number" min="0" max="2000" step="0.1" value="<?= e($_GET['steel_kg_per_sqm'] ?? 0) ?>"></div>
        <div class="field"><label for="formwork">Kalıp (m² / brüt m²)</label><input id="formwork" name="formwork_per_sqm" type="number" min="0" max="50" step="0.01" value="<?= e($_GET['formwork_per_sqm'] ?? 0) ?>"></div>
        <div class="field"><label for="masonry">Duvar (m² / brüt m²)</label><input id="masonry" name="masonry_per_sqm" type="number" min="0" max="50" step="0.01" value="<?= e($_GET['masonry_per_sqm'] ?? 0) ?>"></div>
      </div>
      <p class="workspace-hint warning">Hesap; KAKS, çekme mesafesi, plan notu, bodrum hakkı, emsal dışı alan uygunluğu, finansman, vergi, arsa maliyeti ve bölgesel satış gerçekleşmelerini bağımsız olarak doğrulamaz. Resmî imar belgesi, mühendislik metrajı ve lisanslı değerleme yerine kullanılamaz.</p>
      <button class="button button-dark" type="submit">Fizibilite senaryosunu hesapla</button>
    </form>
    <?php if ($feasibility): ?>
      <section class="engine-results">
        <div class="section-heading"><span class="eyebrow">Hesaplama sonucu</span><h2 class="section-title">Gösterge niteliğinde senaryo özeti</h2><p>Formül varsayımlarıyla hesaplandı; tüm tutarlar kullanıcı girdisidir.</p><a class="button button-dark" href="<?= e($feasibilityExportUrl) ?>">CSV raporu indir</a></div>
        <div class="engine-metrics">
          <article class="engine-metric"><span>Emsale dahil alan</span><strong><?= number_format($feasibility['net_area'], 2, ',', '.') ?> m²</strong></article>
          <article class="engine-metric"><span>Model brüt alanı</span><strong><?= number_format($feasibility['gross_area'], 2, ',', '.') ?> m²</strong></article>
          <article class="engine-metric"><span>Satılabilir alan varsayımı</span><strong><?= number_format($feasibility['saleable_area'], 2, ',', '.') ?> m²</strong></article>
          <article class="engine-metric"><span>Toplam proje maliyet varsayımı</span><strong><?= number_format($feasibility['construction_cost'], 0, ',', '.') ?> TL</strong></article>
          <article class="engine-metric"><span>Toplam satış değeri varsayımı</span><strong><?= number_format($feasibility['gross_development_value'], 0, ',', '.') ?> TL</strong></article>
          <article class="engine-metric highlight"><span>Teorik arsa payı göstergesi</span><strong>%<?= number_format($feasibility['owner_share'], 1, ',', '.') ?></strong><small><?= number_format($feasibility['owner_area'], 2, ',', '.') ?> m² eşdeğer satılabilir alan</small></article>
        </div>
        <div class="engine-result-grid">
          <article class="detail-panel"><h2>Senaryo hassasiyeti</h2><div class="workspace-table-wrap"><table class="workspace-table"><thead><tr><th>Senaryo</th><th>Model maliyeti</th><th>Model satış değeri</th><th>Teorik arsa payı</th></tr></thead><tbody><?php foreach ($feasibility['scenarios'] as $scenario): ?><tr><td><?= e($scenario['name']) ?></td><td><?= number_format($scenario['cost'], 0, ',', '.') ?> TL</td><td><?= number_format($scenario['value'], 0, ',', '.') ?> TL</td><td>%<?= number_format($scenario['owner_share'], 1, ',', '.') ?></td></tr><?php endforeach; ?></tbody></table></div></article>
          <article class="detail-panel"><h2>Katsayıya bağlı yaklaşık metraj</h2><dl class="facts"><div><dt>Beton</dt><dd><?= $feasibility['inputs']['concrete_per_sqm'] > 0 ? number_format($feasibility['materials']['concrete'], 2, ',', '.') . ' m³' : 'Katsayı girilmedi' ?></dd></div><div><dt>Donatı çeliği</dt><dd><?= $feasibility['inputs']['steel_kg_per_sqm'] > 0 ? number_format($feasibility['materials']['steel_tons'], 2, ',', '.') . ' ton' : 'Katsayı girilmedi' ?></dd></div><div><dt>Kalıp alanı</dt><dd><?= $feasibility['inputs']['formwork_per_sqm'] > 0 ? number_format($feasibility['materials']['formwork'], 2, ',', '.') . ' m²' : 'Katsayı girilmedi' ?></dd></div><div><dt>Duvar alanı</dt><dd><?= $feasibility['inputs']['masonry_per_sqm'] > 0 ? number_format($feasibility['materials']['masonry'], 2, ',', '.') . ' m²' : 'Katsayı girilmedi' ?></dd></div></dl></article>
        </div>
        <p class="workspace-hint">Formül: brüt alan = emsal alan + kullanıcı tarafından belirtilen emsal dışı alan + bodrum varsayımı. Teorik arsa payı göstergesi = max(0, min(100, ((satış değeri − proje maliyeti × (1 + hedef kâr)) / satış değeri × 100))). Bu sonuç anlaşma önerisi değildir.</p>
      </section>
    <?php endif; ?>
  </div></section>
  <section class="section">
    <div class="wrap">
      <div class="section-heading"><span class="eyebrow">Pazaryeri</span><h2 class="section-title">Yayındaki projeler</h2><p>Yayına alınmış arsa geliştirme fırsatlarını keşfedin. Her ilanın bilgileri başvuru sahibi tarafından sunulur ve resmî doğrulama anlamına gelmez.</p></div>
      <?php if ($published): ?>
        <div class="listing-grid"><?php foreach (array_slice($published, 0, 3) as $project) { render_project_card($project); } ?></div>
      <?php else: ?>
        <div class="empty-state"><h3>Henüz yayında ilan yok</h3><p>İlk proje başvurusunu siz oluşturabilirsiniz.</p><a class="button button-dark" href="?page=proje-ekle">Proje ekle</a></div>
      <?php endif; ?>
      <div class="center-action"><a class="button button-dark" href="?page=ilanlar">Tüm ilanları görüntüle <span aria-hidden="true">→</span></a></div>
    </div>
  </section>
  <section class="section soft">
    <div class="wrap feature-grid">
      <article class="feature-card"><span class="feature-icon">⌂</span><h3>İmar ve alan senaryoları</h3><p>Parsel alanı, TAKS ve KAKS gibi girdilerle ön hesaplama yapın; resmî imar belgesiyle doğrulayın.</p></article>
      <article class="feature-card"><span class="feature-icon">◷</span><h3>Teklif ve maliyet kıyaslama</h3><p>Paylaşım, maliyet, takvim ve güvence koşullarını aynı kontrol listesiyle değerlendirin.</p></article>
      <article class="feature-card"><span class="feature-icon">▤</span><h3>Şeffaf başvuru süreci</h3><p>Başvurular yönetici incelemesinden geçer; kullanıcı bilgileri kamuya açık ilanlarda gösterilmez.</p></article>
    </div>
  </section>
  <?php endif; ?>
<?php elseif ($page === 'ilanlar'): ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow">Proje pazaryeri</span><h1>Arsa ve geliştirme <em>fırsatları.</em></h1><p>Yayındaki projeleri konum veya anahtar kelimeye göre inceleyin.</p></div></section>
  <section class="section"><div class="wrap">
    <form class="filter-bar" method="get"><input type="hidden" name="page" value="ilanlar"><label class="sr-only" for="search">Ara</label><input id="search" name="q" value="<?= e($query) ?>" placeholder="İl, ilçe veya proje ara"><label class="sr-only" for="city">İl seçin</label><select id="city" name="city"><option value="">Tüm iller</option><?php foreach ($cities as $city): ?><option value="<?= e($city) ?>" <?= $cityFilter === $city ? 'selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?></select><button class="button button-dark">Filtrele</button></form>
    <p class="result-count"><?= count($filtered) ?> proje listeleniyor</p>
    <?php if ($filtered): ?><div class="listing-grid"><?php foreach ($filtered as $project) { render_project_card($project); } ?></div><?php else: ?><div class="empty-state"><h3>Sonuç bulunamadı</h3><p>Arama ölçütlerini değiştirin veya yeni proje başvurusu oluşturun.</p><a class="button button-dark" href="?page=proje-ekle">Proje ekle</a></div><?php endif; ?>
  </div></section>
<?php elseif ($page === 'ilan' && $selectedProject): ?>
  <section class="page-hero"><div class="wrap"><a class="back-link" href="?page=ilanlar">← Projelere dön</a><span class="eyebrow"><?= e($selectedProject['city']) ?> · <?= e($selectedProject['district']) ?></span><h1><?= e($selectedProject['title']) ?></h1><p><?= e($selectedProject['neighborhood']) ?> · <?= number_format((float)$selectedProject['area'], 0, ',', '.') ?> m² parsel</p></div></section>
  <section class="section"><div class="wrap detail-grid"><article class="detail-main"><div class="detail-panel"><h2>Proje özeti</h2><p><?= nl2br(e($selectedProject['description'])) ?></p></div><div class="detail-panel"><h2>Başvuru ve doğrulama notu</h2><p>Bu ilan, kullanıcı tarafından gönderilen bilgilere dayanır. Tapu, imar, mülkiyet ve şirket bilgileri platform tarafından resmî olarak doğrulanmış sayılmaz. Her türlü karar öncesinde ilgili kurum kayıtlarını ve profesyonel görüşü kontrol edin.</p></div></article><aside class="detail-panel detail-side"><span class="live-tag">Yayında</span><dl class="facts"><div><dt>İl / İlçe</dt><dd><?= e($selectedProject['city']) ?> / <?= e($selectedProject['district']) ?></dd></div><div><dt>Mahalle</dt><dd><?= e($selectedProject['neighborhood']) ?></dd></div><div><dt>Parsel alanı</dt><dd><?= number_format((float)$selectedProject['area'], 0, ',', '.') ?> m²</dd></div><div><dt>İmar durumu</dt><dd><?= e($selectedProject['zoning'] ?: 'Belirtilmedi') ?></dd></div><div><dt>Paylaşım beklentisi</dt><dd><?= $selectedProject['share'] !== '' ? '%' . e($selectedProject['share']) : 'Görüşülecek' ?></dd></div></dl><a class="button button-lime full-width" href="#talep">İlgileniyorum</a></aside></div></section>
  <section class="section soft" id="talep"><div class="wrap narrow"><div class="section-heading"><span class="eyebrow">İletişim talebi</span><h2 class="section-title">Proje hakkında bilgi isteyin.</h2><p>İletişim bilgileriniz ilan sahibiyle doğrudan paylaşılmaz; talep yönetici paneline kaydedilir.</p></div><form class="form-card" method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="submit_lead"><input type="hidden" name="project_id" value="<?= e($selectedProject['id']) ?>"><input type="hidden" name="project_title" value="<?= e($selectedProject['title']) ?>"><div class="form-grid"><div class="field"><label for="leadName">Ad soyad</label><input id="leadName" name="name" required maxlength="100"></div><div class="field"><label for="leadPhone">Telefon</label><input id="leadPhone" name="phone" type="tel" required maxlength="30"></div><div class="field"><label for="leadEmail">E-posta (isteğe bağlı)</label><input id="leadEmail" name="email" type="email" maxlength="160"></div><div class="field"><label for="leadMessage">Mesajınız</label><input id="leadMessage" name="message" maxlength="500"></div></div><label class="check-consent"><input type="checkbox" name="consent" value="yes" required> Talebimin bu proje için değerlendirilmek üzere kaydedilmesini kabul ediyorum.</label><button class="button button-dark">Bilgi talebi gönder</button></form></div></section>
<?php elseif ($page === 'proje-ekle'): ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow">Proje başvurusu</span><h1>Arsanızı <em>tanıtın.</em></h1><p>Başvuru yönetici incelemesine alınır; onaylanana kadar ilanlar arasında görünmez.</p></div></section>
  <section class="section"><div class="wrap narrow">
    <form class="form-card" method="post">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="<?= $editableProject ? 'update_project_submission' : 'submit_project' ?>">
      <?php if ($editableProject): ?><input type="hidden" name="project_id" value="<?= e($editableProject['id']) ?>"><?php endif; ?>
      <div class="section-heading"><span class="eyebrow">1 · Başvuru sahibi</span><h2 class="section-title"><?= $editableProject ? 'Düzeltme istenen başvuruyu güncelleyin' : 'Temel fırsat bilgisi' ?></h2><p>Malik/arsa sahibi, proje geliştirici veya emlak danışmanı başvuru yapabilir. Üye hesabında rolünüz otomatik alınır.</p></div>
      <?php if (!$member): ?><div class="field"><label for="applicantRole">Başvuru sahibi rolü</label><select id="applicantRole" name="applicant_role" required><option value="">Rol seçin</option><option value="malik">Malik / arsa sahibi</option><option value="gelistirici">Proje geliştirici</option><option value="emlakci">Emlak danışmanı</option></select></div><?php endif; ?>
      <?php if ($editableProject): ?><p class="workspace-hint warning">Yönetici notu: <?= e($editableProject['review_note'] ?? '') ?>. Kaydettiğiniz değişiklikler eski onayı geçersiz kılar ve yeni inceleme sürümü oluşturur.</p><?php endif; ?>
      <div class="section-heading engine-subhead"><span class="eyebrow">2 · Parsel ve mevcut durum</span><h2 class="section-title">Taşınmaz bilgileri</h2></div>
      <div class="form-grid">
        <div class="field"><label for="title">Proje / ilan başlığı</label><input id="title" name="title" value="<?= e($intakeValue('title')) ?>" required maxlength="120" placeholder="Örn. Merkezde kat karşılığı arsa"></div>
        <div class="field"><label for="area">Parsel alanı (m²)</label><input id="area" name="area" type="number" min="1" max="10000000" step="0.01" value="<?= e($intakeValue('area')) ?>" required></div>
        <div class="field"><label for="city">İl</label><input id="city" name="city" value="<?= e($intakeValue('city')) ?>" required maxlength="60"></div>
        <div class="field"><label for="district">İlçe</label><input id="district" name="district" value="<?= e($intakeValue('district')) ?>" required maxlength="80"></div>
        <div class="field"><label for="neighborhood">Mahalle</label><input id="neighborhood" name="neighborhood" value="<?= e($intakeValue('neighborhood')) ?>" required maxlength="100"></div>
        <div class="field"><label for="zoning">İmar bilgisi (varsa)</label><input id="zoning" name="zoning" value="<?= e($intakeValue('zoning')) ?>" maxlength="160" placeholder="Kaynağı ve tarihi açıklamada belirtin"></div>
        <div class="field"><label for="ownershipStatus">Mülkiyet / temsil durumu</label><select id="ownershipStatus" name="ownership_status"><option value="">Belirtilmedi</option><?php foreach (['owner_claim' => 'Malik olduğumu beyan ediyorum', 'representative_claim' => 'Temsilci olduğumu beyan ediyorum', 'agent_authorized' => 'Yetkili emlak danışmanı olduğumu beyan ediyorum', 'unknown' => 'Bilinmiyor / doğrulanmadı'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $intakeValue('ownership_status') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="currentBuilding">Mevcut yapı durumu</label><input id="currentBuilding" name="current_building" value="<?= e($intakeValue('current_building')) ?>" maxlength="120" placeholder="Örn. boş arsa / yapı var / bilinmiyor"></div>
        <div class="field"><label for="intendedUse">Hedeflenen kullanım / proje türü</label><input id="intendedUse" name="intended_use" value="<?= e($intakeValue('intended_use')) ?>" maxlength="160" placeholder="Örn. konut, ticaret, karma"></div>
        <div class="field"><label for="share">Paylaşım beklentisi (%) (isteğe bağlı)</label><input id="share" name="share" type="number" min="0" max="100" step="0.1" value="<?= e($intakeValue('share')) ?>"></div>
      </div>
      <div class="section-heading engine-subhead"><span class="eyebrow">3 · Ticari varsayımlar</span><h2 class="section-title">Hedef ve takvim</h2></div>
      <div class="form-grid">
        <div class="field"><label for="budgetRange">Bütçe aralığı (isteğe bağlı)</label><input id="budgetRange" name="budget_range" value="<?= e($intakeValue('budget_range')) ?>" maxlength="100" placeholder="Aralık veya henüz belirlenmedi"></div>
        <div class="field"><label for="targetTimeline">Hedeflenen takvim</label><input id="targetTimeline" name="target_timeline" value="<?= e($intakeValue('target_timeline')) ?>" maxlength="100" placeholder="Örn. 2027 içinde / henüz belirlenmedi"></div>
        <div class="field full-span"><label for="marketAssumptions">Pazar / satış-kiralama varsayımları</label><textarea id="marketAssumptions" name="market_assumptions" maxlength="1000" rows="3" placeholder="Varsayım ve kaynağı belirtin; gerçek satış verisi olarak sunmayın."><?= e($intakeValue('market_assumptions')) ?></textarea></div>
        <div class="field full-span"><label for="description">Proje açıklaması ve bilgi kaynakları</label><textarea id="description" name="description" required minlength="30" maxlength="3000" rows="6" placeholder="Beyan ettiğiniz bilgilerin kaynağını, belge tarihini ve belirsizlikleri yazın. Hassas belge yüklemeyin."><?= e($intakeValue('description')) ?></textarea></div>
      </div>
      <div class="section-heading engine-subhead"><span class="eyebrow">4 · İletişim ve gönderim</span></div>
      <div class="form-grid">
        <div class="field"><label for="contactName">Başvuru sahibi adı</label><input id="contactName" name="contact_name" value="<?= e($intakeValue('contact_name', $member['name'] ?? '')) ?>" required maxlength="100"></div>
        <div class="field"><label for="contactEmail">İletişim e-postası</label><input id="contactEmail" name="contact_email" type="email" value="<?= e($intakeValue('contact_email', $member['email'] ?? '')) ?>" required maxlength="160"></div>
        <div class="field"><label for="contactPhone">Telefon</label><input id="contactPhone" name="contact_phone" type="tel" value="<?= e($intakeValue('contact_phone', $member['phone'] ?? '')) ?>" required maxlength="30"></div>
      </div>
      <label class="check-consent"><input type="checkbox" name="consent" value="yes" required> Başvuru bilgilerinin değerlendirme ve proje fırsatı oluşturma amacıyla kaydedilmesini kabul ediyorum.</label>
      <p class="workspace-hint warning">Her alan başvuru sahibi beyanı olarak kaydedilir; resmi doğrulama değildir. Bu formda hassas belge veya kimlik numarası paylaşmayın. Onay; tapu/imar doğrulaması, değerleme veya bağlayıcı teklif anlamına gelmez.</p>
      <button class="button button-dark"><?= $editableProject ? 'Düzeltilmiş başvuruyu yeniden gönder' : 'Başvuruyu gönder' ?></button>
    </form>
  </div></section>
<?php elseif ($page === 'kayit'): ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow">Rolünüze göre çalışma alanı</span><h1>İhtiyacınıza uygun <em>hesap.</em></h1><p>Malik, geliştirici, emlak danışmanı, mimar, mühendis veya müteahhit olarak size uygun başlangıç alanını seçin.</p></div></section>
  <section class="section"><div class="wrap narrow"><form class="form-card" method="post" id="registrationForm"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="register_user"><div class="form-grid"><div class="field"><label for="registerName">Ad soyad</label><input id="registerName" name="name" required maxlength="100" autocomplete="name"></div><div class="field"><label for="registerEmail">E-posta</label><input id="registerEmail" name="email" type="email" required maxlength="160" autocomplete="email"></div><div class="field"><label for="registerPhone">Telefon</label><input id="registerPhone" name="phone" type="tel" required maxlength="30" autocomplete="tel"></div><div class="field"><label for="registerRole">Mesleki hesap türü</label><select id="registerRole" name="role" required><option value="">Rol seçin</option><?php foreach ($userRoles as $role => $roleInfo): ?><option value="<?= e($role) ?>"><?= e($roleInfo['label']) ?></option><?php endforeach; ?></select></div><div class="field"><label for="registerCity">İl / hizmet bölgesi</label><input id="registerCity" name="city" maxlength="100" placeholder="İsteğe bağlı"></div><div class="field"><label for="registerCompany">Şirket / ofis adı</label><input id="registerCompany" name="company" maxlength="140" placeholder="İsteğe bağlı"></div><div class="field"><label for="professionalDetail" id="professionalDetailLabel">Uzmanlık / çalışma alanı</label><input id="professionalDetail" name="professional_detail" maxlength="180" placeholder="Örn. konut, kentsel dönüşüm"></div><div class="field"><label for="authorizationNumber">Mesleki yetki / sicil no</label><input id="authorizationNumber" name="authorization_number" maxlength="100" placeholder="İsteğe bağlı; doğrulanmış değildir"></div><div class="field full-span"><label for="registerPassword">Parola (en az 12 karakter)</label><input id="registerPassword" name="password" type="password" required minlength="12" autocomplete="new-password"></div></div><p class="workspace-hint" id="roleDescription">Hesap türünü seçince ilgili çalışma alanının kısa açıklaması burada görünür.</p><label class="check-consent"><input type="checkbox" name="consent" value="yes" required> Hesap bilgilerimin çalışma alanı sağlamak üzere kaydedilmesini kabul ediyorum. Profil alanları kullanıcı beyanıdır; yetki veya belge doğrulaması yapılmış anlamına gelmez.</label><button class="button button-dark">Hesap oluştur</button><p class="form-footnote">Hesabınız var mı? <a href="?page=uye-giris"><u>Üye girişi yapın.</u></a></p></form></div></section>
  <script>
    const roleSelect = document.getElementById('registerRole');
    const roleDescription = document.getElementById('roleDescription');
    const roleDetails = {
      malik: 'Arsa bilgileri ve başvuru durumlarını takip edebileceğiniz malik alanı.',
      gelistirici: 'Proje geliştirme fırsatlarını ve fizibilite hazırlıklarını takip edebileceğiniz alan.',
      emlakci: 'Portföy ve arsa fırsatlarını inceleyip proje başvurusu yapabileceğiniz alan.',
      mimar: 'Tasarım kapsamı ve proje gereksinimleri için mesleki çalışma alanı.',
      muhendis: 'Teknik değerlendirme ve disiplin bilgileri için mesleki çalışma alanı.',
      muteahhit: 'Yapım fırsatlarını ve proje başvurularını takip edebileceğiniz alan.'
    };
    roleSelect.addEventListener('change', () => {
      roleDescription.textContent = roleDetails[roleSelect.value] || 'Hesap türünü seçince ilgili çalışma alanının kısa açıklaması burada görünür.';
    });
  </script>
<?php elseif ($page === 'uye-giris'): ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow">Üye çalışma alanları</span><h1>Hesabınıza <em>giriş yapın.</em></h1><p>Malikler ve gayrimenkul/inşaat profesyonelleri için kişisel çalışma alanı.</p></div></section>
  <section class="section"><div class="wrap narrow"><form class="form-card" method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="member_login"><div class="field"><label for="memberEmail">E-posta</label><input id="memberEmail" type="email" name="email" autocomplete="username" required></div><div class="field"><label for="memberPassword">Parola</label><input id="memberPassword" type="password" name="password" autocomplete="current-password" required></div><button class="button button-dark">Giriş yap</button><p class="form-footnote">Hesabınız yok mu? <a href="?page=kayit"><u>Rolünüze özel hesap oluşturun.</u></a></p></form></div></section>
<?php elseif ($page === 'hesabim'): ?>
  <?php
    $roleKey = (string)($member['role'] ?? '');
    $roleInfo = $userRoles[$roleKey] ?? ['label' => 'Üye', 'summary' => 'Kişisel çalışma alanınız.'];
    $dashboardModules = [
        'malik' => [
            ['title' => 'Parsel başvurusu', 'text' => 'Arsanızı ilan başvurusu olarak ekleyin. Yayın öncesi yönetici incelemesi yapılır.', 'url' => '?page=proje-ekle', 'link' => 'Arsa başvurusu oluştur'],
            ['title' => 'Fırsatları incele', 'text' => 'Yayındaki projelere göz atıp uygun fırsatları karşılaştırın.', 'url' => '?page=ilanlar', 'link' => 'İlanları görüntüle'],
            ['title' => 'Bölüm paylaşım karşılaştırması', 'text' => 'Bağımsız bölüm puanlarını ve taraflarca belirlenen kriter ağırlıklarını karşılaştırın.', 'url' => '?page=serefiye', 'link' => 'Şerefiye aracını aç'],
        ],
        'gelistirici' => [
            ['title' => 'Geliştirme fırsatları', 'text' => 'Konum, parsel alanı ve açıklama bilgilerine göre açık projeleri inceleyin.', 'url' => '?page=ilanlar', 'link' => 'Fırsatları keşfet'],
            ['title' => 'Ön fizibilite', 'text' => 'Alan, maliyet, satış fiyatı ve hedef kâr varsayımlarını şeffaf biçimde senaryolaştırın.', 'url' => '?page=fizibilite', 'link' => 'Hesaplamayı aç'],
            ['title' => 'Bölüm paylaşım karşılaştırması', 'text' => 'Kat, yön, gün ışığı ve donatı puanlarını 10.000 baz puan üzerinden kıyaslayın.', 'url' => '?page=serefiye', 'link' => 'Şerefiye aracını aç'],
            ['title' => 'Yeni çalışma', 'text' => 'Yeni bir proje fırsatı veya çalışma girdisi ekleyin; ilan yayınlanmadan önce incelenir.', 'url' => '?page=proje-ekle', 'link' => 'Proje başvurusu yap'],
        ],
        'emlakci' => [
            ['title' => 'Portföy fırsatları', 'text' => 'Yayındaki arsa ve geliştirme fırsatlarını müşterileriniz için tarayın.', 'url' => '?page=ilanlar', 'link' => 'İlanları tara'],
            ['title' => 'Portföy başvurusu', 'text' => 'İlan sahibinin onayı ve moderasyon için arsa başvurusu gönderin.', 'url' => '?page=proje-ekle', 'link' => 'İlan başvurusu ekle'],
        ],
        'mimar' => [
            ['title' => 'Tasarım girdileri', 'text' => 'Mesleki uzmanlık ve hizmet bölgesi bilgilerinizi profilinizde tanımlı tutun.', 'url' => '?page=hesabim#profil', 'link' => 'Profil bilgileri'],
            ['title' => 'Proje kapsamını incele', 'text' => 'İlan açıklamalarındaki proje gereksinimlerini gözden geçirin.', 'url' => '?page=ilanlar', 'link' => 'Projeleri görüntüle'],
            ['title' => 'Bağımsız bölüm matrisi', 'text' => 'Tasarım puanlarının dağılıma nasıl yansıdığını, endeks olarak görün.', 'url' => '?page=serefiye', 'link' => 'Matrisi görüntüle'],
        ],
        'muhendis' => [
            ['title' => 'Teknik fırsatlar', 'text' => 'Proje ilanlarını inceleyip mesleki uzmanlık alanınıza uygun işleri bulun.', 'url' => '?page=ilanlar', 'link' => 'Proje ilanları'],
            ['title' => 'Uzmanlık profilim', 'text' => 'Çalışma bölgesi ve teknik alan bilgilerinizi güncel tutun.', 'url' => '?page=hesabim#profil', 'link' => 'Profil bilgileri'],
        ],
        'muteahhit' => [
            ['title' => 'Arsa fırsatları', 'text' => 'Yayındaki projeleri konum ve temel parsel bilgileriyle karşılaştırın.', 'url' => '?page=ilanlar', 'link' => 'Fırsatları incele'],
            ['title' => 'Proje başvurusu', 'text' => 'İş geliştirme başvurusu gönderin. Herhangi bir müteahhitlik yetkisi bu profille doğrulanmaz.', 'url' => '?page=proje-ekle', 'link' => 'Çalışma başvurusu yap'],
        ],
    ];
  ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow"><?= e($roleInfo['label']) ?> çalışma alanı</span><h1>Hoş geldiniz, <em><?= e($member['name']) ?>.</em></h1><p><?= e($roleInfo['summary']) ?></p></div></section>
  <section class="section"><div class="wrap">
    <div class="profile-notice"><strong>Profil beyanı — doğrulanmamıştır.</strong> Mesleki unvan, sicil/yetki numarası, şirket ve diğer profil bilgileri kullanıcı tarafından girilmiştir; resmi kurumlarla teyit edilmemiştir.</div>
    <div class="feature-grid role-grid"><?php foreach ($dashboardModules[$roleKey] ?? [] as $module): ?><article class="feature-card"><span class="eyebrow"><?= e($roleInfo['label']) ?></span><h3><?= e($module['title']) ?></h3><p><?= e($module['text']) ?></p><a class="text-link" href="<?= e($module['url']) ?>"><?= e($module['link']) ?> →</a></article><?php endforeach; ?></div>
    <?php if (in_array($roleKey, ['mimar', 'muhendis', 'muteahhit'], true)): ?><section class="account-panel"><div class="section-heading"><span class="eyebrow">Proje erişimi</span><h2 class="section-title">Size atanmış onaylı proje brifleri</h2><p>Yalnızca yönetici tarafından bu hesaba atanmış ve başvuru sahibi beyanından oluşturulmuş son onaylı sürümler gösterilir.</p></div><?php if ($assignedBriefs): ?><div class="admin-list"><?php foreach ($assignedBriefs as $assignedProject): $brief = $assignedProject['approved_brief']; $briefFields = is_array($brief['fields'] ?? null) ? $brief['fields'] : []; ?><article class="admin-item"><div class="admin-item-info"><div class="admin-item-title"><h3><?= e($briefFields['title'] ?? 'Proje brifi') ?></h3><span class="badge badge-pending">Onaylı brif v<?= e($brief['version'] ?? 1) ?></span></div><p><?= e(($briefFields['city'] ?? '') . ' / ' . ($briefFields['district'] ?? '') . ' · ' . ($briefFields['area'] ?? '') . ' m²') ?></p><dl class="facts"><div><dt>İmar beyanı</dt><dd><?= e($briefFields['zoning'] ?: 'Belirtilmedi') ?></dd></div><div><dt>Mevcut yapı</dt><dd><?= e($briefFields['current_building'] ?: 'Belirtilmedi') ?></dd></div><div><dt>Hedeflenen kullanım</dt><dd><?= e($briefFields['intended_use'] ?: 'Belirtilmedi') ?></dd></div><div><dt>Takvim varsayımı</dt><dd><?= e($briefFields['target_timeline'] ?: 'Belirtilmedi') ?></dd></div><div><dt>İnceleme</dt><dd><?= e($brief['approved_by'] ?? '') ?> · <?= e($brief['approved_at'] ?? '') ?></dd></div></dl><p class="workspace-hint warning">Kaynak: başvuru sahibi beyanı; bu onay resmi mülkiyet, imar, mühendislik veya değerleme doğrulaması değildir. İletişim ve özel bütçe verileri uzman görünümüne aktarılmaz.</p><a class="text-link" href="?page=fizibilite">Genel ön fizibilite aracını aç →</a></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><h3>Henüz atanmış onaylı proje yok</h3><p>Uzman erişimi yalnızca yönetici ataması ve onaylı brief sonrasında açılır.</p></div><?php endif; ?></section><?php endif; ?>
    <section class="detail-panel account-panel" id="profil"><h2>Profil bilgilerini düzenle</h2><p>Hesap türü değiştirilemez. Mesleki beyan ve sicil bilgileri platform tarafından doğrulanmaz.</p><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="update_profile"><div class="form-grid"><div class="field"><label for="profileName">Ad soyad</label><input id="profileName" name="name" value="<?= e($member['name']) ?>" maxlength="100" required></div><div class="field"><label for="profileEmail">E-posta</label><input id="profileEmail" name="email" type="email" value="<?= e($member['email']) ?>" maxlength="160" required></div><div class="field"><label for="profilePhone">Telefon</label><input id="profilePhone" name="phone" type="tel" value="<?= e($member['phone']) ?>" maxlength="30" required></div><div class="field"><label for="profileCity">İl / hizmet bölgesi</label><input id="profileCity" name="city" value="<?= e($member['city'] ?? '') ?>" maxlength="100"></div><div class="field"><label for="profileCompany">Şirket / ofis</label><input id="profileCompany" name="company" value="<?= e($member['company'] ?? '') ?>" maxlength="140"></div><div class="field"><label for="profileDetail">Uzmanlık</label><input id="profileDetail" name="professional_detail" value="<?= e($member['professional_detail'] ?? '') ?>" maxlength="180"></div><div class="field"><label for="profileAuthorization">Yetki / sicil beyanı</label><input id="profileAuthorization" name="authorization_number" value="<?= e($member['authorization_number'] ?? '') ?>" maxlength="100"></div></div><button class="button button-dark" style="margin-top:18px">Profili kaydet</button></form></section>
    <section class="account-panel"><div class="section-heading"><span class="eyebrow">Başvuru takibi</span><h2 class="section-title">Gönderdiğiniz proje başvuruları</h2></div><?php if ($myProjects): ?><div class="admin-list"><?php foreach ($myProjects as $project): $reviewState = (string)($project['review_status'] ?? 'submitted'); $reviewLabels = ['submitted' => 'İnceleme bekliyor', 'needs_info' => 'Bilgi / düzeltme gerekli', 'approved' => 'Brif onaylandı', 'rejected' => 'Reddedildi']; ?><article class="admin-item"><div class="admin-item-info"><div class="admin-item-title"><h3><?= e($project['title']) ?></h3><span class="badge <?= ($project['status'] ?? '') === 'published' ? 'badge-live' : 'badge-pending' ?>"><?= ($project['status'] ?? '') === 'published' ? 'İlan yayında' : e($reviewLabels[$reviewState] ?? $reviewState) ?></span></div><p><?= e($project['city']) ?> / <?= e($project['district']) ?> · <?= number_format((float)$project['area'], 0, ',', '.') ?> m²</p><?php if (!empty($project['review_note'])): ?><p class="workspace-hint">İnceleme notu: <?= e($project['review_note']) ?></p><?php endif; ?><?php if (!empty($project['approved_brief']['version'])): ?><p>Onaylı brief sürümü v<?= e($project['approved_brief']['version']) ?>; bu bir resmi belge doğrulaması değildir.</p><?php endif; ?><?php if (!empty($project['milestones']) && is_array($project['milestones'])): ?><ul class="member-milestones"><?php foreach ($milestoneDefinitions as $milestoneKey => $milestoneDefinition): $milestoneState = $project['milestones'][$milestoneKey]['status'] ?? 'pending'; ?><li><span class="<?= $milestoneState === 'completed' ? 'milestone-done' : '' ?>"><?= $milestoneState === 'completed' ? '✓' : '○' ?></span><?= e($milestoneDefinition['label']) ?> — <?= $milestoneState === 'completed' ? 'İşaretlendi' : 'Bekliyor' ?></li><?php endforeach; ?></ul><?php endif; ?></div><?php if (($project['review_status'] ?? '') === 'needs_info'): ?><a class="button button-outline-dark button-small" href="?page=proje-ekle&amp;id=<?= e($project['id']) ?>">Düzeltmeyi yap ve yeniden gönder</a><?php elseif (($project['status'] ?? '') === 'published'): ?><a class="text-link" href="?page=ilan&amp;id=<?= e($project['id']) ?>">İlanı görüntüle →</a><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><h3>Henüz proje başvurunuz yok</h3><p>Bu kullanıcı hesabından gönderilen başvurular burada listelenir.</p><a class="button button-dark" href="?page=proje-ekle">Başvuru oluştur</a></div><?php endif; ?></section>
  </div></section>
<?php elseif ($page === 'giris'): ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow">Güvenli yönetim</span><h1>Yönetici <em>girişi.</em></h1><p>Yalnızca yetkili kullanıcılar başvuru ve ilanları yönetebilir.</p></div></section>
  <section class="section"><div class="wrap narrow"><form class="form-card" method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="login"><div class="field"><label for="username">Kullanıcı adı</label><input id="username" name="username" autocomplete="username" required></div><div class="field" style="margin-top:14px"><label for="password">Parola</label><input id="password" type="password" name="password" autocomplete="current-password" required></div><button class="button button-dark" style="margin-top:18px">Giriş yap</button><p class="workspace-hint">İlk defa kuruyorsanız, sunucu yöneticisinin kurulum anahtarını tanımlamasından sonra <a href="?page=kurulum"><u>yönetici hesabı oluşturun</u></a>.</p></form></div></section>
<?php elseif ($page === 'kurulum'): ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow">Tek seferlik kurulum</span><h1>Yönetici <em>hesabı oluştur.</em></h1><p>Kurulum, sunucuda ASATA_ADMIN_SETUP_KEY tanımlı ve henüz yönetici hesabı yoksa açılır.</p></div></section>
  <section class="section"><div class="wrap narrow"><?php if (read_admin()): ?><div class="empty-state"><h3>Kurulum tamamlanmış</h3><p>Yönetici hesabı oluşturulmuş. Giriş ekranını kullanın.</p><a class="button button-dark" href="?page=giris">Girişe git</a></div><?php else: ?><form class="form-card" method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="setup_admin"><div class="field"><label for="setupKey">Sunucu kurulum anahtarı</label><input id="setupKey" name="setup_key" type="password" required autocomplete="off"></div><div class="field" style="margin-top:14px"><label for="username">Yönetici kullanıcı adı</label><input id="username" name="username" required minlength="3" maxlength="40" pattern="[a-zA-Z0-9._-]+" autocomplete="username"></div><div class="field" style="margin-top:14px"><label for="password">Parola (en az 12 karakter)</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"></div><button class="button button-dark" style="margin-top:18px">Yönetici hesabı oluştur</button></form><?php endif; ?></div></section>
<?php elseif ($page === 'admin'): ?>
  <section class="page-hero"><div class="wrap"><span class="eyebrow">İçerik ve başvuru yönetimi</span><h1>Yönetim <em>paneli.</em></h1><p>İlan başvurularını inceleyin, yayınlayın ve iletişim taleplerini takip edin.</p><a class="button button-outline" href="?page=cikis">Oturumu kapat</a></div></section>
  <section class="section"><div class="wrap admin-stats"><div class="stat-card"><span>Toplam başvuru</span><strong><?= count($projects) ?></strong></div><div class="stat-card"><span>Yayındaki ilan</span><strong><?= count($published) ?></strong></div><div class="stat-card"><span>İletişim talebi</span><strong><?= count($leads) ?></strong></div></div></section>
  <section class="section compact"><div class="wrap"><div class="section-heading"><span class="eyebrow">Moderasyon</span><h2 class="section-title">Proje başvuruları ve işlem geçmişi</h2><p>Silme yerine arşivleme uygulanır. Durum ve aşama değişiklikleri işlemi yapan yönetici ve zaman bilgisiyle kaydedilir.</p></div><?php if ($projects): ?><div class="admin-list"><?php foreach ($projects as $project): $projectStatus = (string)($project['status'] ?? 'pending'); $isArchived = $projectStatus === 'archived' || !empty($project['archived_at']); ?><article class="admin-item"><div class="admin-item-info"><div class="admin-item-title"><h3><?= e($project['title'] ?? 'Başlıksız proje') ?></h3><span class="badge <?= $projectStatus === 'published' ? 'badge-live' : 'badge-pending' ?>"><?= $isArchived ? 'Arşivlendi' : ($projectStatus === 'published' ? 'Yayında' : 'İncelemede') ?></span></div><p><?= e(($project['city'] ?? '') . ' / ' . ($project['district'] ?? '') . ' · ' . number_format((float)($project['area'] ?? 0), 0, ',', '.') . ' m²') ?></p><p><?= e($project['contact_name'] ?? 'Demo kayıt') ?> · <?= e($project['contact_email'] ?? '') ?> · <?= e($project['contact_phone'] ?? '') ?></p><small><?= e($project['created_at'] ?? '') ?><?= !empty($project['demo']) ? ' · ÖRNEK KAYIT' : '' ?></small><?php $activity = is_array($project['activity'] ?? null) ? $project['activity'] : []; if ($activity): ?><details class="project-activity"><summary>İşlem geçmişi (<?= count($activity) ?>)</summary><ul><?php foreach (array_reverse($activity) as $event): $eventAction = (string)($event['action'] ?? ''); $eventLabel = match ($eventAction) { 'submitted' => 'Başvuru gönderildi', 'publish' => 'İlan yayınlandı', 'hide' => 'İlan yayından kaldırıldı', 'archive' => 'Kayıt arşivlendi', 'restore' => 'Kayıt arşivden geri yüklendi', 'milestone_completed' => 'Aşama tamamlandı olarak işaretlendi', 'milestone_pending' => 'Aşama bekliyor olarak güncellendi', default => 'Proje işlemi' }; ?><li><strong><?= e($eventLabel) ?></strong><?php if (!empty($event['milestone']) && isset($milestoneDefinitions[$event['milestone']])): ?> — <?= e($milestoneDefinitions[$event['milestone']]['label']) ?><?php endif; ?> · <?= e($event['actor_name'] ?? 'Bilinmeyen kullanıcı') ?> · <?= e($event['at'] ?? '') ?></li><?php endforeach; ?></ul></details><?php endif; ?></div><div class="admin-actions"><?php if (!$isArchived && empty($project['demo'])): ?><?php if ($projectStatus !== 'published'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="admin_project"><input type="hidden" name="operation" value="publish"><input type="hidden" name="id" value="<?= e($project['id']) ?>"><button class="button button-lime button-small">Yayınla</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="admin_project"><input type="hidden" name="operation" value="hide"><input type="hidden" name="id" value="<?= e($project['id']) ?>"><button class="button button-outline-dark button-small">Yayından kaldır</button></form><?php endif; ?><form method="post" onsubmit="return confirm('Bu kayıt arşivlensin mi? Kayıt geçmişi korunacaktır.')"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="admin_project"><input type="hidden" name="operation" value="archive"><input type="hidden" name="id" value="<?= e($project['id']) ?>"><button class="button button-danger button-small">Arşivle</button></form><?php elseif ($isArchived && empty($project['demo'])): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="admin_project"><input type="hidden" name="operation" value="restore"><input type="hidden" name="id" value="<?= e($project['id']) ?>"><button class="button button-dark button-small">Geri yükle</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><h3>Başvuru bulunmuyor</h3></div><?php endif; ?></div></section>
  <section class="section compact"><div class="wrap"><div class="section-heading"><span class="eyebrow">Başvuru verisi ve onay kapısı</span><h2 class="section-title">Uzman brifi incelemesi</h2><p>Her alan başvuru sahibi beyanıdır ve doğrulanmamıştır. Onaylanan sürüm, ilan yayınından bağımsız olarak yalnız atanmış mimar/mühendis/müteahhit hesaplarına açılır.</p></div><?php $reviewProjects = array_values(array_filter($projects, static fn(array $project): bool => empty($project['demo']) && empty($project['archived_at']))); ?><?php if ($reviewProjects): ?><div class="admin-list"><?php foreach ($reviewProjects as $reviewProject): $reviewState = (string)($reviewProject['review_status'] ?? 'submitted'); $reviewLabels = ['submitted' => 'İnceleme bekliyor', 'needs_info' => 'Bilgi / düzeltme gerekli', 'approved' => 'Brif onaylandı', 'rejected' => 'Reddedildi']; $fieldSources = is_array($reviewProject['field_sources'] ?? null) ? $reviewProject['field_sources'] : []; ?><article class="admin-item"><div class="admin-item-info"><div class="admin-item-title"><h3><?= e($reviewProject['title'] ?? 'Başlıksız başvuru') ?></h3><span class="badge <?= $reviewState === 'approved' ? 'badge-live' : 'badge-pending' ?>"><?= e($reviewLabels[$reviewState] ?? $reviewState) ?></span></div><p><?= e($reviewProject['applicant_role'] ?? '') ?> · <?= e(($reviewProject['city'] ?? '') . ' / ' . ($reviewProject['district'] ?? '') . ' · ' . ($reviewProject['area'] ?? '') . ' m²') ?></p><p>Alan kökeni: başvuru sahibi beyanı · <?= e($fieldSources['area']['source_date'] ?? $reviewProject['created_at'] ?? '') ?> · Resmî doğrulama yok</p><?php if (!empty($reviewProject['review_note'])): ?><p class="workspace-hint">Son inceleme notu: <?= e($reviewProject['review_note']) ?> · <?= e($reviewProject['reviewed_by'] ?? '') ?> · <?= e($reviewProject['reviewed_at'] ?? '') ?></p><?php endif; ?><?php if (!empty($reviewProject['approved_brief']['version'])): ?><p>Son onaylı brief sürümü: v<?= e($reviewProject['approved_brief']['version']) ?> · uzman ataması <?= count(is_array($reviewProject['expert_assignments'] ?? null) ? $reviewProject['expert_assignments'] : []) ?> hesap</p><?php endif; ?></div><?php if (($reviewProject['review_status'] ?? '') !== 'approved' || empty($reviewProject['approved_brief'])): ?><form class="brief-review-form" method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="admin_review_project"><input type="hidden" name="project_id" value="<?= e($reviewProject['id']) ?>"><div class="field"><label for="reviewStatus-<?= e($reviewProject['id']) ?>">İnceleme sonucu</label><select id="reviewStatus-<?= e($reviewProject['id']) ?>" name="review_status"><option value="needs_info">Eksik bilgi / düzeltme iste</option><option value="approved">Brief sürümünü onayla</option><option value="rejected">Reddet</option></select></div><div class="field"><label for="reviewNote-<?= e($reviewProject['id']) ?>">İnceleme notu (ret/eksik bilgi için zorunlu)</label><textarea id="reviewNote-<?= e($reviewProject['id']) ?>" name="review_note" maxlength="1000" rows="2"></textarea></div><button class="button button-dark button-small">İnceleme kararını kaydet</button></form><?php endif; ?><?php if (($reviewProject['review_status'] ?? '') === 'approved' && !empty($reviewProject['approved_brief'])): ?><form class="brief-review-form" method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="admin_assign_experts"><input type="hidden" name="project_id" value="<?= e($reviewProject['id']) ?>"><div class="field"><label for="experts-<?= e($reviewProject['id']) ?>">Projeye atanacak uzman hesapları</label><select id="experts-<?= e($reviewProject['id']) ?>" name="expert_ids[]" multiple size="4"><?php $assignedIds = is_array($reviewProject['expert_assignments'] ?? null) ? $reviewProject['expert_assignments'] : []; foreach ($eligibleExperts as $expert): ?><option value="<?= e($expert['id']) ?>" <?= in_array($expert['id'], $assignedIds, true) ? 'selected' : '' ?>><?= e($expert['name']) ?> · <?= e($userRoles[$expert['role']]['label'] ?? $expert['role']) ?></option><?php endforeach; ?></select><small>Hiç seçim yapılmazsa proje uzmanlara paylaşılmaz. Uzmanlar yalnız onaylı brief alanlarının sınırlı bir görünümünü görür.</small></div><button class="button button-outline-dark button-small">Uzman erişimini kaydet</button></form><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><h3>İncelenecek başvuru yok</h3></div><?php endif; ?></div></section>
  <section class="section compact soft"><div class="wrap"><div class="section-heading"><span class="eyebrow">İnşaat ilerleme kontrolü</span><h2 class="section-title">Aşama takip listesi</h2><p>Aşağıdaki yüzdeler master prompttaki örnek kademeli modeldir. Arayüz yalnızca yönetici işaretlemelerini saklar; tapu devri, escrow, sigorta, hakediş veya teknik onay gerçekleştirmez.</p></div><?php $trackableProjects = array_values(array_filter($projects, static fn(array $project): bool => empty($project['demo']) && empty($project['archived_at']))); ?><?php if ($trackableProjects): ?><div class="milestone-admin-list"><?php foreach ($trackableProjects as $project): $projectMilestones = is_array($project['milestones'] ?? null) ? $project['milestones'] : []; ?><article class="milestone-admin-card"><div class="admin-item-title"><h3><?= e($project['title'] ?? 'Başlıksız proje') ?></h3><span class="listing-location"><?= e(($project['city'] ?? '') . ' / ' . ($project['district'] ?? '')) ?></span></div><div class="milestone-admin-rows"><?php foreach ($milestoneDefinitions as $milestoneKey => $milestoneDefinition): $milestoneState = $projectMilestones[$milestoneKey]['status'] ?? 'pending'; $completedAt = $projectMilestones[$milestoneKey]['completed_at'] ?? ''; ?><div class="milestone-admin-row"><div><strong><?= e($milestoneDefinition['label']) ?></strong><span>Prompt örneği: %<?= e($milestoneDefinition['allocation']) ?> · <?= $milestoneState === 'completed' ? 'İşaretlendi' : 'Bekliyor' ?><?= $completedAt !== '' ? ' · ' . e($completedAt) : '' ?></span></div><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="admin_milestone"><input type="hidden" name="project_id" value="<?= e($project['id']) ?>"><input type="hidden" name="milestone" value="<?= e($milestoneKey) ?>"><select name="status" aria-label="<?= e($milestoneDefinition['label']) ?> durumu"><option value="pending" <?= $milestoneState !== 'completed' ? 'selected' : '' ?>>Bekliyor</option><option value="completed" <?= $milestoneState === 'completed' ? 'selected' : '' ?>>İşaretlendi</option></select><button class="button button-dark button-small">Kaydet</button></form></div><?php endforeach; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><h3>İnşaat aşaması takibine uygun başvuru yok</h3><p>Örnek ilanlar düzenlenemez; gerçek proje başvuruları bu listede görünür.</p></div><?php endif; ?></div></section>
  <section class="section compact soft"><div class="wrap"><div class="section-heading"><span class="eyebrow">Gelen talepler</span><h2 class="section-title">İletişim kayıtları</h2></div><?php if ($leads): ?><div class="admin-list"><?php foreach ($leads as $lead): ?><article class="admin-item"><div class="admin-item-info"><h3><?= e($lead['project_title'] ?? 'Genel iletişim') ?></h3><p><?= e($lead['name'] ?? '') ?> · <?= e($lead['phone'] ?? '') ?> · <?= e($lead['email'] ?? '') ?></p><p><?= e($lead['message'] ?? '') ?></p><small><?= e($lead['created_at'] ?? '') ?></small></div><form method="post" onsubmit="return confirm('Bu talep kaydı silinsin mi?')"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="admin_lead"><input type="hidden" name="id" value="<?= e($lead['id']) ?>"><button class="button button-danger button-small">Sil</button></form></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><h3>Henüz iletişim talebi yok</h3></div><?php endif; ?></div></section>
<?php else: ?>
  <section class="section"><div class="wrap empty-state"><span class="eyebrow">404</span><h1>Bu sayfa bulunamadı.</h1><a class="button button-dark" href="?page=home">Ana sayfaya dön</a></div></section>
<?php endif; ?>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
