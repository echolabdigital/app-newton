<?php
require_once __DIR__ . '/../config.php';
$tenant = require_tenant();
require_once __DIR__ . '/../core/cnpj_db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'municipios':
            $uf = strtoupper(trim($_GET['uf'] ?? ''));
            if (!$uf || !in_array($uf, CNPJ_UFS, true)) {
                echo json_encode([]);
                exit;
            }
            $rows = cnpj_all(
                "SELECT DISTINCT est.municipio AS codigo, mun.descricao AS nome
                 FROM rf_estabelecimentos est
                 JOIN rf_municipios mun ON mun.codigo = est.municipio
                 WHERE est.uf = ? AND est.situacao_cadastral = '02'
                 ORDER BY mun.descricao
                 LIMIT 2000",
                [$uf]
            );
            echo json_encode($rows);
            break;

        case 'cnaes':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 2) { echo json_encode([]); exit; }
            $rows = cnpj_all(
                "SELECT codigo, descricao FROM rf_cnaes
                 WHERE descricao ILIKE ? OR codigo LIKE ?
                 ORDER BY codigo LIMIT 20",
                ['%' . $q . '%', $q . '%']
            );
            echo json_encode($rows);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'action inválida']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno']);
}
