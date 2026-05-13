<?php
/**
 * NEWTONIA — Guards de acesso
 *
 * Toda página deve chamar uma dessas no topo:
 *   require_login()        — qualquer usuário logado
 *   require_super_admin()  — só equipe Newtonia (/admin/)
 *   require_tenant()       — usuário logado COM tenant ativo (/app/)
 */

function require_login(): void {
    auth_start_session();
    if (!auth_user_id()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?redirect=' . $back);
        exit;
    }
}

function require_super_admin(): void {
    require_login();
    if (!auth_is_super()) {
        http_response_code(403);
        die('Acesso negado. Esta área é restrita à equipe Newtonia.');
    }
}

function require_tenant(): array {
    require_login();
    $t = tenant_current();
    if (!$t) {
        header('Location: /select-tenant.php');
        exit;
    }
    if ($t['status'] === 'suspended') {
        die('Conta suspensa. Entre em contato com o suporte: contato@newtonia.app');
    }
    if ($t['status'] === 'cancelled') {
        die('Conta cancelada.');
    }
    return $t;
}
