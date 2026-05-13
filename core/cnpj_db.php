<?php
/**
 * Newton CNPJ — Conexão PostgreSQL separada do MySQL principal.
 *
 * Requer em config.php:
 *   define('CNPJ_DB_HOST', '...');
 *   define('CNPJ_DB_PORT', '5432');
 *   define('CNPJ_DB_NAME', 'newton_cnpj');
 *   define('CNPJ_DB_USER', 'newton');
 *   define('CNPJ_DB_PASS', '...');
 */

function cnpj_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
        CNPJ_DB_HOST, CNPJ_DB_PORT, CNPJ_DB_NAME);
    $pdo = new PDO($dsn, CNPJ_DB_USER, CNPJ_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function cnpj_q(string $sql, array $p = []): PDOStatement {
    $st = cnpj_db()->prepare($sql);
    $st->execute($p);
    return $st;
}

function cnpj_one(string $sql, array $p = []): ?array {
    $r = cnpj_q($sql, $p)->fetch();
    return $r === false ? null : $r;
}

function cnpj_all(string $sql, array $p = []): array {
    return cnpj_q($sql, $p)->fetchAll();
}

function cnpj_val(string $sql, array $p = []) {
    $v = cnpj_q($sql, $p)->fetchColumn();
    return $v === false ? null : $v;
}

/**
 * Monta WHERE + params a partir dos filtros do formulário.
 * Retorna ['where' => '...', 'params' => [...]]
 */
function cnpj_where(array $f): array {
    $conds  = [];
    $params = [];

    $sit = $f['situacao'] ?? '02';
    if ($sit && $sit !== 'all') {
        $conds[]  = 'est.situacao_cadastral = ?';
        $params[] = $sit;
    }

    if (!empty($f['uf'])) {
        $conds[]  = 'est.uf = ?';
        $params[] = strtoupper(trim($f['uf']));
    }

    if (!empty($f['municipio'])) {
        $conds[]  = 'est.municipio = ?';
        $params[] = $f['municipio'];
    }

    if (!empty($f['cnae'])) {
        $cnae = preg_replace('/\D/', '', $f['cnae']);
        if ($cnae !== '') {
            $conds[]  = 'est.cnae_principal LIKE ?';
            $params[] = $cnae . '%';
        }
    }

    if (!empty($f['porte'])) {
        $conds[]  = 'emp.porte_empresa = ?';
        $params[] = $f['porte'];
    }

    if (!empty($f['mf']) && in_array($f['mf'], ['1', '2'], true)) {
        $conds[]  = 'est.identificador_mf = ?';
        $params[] = $f['mf'];
    }

    if (!empty($f['simples'])) {
        $conds[] = "sim.opcao_simples = 'S'";
    }

    if (!empty($f['mei'])) {
        $conds[] = "sim.opcao_mei = 'S'";
    }

    if (!empty($f['tem_email'])) {
        $conds[] = "est.email <> ''";
    }

    if (!empty($f['tem_tel'])) {
        $conds[] = "est.telefone1 <> ''";
    }

    if (!empty($f['abertura_de'])) {
        $conds[]  = 'est.data_inicio_atividade >= ?';
        $params[] = $f['abertura_de'];
    }

    if (!empty($f['abertura_ate'])) {
        $conds[]  = 'est.data_inicio_atividade <= ?';
        $params[] = $f['abertura_ate'];
    }

    if (!empty($f['q'])) {
        $term  = trim($f['q']);
        $clean = preg_replace('/\D/', '', $term);
        if (strlen($clean) >= 8) {
            $conds[]  = "(est.cnpj_basico = ? OR (est.cnpj_basico || est.cnpj_ordem || est.cnpj_dv) = ?)";
            $params[] = substr($clean, 0, 8);
            $params[] = substr($clean, 0, 14);
        } else {
            $conds[]  = "(emp.razao_social ILIKE ? OR est.nome_fantasia ILIKE ?)";
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
        }
    }

    $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
    return compact('where', 'params');
}

/**
 * Executa a query principal de prospecção com paginação.
 */
function cnpj_search(array $f, int $page = 1, int $per = 50): array {
    ['where' => $where, 'params' => $params] = cnpj_where($f);

    $joins = "
        FROM rf_estabelecimentos est
        JOIN rf_empresas emp ON emp.cnpj_basico = est.cnpj_basico
        LEFT JOIN rf_cnaes cn      ON cn.codigo  = est.cnae_principal
        LEFT JOIN rf_municipios mun ON mun.codigo = est.municipio
        LEFT JOIN rf_simples sim    ON sim.cnpj_basico = est.cnpj_basico
    ";

    $total  = (int) cnpj_val("SELECT COUNT(*) $joins $where", $params);
    $offset = ($page - 1) * $per;

    $rows = cnpj_all("
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
            COALESCE(mun.descricao, est.municipio) AS municipio,
            est.bairro,
            est.cep,
            est.ddd1 || est.telefone1 AS telefone,
            est.ddd2 || est.telefone2 AS telefone2,
            est.email,
            emp.capital_social
        $joins
        $where
        ORDER BY emp.razao_social
        LIMIT ? OFFSET ?
    ", array_merge($params, [$per, $offset]));

    return compact('rows', 'total');
}

// ─── Constantes ────────────────────────────────────────────────────────

const CNPJ_SITUACOES = [
    'all' => 'Todas',
    '02'  => 'Ativa',
    '01'  => 'Nula',
    '03'  => 'Suspensa',
    '04'  => 'Inapta',
    '08'  => 'Baixada',
];

const CNPJ_PORTES = [
    ''   => 'Todos',
    '00' => 'Não Informado',
    '01' => 'Micro Empresa (ME)',
    '03' => 'Pequeno Porte (EPP)',
    '05' => 'Demais',
];

const CNPJ_UFS = [
    'AC','AL','AM','AP','BA','CE','DF','ES','GO',
    'MA','MG','MS','MT','PA','PB','PE','PI','PR',
    'RJ','RN','RO','RR','RS','SC','SE','SP','TO',
];

function cnpj_fmt(string $c): string {
    $c = preg_replace('/\D/', '', $c);
    if (strlen($c) !== 14) return $c;
    return substr($c,0,2).'.'.substr($c,2,3).'.'.substr($c,5,3)
         .'/'.substr($c,8,4).'-'.substr($c,12,2);
}

function cnpj_situacao_label(string $s): string {
    return CNPJ_SITUACOES[$s] ?? $s;
}

function cnpj_porte_label(string $p): string {
    return CNPJ_PORTES[$p] ?? $p;
}
