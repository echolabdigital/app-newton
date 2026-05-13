<?php
/**
 * Layout do painel super-admin Newtonia.
 * Uso:
 *   admin_layout('Título da página', 'tenants', function() { ?>
 *      conteúdo HTML
 *   <?php });
 */

function admin_layout(string $title, string $active, callable $body): void {
    $items = [
        ['k'=>'overview',  'label'=>'Visão Geral',   'href'=>'index.php',     'icon'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['k'=>'tenants',   'label'=>'Tenants',       'href'=>'tenants.php',   'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['k'=>'zapi',      'label'=>'Z-API Pool',    'href'=>'zapi-pool.php', 'icon'=>'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z'],
        ['k'=>'plans',     'label'=>'Planos',        'href'=>'plans.php',     'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['k'=>'audit',     'label'=>'Audit Log',     'href'=>'audit.php',     'icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ];
    $userName = e(auth_user_name() ?: 'Admin');
    $initial  = strtoupper(mb_substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> — Newtonia</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --cr: #be123c; --cr-deep: #881128; --cr-glow: rgba(190,18,60,0.08);
  --ink: #0c0c0d; --ink-2: #3a3a40; --ink-3: #7a7a85;
  --fog: #f5f3ef; --fog-2: #ece9e3; --white: #fff;
  --border: rgba(0,0,0,0.08);
}
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Montserrat', system-ui, sans-serif;
  background: var(--fog); color: var(--ink);
  height: 100vh; display: flex; overflow: hidden;
  -webkit-font-smoothing: antialiased;
}
.sidebar {
  width: 260px; background: var(--white);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  padding: 1.5rem; flex-shrink: 0;
}
.brand { display: flex; align-items: center; gap: .75rem; text-decoration: none; margin-bottom: 2rem; }
.logo-box { width: 32px; height: 32px; border-radius: 8px; background: var(--cr); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; font-size: 1.1rem; }
.logo-text { font-weight: 800; font-size: 1.1rem; color: var(--ink); letter-spacing: -0.02em; }
.logo-text span { color: var(--cr); }
.logo-sub { font-size: .6rem; color: var(--ink-3); font-weight: 700; letter-spacing: .1em; text-transform: uppercase; margin-top: .2rem; }
.nav-menu { display: flex; flex-direction: column; gap: .35rem; flex: 1; }
.nav-item { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; border-radius: 10px; color: var(--ink-2); text-decoration: none; font-size: .82rem; font-weight: 600; transition: all .2s; }
.nav-item:hover { background: var(--fog); color: var(--ink); }
.nav-item.active { background: var(--cr-glow); color: var(--cr); }
.nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
.sidebar-footer { border-top: 1px solid var(--border); padding-top: 1rem; margin-top: auto; }
.user-profile { display: flex; align-items: center; gap: .75rem; padding: .5rem; }
.avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--ink); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; }
.user-info { display: flex; flex-direction: column; min-width: 0; }
.user-name { font-size: .8rem; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-plan { font-size: .65rem; color: var(--cr); font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
.logout-btn { display: block; text-align: center; margin-top: .75rem; padding: .5rem; font-size: .7rem; color: var(--ink-3); text-decoration: none; font-weight: 600; border-radius: 8px; }
.logout-btn:hover { color: var(--cr); background: var(--fog); }

.main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.header { height: 70px; background: var(--white); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; flex-shrink: 0; }
.header-title { font-size: 1.25rem; font-weight: 800; color: var(--ink); }
.header-actions { display: flex; align-items: center; gap: 1rem; }
.btn-action { padding: .6rem 1.2rem; background: var(--ink); color: #fff; border: none; border-radius: 8px; font-family: inherit; font-size: .8rem; font-weight: 600; cursor: pointer; transition: background .2s; display: inline-flex; align-items: center; gap: .5rem; text-decoration: none; }
.btn-action:hover { background: var(--cr); }
.btn-action.secondary { background: var(--white); color: var(--ink-2); border: 1px solid var(--border); }
.btn-action.secondary:hover { background: var(--fog); color: var(--ink); }
.btn-action.danger { background: #fef2f2; color: #991b1b; }
.btn-action.danger:hover { background: #dc2626; color: #fff; }

.viewport { flex: 1; padding: 2rem; overflow-y: auto; background: var(--fog); }

/* utilitários compartilhados */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.stat-card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; }
.stat-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--cr-glow); color: var(--cr); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
.stat-val { font-size: 2rem; font-weight: 800; line-height: 1; margin-bottom: .25rem; }
.stat-label { font-size: .8rem; font-weight: 500; color: var(--ink-3); }

.panel { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; }
.panel h2 { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }

table { width: 100%; border-collapse: collapse; font-size: .85rem; }
th { text-align: left; padding: .85rem .75rem; font-weight: 700; color: var(--ink-3); border-bottom: 1px solid var(--border); font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; }
td { padding: 1rem .75rem; border-bottom: 1px solid var(--border); }
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--fog); }

.badge { display: inline-block; font-size: .65rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; letter-spacing: .05em; text-transform: uppercase; }
.badge-active     { background: rgba(22,163,74,0.12);  color: #15803d; }
.badge-pending    { background: rgba(234,179,8,0.12);  color: #a16207; }
.badge-suspended  { background: rgba(239,68,68,0.12);  color: #b91c1c; }
.badge-cancelled  { background: rgba(107,114,128,0.12);color: #4b5563; }
.badge-available  { background: rgba(22,163,74,0.12);  color: #15803d; }
.badge-assigned   { background: rgba(190,18,60,0.12);  color: var(--cr); }
.badge-maintenance{ background: rgba(234,179,8,0.12);  color: #a16207; }

input, select, textarea {
  width: 100%; padding: .7rem .85rem;
  border: 1px solid var(--border); border-radius: 8px;
  font-family: inherit; font-size: .85rem;
  background: var(--white);
}
input:focus, select:focus, textarea:focus { outline: none; border-color: var(--cr); box-shadow: 0 0 0 3px var(--cr-glow); }

label { display: block; font-size: .72rem; font-weight: 700; color: var(--ink-2); margin-bottom: .35rem; text-transform: uppercase; letter-spacing: .03em; }
.field { margin-bottom: 1rem; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .row-2, .row-3 { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<aside class="sidebar">
  <a href="index.php" class="brand">
    <div class="logo-box">N</div>
    <div>
      <div class="logo-text">NEWTON<span>IA</span>.</div>
      <div class="logo-sub">Super Admin</div>
    </div>
  </a>

  <nav class="nav-menu">
    <?php foreach ($items as $i): ?>
      <a href="<?= $i['href'] ?>" class="nav-item <?= $active===$i['k']?'active':'' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="<?= $i['icon'] ?>"/></svg>
        <?= e($i['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="avatar"><?= $initial ?></div>
      <div class="user-info">
        <span class="user-name"><?= $userName ?></span>
        <span class="user-plan">Equipe</span>
      </div>
    </div>
    <a href="../logout.php" class="logout-btn">Sair</a>
  </div>
</aside>

<main class="main">
  <header class="header">
    <div class="header-title"><?= e($title) ?></div>
    <div class="header-actions" id="header-actions"></div>
  </header>
  <div class="viewport">
    <?= flash_render() ?>
    <?php $body(); ?>
  </div>
</main>
</body>
</html>
<?php
}
