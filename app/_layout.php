<?php
/**
 * Layout do painel TENANT (white-label).
 * Aplica cor primária e nome do tenant via tenant_brand().
 *
 * Uso:
 *   app_layout('Título', 'crm', function() { ?> conteúdo HTML <?php });
 */

function app_layout(string $title, string $active, callable $body): void {
    $brand = tenant_brand();
    $tenant = tenant_current();

    $items = [
        ['k'=>'overview',  'label'=>'Visão Geral',     'href'=>'index.php',     'icon'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['k'=>'extrator',  'label'=>'Extrator Maps',   'href'=>'#',             'icon'=>'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z', 'soon'=>true],
        ['k'=>'crm',       'label'=>'CRM Kanban',      'href'=>'#',             'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'soon'=>true],
        ['k'=>'disparador','label'=>'Disparador WPP',  'href'=>'#',             'icon'=>'M13 10V3L4 14h7v7l9-11h-7z', 'soon'=>true],
        ['k'=>'conversas', 'label'=>'Conversas',       'href'=>'#',             'icon'=>'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'soon'=>true],
        ['k'=>'config',    'label'=>'Configurações',   'href'=>'#',             'icon'=>'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'soon'=>true],
    ];

    $userName = e(auth_user_name() ?: $brand['name']);
    $initial  = strtoupper(mb_substr($brand['name'], 0, 1));
    $brandColor = e($brand['color']);
    $isSuper = auth_is_super();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> — <?= e($brand['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --cr: <?= $brandColor ?>;
  --cr-glow: <?= $brandColor ?>15;
  --ink: #0c0c0d; --ink-2: #3a3a40; --ink-3: #7a7a85;
  --fog: #f5f3ef; --white: #fff;
  --border: rgba(0,0,0,0.08);
}
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Montserrat', system-ui, sans-serif; background: var(--fog); color: var(--ink); height: 100vh; display: flex; overflow: hidden; -webkit-font-smoothing: antialiased; }
.sidebar { width: 260px; background: var(--white); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 1.5rem; flex-shrink: 0; }
.brand { display: flex; align-items: center; gap: .75rem; text-decoration: none; margin-bottom: 2rem; }
.logo-box { width: 32px; height: 32px; border-radius: 8px; background: var(--cr); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; font-size: 1.1rem; flex-shrink: 0; }
.logo-text { font-weight: 800; font-size: 1.05rem; color: var(--ink); letter-spacing: -0.02em; line-height: 1.1; }
.logo-sub { font-size: .55rem; color: var(--ink-3); font-weight: 700; letter-spacing: .12em; text-transform: uppercase; margin-top: .15rem; }
.nav-menu { display: flex; flex-direction: column; gap: .35rem; flex: 1; }
.nav-item { display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; border-radius: 10px; color: var(--ink-2); text-decoration: none; font-size: .82rem; font-weight: 600; transition: all .2s; position: relative; }
.nav-item:hover { background: var(--fog); color: var(--ink); }
.nav-item.active { background: var(--cr-glow); color: var(--cr); }
.nav-item.soon { opacity: .55; cursor: not-allowed; }
.nav-item.soon:hover { background: transparent; color: var(--ink-2); }
.nav-item .badge-soon { margin-left: auto; font-size: .55rem; font-weight: 800; background: var(--ink); color: #fff; padding: 2px 6px; border-radius: 4px; letter-spacing: .04em; }
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
.viewport { flex: 1; padding: 2rem; overflow-y: auto; background: var(--fog); }

.super-bar { background: var(--ink); color: #fff; padding: .55rem 2rem; font-size: .72rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
.super-bar a { color: #fff; opacity: .7; text-decoration: none; }
.super-bar a:hover { opacity: 1; text-decoration: underline; }

.btn-action { padding: .6rem 1.2rem; background: var(--ink); color: #fff; border: none; border-radius: 8px; font-family: inherit; font-size: .8rem; font-weight: 600; cursor: pointer; transition: background .2s; display: inline-flex; align-items: center; gap: .5rem; text-decoration: none; }
.btn-action:hover { background: var(--cr); }
.btn-action.secondary { background: var(--white); color: var(--ink-2); border: 1px solid var(--border); }
.btn-action.secondary:hover { background: var(--fog); color: var(--ink); }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.stat-card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; }
.stat-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--cr-glow); color: var(--cr); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
.stat-val { font-size: 2rem; font-weight: 800; line-height: 1; margin-bottom: .25rem; }
.stat-label { font-size: .8rem; font-weight: 500; color: var(--ink-3); }

.panel { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; }
.panel h2 { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }
</style>
</head>
<body>

<aside class="sidebar">
  <a href="index.php" class="brand">
    <?php if ($brand['logo']): ?>
      <img src="<?= e($brand['logo']) ?>" alt="<?= e($brand['name']) ?>" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">
    <?php else: ?>
      <div class="logo-box"><?= $initial ?></div>
    <?php endif; ?>
    <div>
      <div class="logo-text"><?= e($brand['name']) ?></div>
      <div class="logo-sub">Powered by Newtonia</div>
    </div>
  </a>

  <nav class="nav-menu">
    <?php foreach ($items as $i): ?>
      <a href="<?= isset($i['soon']) && $i['soon'] ? '#' : $i['href'] ?>"
         class="nav-item <?= $active===$i['k']?'active':'' ?> <?= isset($i['soon']) && $i['soon']?'soon':'' ?>"
         <?= isset($i['soon']) && $i['soon'] ? 'onclick="event.preventDefault();"' : '' ?>>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="<?= $i['icon'] ?>"/></svg>
        <span><?= e($i['label']) ?></span>
        <?php if (isset($i['soon']) && $i['soon']): ?><span class="badge-soon">Em breve</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="avatar"><?= strtoupper(mb_substr(auth_user_name() ?: 'U', 0, 1)) ?></div>
      <div class="user-info">
        <span class="user-name"><?= e(auth_user_name() ?: auth_user_email()) ?></span>
        <span class="user-plan"><?= e(tenant_role() ?: 'usuário') ?></span>
      </div>
    </div>
    <a href="../logout.php" class="logout-btn">Sair</a>
  </div>
</aside>

<main class="main">
  <?php if ($isSuper): ?>
    <div class="super-bar">
      <span>🔧 Você está visualizando como super-admin · Tenant: <strong><?= e($tenant['name']) ?></strong></span>
      <a href="../admin/">← Voltar pro Super Admin</a>
    </div>
  <?php endif; ?>
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
