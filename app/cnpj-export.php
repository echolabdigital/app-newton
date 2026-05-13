<?php
require_once __DIR__ . '/../config.php';
$tenant = require_tenant();
require_once __DIR__ . '/../core/cnpj_db.php';

$f = [
    'q'           => trim($_GET['q']            ?? ''),
    'situacao'    => $_GET['situacao']           ?? '02',
    'uf'          => strtoupper(trim($_GET['uf'] ?? '')),
    'municipio'   => trim($_GET['municipio']    ?? ''),
    'cnae'        => trim($_GET['cnae']         ?? ''),
    'porte'       => $_GET['porte']              ?? '',
    'mf'          => $_GET['mf']                 ?? '',
    'simples'     => !empty($_GET['simples']),
    'mei'         => !empty($_GET['mei']),
    'tem_email'   => !empty($_GET['tem_email']),
    'tem_tel'     => !empty($_GET['tem_tel']),
    'abertura_de' => $_GET['abertura_de']        ?? '',
    'abertura_ate'=> $_GET['abertura_ate']       ?? '',
];

['where' => $where, 'params' => $params] = cnpj_where($f);

$joins = "
    FROM rf_estabelecimentos est
    JOIN rf_empresas emp ON emp.cnpj_basico = est.cnpj_basico
    LEFT JOIN rf_cnaes cn      ON cn.codigo  = est.cnae_principal
    LEFT JOIN rf_municipios mun ON mun.codigo = est.municipio
    LEFT JOIN rf_simples sim    ON sim.cnpj_basico = est.cnpj_basico
";

$stmt = cnpj_q("
    SELECT
        est.cnpj_basico || est.cnpj_ordem || est.cnpj_dv AS cnpj,
        emp.razao_social,
        est.nome_fantasia,
        est.situacao_cadastral,
        est.data_inicio_atividade::text AS data_abertura,
        est.cnae_principal,
        COALESCE(cn.descricao, '') AS cnae_desc,
        emp.porte_empresa,
        est.identificador_mf,
        est.uf,
        COALESCE(mun.descricao, '') AS municipio,
        est.bairro,
        est.cep,
        est.ddd1 || est.telefone1 AS telefone,
        est.ddd2 || est.telefone2 AS telefone2,
        est.email,
        emp.capital_social::text AS capital_social
    $joins
    $where
    ORDER BY emp.razao_social
    LIMIT 5000
", $params);

$filename = 'newton-cnpj-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

echo "\xEF\xBB\xBF"; // BOM UTF-8 para Excel

$out = fopen('php://output', 'w');
fputcsv($out, [
    'CNPJ', 'Razão Social', 'Nome Fantasia', 'Situação',
    'Data Abertura', 'CNAE', 'Setor (CNAE)', 'Porte', 'Tipo',
    'UF', 'Município', 'Bairro', 'CEP',
    'Telefone 1', 'Telefone 2', 'E-mail', 'Capital Social (R$)',
], ';');

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
        cnpj_fmt($r['cnpj']),
        $r['razao_social'],
        $r['nome_fantasia'],
        cnpj_situacao_label($r['situacao_cadastral']),
        $r['data_abertura'],
        $r['cnae_principal'],
        $r['cnae_desc'],
        cnpj_porte_label($r['porte_empresa']),
        $r['identificador_mf'] === '1' ? 'Matriz' : 'Filial',
        $r['uf'],
        $r['municipio'],
        $r['bairro'],
        $r['cep'],
        $r['telefone'],
        $r['telefone2'],
        $r['email'],
        $r['capital_social'],
    ], ';');
}
fclose($out);
