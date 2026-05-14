<?php
/**
 * Newton AI — CNPJ Export (quota v2)
 * Exporta leads como CSV respeitando limite mensal + addon credits.
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/cnpj_db.php';

$limit = cnpj_monthly_limit($tenant_id);
$used  = cnpj_monthly_used($tenant_id);
$pct   = cnpj_usage_pct($used, $limit);

// Block at 100 %
if ($pct >= 100) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode([
        'error'   => 'limit_reached',
        'message' => "Você atingiu 100% do seu limite mensal ({$limit} leads). "
                   . 'Adquira um pacote adicional ou aguarde a renovação no próximo mês.',
        'used'    => $used,
        'limit'   => $limit,
        'percent' => $pct,
    ]);
    exit;
}

// Build query
$filters = $_GET;
[$where, $params] = cnpj_build_where($filters);

$total = (int) cnpj_val(
    "SELECT COUNT(*) FROM rf_estabelecimentos e
     JOIN rf_empresas emp ON emp.cnpj_basico = e.cnpj_basico $where",
    $params
);

$available    = $limit - $used;
$max_per_dl   = 5000;
$export_count = min($total, $max_per_dl, $available);

if ($export_count <= 0) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode([
        'error'   => 'limit_reached',
        'message' => "Limite insuficiente. Disponível: {$available} leads este mês.",
        'used'    => $used,
        'limit'   => $limit,
        'percent' => $pct,
    ]);
    exit;
}

// Log BEFORE streaming (reserve quota)
cnpj_quota_log($tenant_id, $export_count, $filters);

// Alert header (non-blocking)
$alert = cnpj_alert_message($pct, $used, $limit);

$rows = cnpj_all(
    "SELECT
        e.cnpj_basico || e.cnpj_ordem || e.cnpj_dv AS cnpj,
        emp.razao_social,
        e.nome_fantasia,
        e.situacao_cadastral,
        e.data_situacao_cadastral,
        e.cnae_fiscal_principal,
        e.tipo_logradouro || ' ' || e.logradouro AS endereco,
        e.numero,
        e.complemento,
        e.bairro,
        e.municipio,
        e.uf,
        e.cep,
        e.ddd1,
        e.telefone1,
        e.ddd2,
        e.telefone2,
        e.correio_eletronico,
        emp.porte_empresa,
        emp.capital_social
     FROM rf_estabelecimentos e
     JOIN rf_empresas emp ON emp.cnpj_basico = e.cnpj_basico
     $where
     LIMIT $export_count",
    $params
);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cnpj_export_' . date('Y-m-d_His') . '.csv"');
if ($alert) {
    header('X-Newton-Alert: ' . $alert);
}

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel

fputcsv($out, [
    'CNPJ','Razão Social','Nome Fantasia','Situação','Dt.Situação',
    'CNAE Principal','Endereço','Número','Complemento',
    'Bairro','Município','UF','CEP','DDD1','Telefone1','DDD2','Telefone2',
    'E-mail','Porte','Capital Social',
], ';');

foreach ($rows as $r) {
    fputcsv($out, array_values($r), ';');
}
fclose($out);
exit;
