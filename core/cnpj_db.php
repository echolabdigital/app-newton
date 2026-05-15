<?php
/**
 * Newton AI — CNPJ / Receita Federal helpers
 * PostgreSQL connection + query helpers + quota v2
 */

// ─── PostgreSQL connection ────────────────────────────────────────────────────

function cnpj_db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        CNPJ_DB_HOST, CNPJ_DB_PORT, CNPJ_DB_NAME
    );
    $pdo = new PDO($dsn, CNPJ_DB_USER, CNPJ_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function cnpj_q(string $sql, array $p = []): PDOStatement
{
    $st = cnpj_db()->prepare($sql);
    $st->execute($p);
    return $st;
}

function cnpj_one(string $sql, array $p = []): ?array
{
    $r = cnpj_q($sql, $p)->fetch();
    return $r ?: null;
}

function cnpj_all(string $sql, array $p = []): array
{
    return cnpj_q($sql, $p)->fetchAll();
}

function cnpj_val(string $sql, array $p = [])
{
    return cnpj_q($sql, $p)->fetchColumn();
}

// ─── WHERE builder ───────────────────────────────────────────────────────────

function cnpj_build_where(array $f): array
{
    $conds  = [];
    $params = [];

    if (!empty($f['q'])) {
        $q = trim($f['q']);
        $digits = preg_replace('/\D/', '', $q);
        if (strlen($digits) >= 8) {
            $conds[]  = 'e.cnpj_basico = ?';
            $params[] = substr($digits, 0, 8);
        } else {
            $conds[]  = "(emp.razao_social ILIKE ? OR e.nome_fantasia ILIKE ?)";
            $like     = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
    }

    if (!empty($f['situacao'])) {
        $conds[]  = 'e.situacao_cadastral = ?';
        $params[] = $f['situacao'];
    }

    if (!empty($f['uf'])) {
        $conds[]  = 'e.uf = ?';
        $params[] = strtoupper($f['uf']);
    }

    if (!empty($f['municipio'])) {
        $conds[]  = 'e.municipio = ?';
        $params[] = $f['municipio'];
    }

    if (!empty($f['cnae'])) {
        $conds[]  = 'e.cnae_principal = ?';
        $params[] = $f['cnae'];
    }

    if (!empty($f['porte'])) {
        $conds[]  = 'emp.porte_empresa = ?';
        $params[] = $f['porte'];
    }

    if (!empty($f['mf'])) {
        $conds[]  = 'e.identificador_mf = ?';
        $params[] = $f['mf'];
    }

    if (!empty($f['abertura_de'])) {
        $conds[]  = 'e.data_inicio_atividade >= ?';
        $params[] = $f['abertura_de'];
    }

    if (!empty($f['abertura_ate'])) {
        $conds[]  = 'e.data_inicio_atividade <= ?';
        $params[] = $f['abertura_ate'];
    }

    if (!empty($f['tem_email'])) {
        $conds[] = "e.email IS NOT NULL AND e.email <> ''";
    }

    if (!empty($f['tem_tel'])) {
        $conds[] = "e.telefone1 IS NOT NULL AND e.telefone1 <> ''";
    }

    if (!empty($f['simples'])) {
        $conds[] = 'EXISTS (SELECT 1 FROM rf_simples s WHERE s.cnpj_basico = e.cnpj_basico AND s.opcao_simples = \'S\')';
    }

    if (!empty($f['mei'])) {
        $conds[] = 'EXISTS (SELECT 1 FROM rf_simples s WHERE s.cnpj_basico = e.cnpj_basico AND s.opcao_mei = \'S\')';
    }

    $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
    return [$where, $params];
}

// ─── Search ──────────────────────────────────────────────────────────────────

function cnpj_search(array $f, int $page = 1, int $per = 20): array
{
    [$where, $params] = cnpj_build_where($f);

    $total = (int) cnpj_val(
        "SELECT COUNT(*) FROM rf_estabelecimentos e
         LEFT JOIN rf_empresas emp ON emp.cnpj_basico = e.cnpj_basico $where",
        $params
    );

    $offset = ($page - 1) * $per;
    $rows   = cnpj_all(
        "SELECT
            e.cnpj_basico || e.cnpj_ordem || e.cnpj_dv AS cnpj,
            COALESCE(emp.razao_social, e.nome_fantasia, 'N/D') AS razao_social,
            e.nome_fantasia,
            e.situacao_cadastral,
            e.data_inicio_atividade,
            e.cnae_principal,
            e.uf,
            e.municipio,
            e.ddd1,
            e.telefone1,
            e.ddd2,
            e.telefone2,
            e.email,
            COALESCE(emp.porte_empresa, '00') AS porte_empresa,
            e.identificador_mf
         FROM rf_estabelecimentos e
         LEFT JOIN rf_empresas emp ON emp.cnpj_basico = e.cnpj_basico
         $where
         ORDER BY razao_social
         LIMIT $per OFFSET $offset",
        $params
    );

    return ['rows' => $rows, 'total' => $total];
}

// ─── Quota v2 ─────────────────────────────────────────────────────────────────

function cnpj_monthly_limit(int $tenantId): int
{
    $row = db_one(
        "SELECT t.cnpj_limit_override, t.cnpj_addon_credits, p.monthly_limit
         FROM tenants t
         LEFT JOIN cnpj_plans p ON p.id = t.cnpj_plan_id
         WHERE t.id = ?",
        [$tenantId]
    );
    if (!$row) return 0;
    $base = $row['cnpj_limit_override'] !== null
        ? (int) $row['cnpj_limit_override']
        : (int) ($row['monthly_limit'] ?? 0);
    return $base + (int) $row['cnpj_addon_credits'];
}

function cnpj_base_limit(int $tenantId): int
{
    return (int) db_val(
        "SELECT COALESCE(t.cnpj_limit_override, p.monthly_limit, 0)
         FROM tenants t
         LEFT JOIN cnpj_plans p ON p.id = t.cnpj_plan_id
         WHERE t.id = ?",
        [$tenantId]
    );
}

function cnpj_monthly_used(int $tenantId): int
{
    return (int) db_val(
        "SELECT COALESCE(SUM(records_count), 0)
         FROM cnpj_download_log
         WHERE tenant_id = ?
           AND YEAR(downloaded_at)  = YEAR(NOW())
           AND MONTH(downloaded_at) = MONTH(NOW())",
        [$tenantId]
    );
}

function cnpj_quota_log(int $tenantId, int $rows, array $filters = []): void
{
    if ($rows <= 0) return;

    db_insert('cnpj_download_log', [
        'tenant_id'    => $tenantId,
        'records_count'=> $rows,
        'filters_json' => json_encode($filters, JSON_UNESCAPED_UNICODE),
    ]);

    // Deduct addon credits for any overflow past the base plan
    $base      = cnpj_base_limit($tenantId);
    $usedAfter = cnpj_monthly_used($tenantId);
    $overflow  = min($rows, max(0, $usedAfter - $base));
    if ($overflow > 0) {
        db_q(
            "UPDATE tenants SET cnpj_addon_credits = GREATEST(0, cnpj_addon_credits - ?) WHERE id = ?",
            [$overflow, $tenantId]
        );
    }
}

function cnpj_usage_pct(int $used, int $limit): float
{
    if ($limit <= 0) return 100.0;
    return min(100.0, round($used / $limit * 100, 1));
}

const CNPJ_ALERT_THRESHOLDS = [90, 80, 70, 60, 50];

function cnpj_alert_message(float $pct, int $used, int $limit): ?string
{
    foreach (CNPJ_ALERT_THRESHOLDS as $t) {
        if ($pct >= $t) {
            $remaining = $limit - $used;
            return "Você usou {$pct}% do limite mensal ({$used}/{$limit} leads). Restam {$remaining} leads.";
        }
    }
    return null;
}

// ─── Constants ───────────────────────────────────────────────────────────────

const CNPJ_SITUACOES = [
    '01' => 'Nula',
    '02' => 'Ativa',
    '03' => 'Suspensa',
    '04' => 'Inapta',
    '08' => 'Baixada',
];

const CNPJ_PORTES = [
    '00' => 'Não informado',
    '01' => 'Micro empresa',
    '03' => 'Empresa de Pequeno Porte',
    '05' => 'Demais',
];

const CNPJ_UFS = [
    'AC','AL','AM','AP','BA','CE','DF','ES','GO','MA',
    'MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN',
    'RO','RR','RS','SC','SE','SP','TO',
];

// ─── Formatters ──────────────────────────────────────────────────────────────

function cnpj_fmt(string $cnpj): string
{
    $d = preg_replace('/\D/', '', $cnpj);
    if (strlen($d) !== 14) return $cnpj;
    return substr($d,0,2).'.'.substr($d,2,3).'.'.substr($d,5,3).'/'
         . substr($d,8,4).'-'.substr($d,12,2);
}

function cnpj_situacao_label(string $code): string
{
    return CNPJ_SITUACOES[$code] ?? $code;
}

function cnpj_porte_label(string $code): string
{
    return CNPJ_PORTES[$code] ?? $code;
}
