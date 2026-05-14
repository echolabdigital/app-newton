<?php
/**
 * Newton AI — Admin layout wrapper
 * Usage: admin_layout(string $title, string $active, callable $body)
 */

function admin_layout(string $title, string $active, callable $body): void
{
    $nav = [
        ['k' => 'overview',    'label' => 'Visão Geral',    'href' => 'index.php',        'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['k' => 'tenants',     'label' => 'Tenants',        'href' => 'tenants.php',      'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['k' => 'zapi',        'label' => 'Z-API Pool',     'href' => 'zapi.php',         'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
        ['k' => 'plans',       'label' => 'Planos',         'href' => 'plans.php',        'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['k' => 'cnpj-limits', 'label' => 'Limites CNPJ',  'href' => 'cnpj-limits.php',  'icon' => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4'],
        ['k' => 'audit',       'label' => 'Auditoria',      'href' => 'audit.php',        'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-2.962z'],
    ];

    $brand = function_exists('admin_brand') ? admin_brand() : 'Newton AI';
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> | <?= htmlspecialchars($brand) ?> Admin</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root { --cr: #6366f1; --cr-glow: #818cf8; --ink: #1e1b4b; --sidebar-w: 220px; }
  body  { font-family: system-ui, sans-serif; background: #f5f5f7; color: #1f2937; display: flex; min-height: 100vh; }

  /* Sidebar */
  .adm-side { width: var(--sidebar-w); background: var(--ink); color: #c7d2fe; display: flex; flex-direction: column; padding: 24px 0; flex-shrink: 0; }
  .adm-logo { padding: 0 20px 24px; font-weight: 700; font-size: 1.05rem; color: #fff; letter-spacing: -.3px; }
  .adm-logo small { display: block; font-size: .7rem; font-weight: 400; color: #818cf8; margin-top: 2px; }
  nav a { display: flex; align-items: center; gap: 10px; padding: 10px 20px; font-size: .88rem; color: #a5b4fc; text-decoration: none; border-left: 3px solid transparent; transition: background .15s; }
  nav a:hover   { background: rgba(255,255,255,.07); color: #fff; }
  nav a.active  { background: rgba(255,255,255,.1); color: #fff; border-left-color: var(--cr-glow); }
  nav svg { width: 18px; height: 18px; flex-shrink: 0; }

  /* Main */
  .adm-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
  .adm-header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; }
  .adm-header h1 { font-size: 1rem; font-weight: 600; color: #111827; }
  .adm-content { padding: 28px; overflow-y: auto; flex: 1; }
</style>
</head>
<body>

<aside class="adm-side">
  <div class="adm-logo">
    <?= htmlspecialchars($brand) ?>
    <small>Painel Admin</small>
  </div>
  <nav>
    <?php foreach ($nav as $item): ?>
    <a href="<?= $item['href'] ?>" class="<?= $active === $item['k'] ? 'active' : '' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>"/>
      </svg>
      <?= htmlspecialchars($item['label']) ?>
    </a>
    <?php endforeach; ?>
  </nav>
</aside>

<div class="adm-main">
  <header class="adm-header">
    <h1><?= htmlspecialchars($title) ?></h1>
    <a href="../app/logout.php" style="font-size:.82rem;color:#6b7280;text-decoration:none">Sair</a>
  </header>
  <div class="adm-content">
    <?php $body(); ?>
  </div>
</div>

</body>
</html>
    <?php
}
