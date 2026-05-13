<?php
require_once __DIR__ . '/../config.php';
require_super_admin();
require_once __DIR__ . '/_layout.php';

$tot_tenants    = (int) db_val("SELECT COUNT(*) FROM tenants");
$tot_active     = (int) db_val("SELECT COUNT(*) FROM tenants WHERE status='active'");
$tot_pending    = (int) db_val("SELECT COUNT(*) FROM tenants WHERE status='pending'");
$tot_suspended  = (int) db_val("SELECT COUNT(*) FROM tenants WHERE status='suspended'");

$mrr_cents      = (int) db_val(
    "SELECT COALESCE(SUM(p.price_cents), 0)
     FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id
     WHERE t.status='active'"
);

$pool_total     = (int) db_val("SELECT COUNT(*) FROM zapi_pool");
$pool_available = (int) db_val("SELECT COUNT(*) FROM zapi_pool WHERE status='available'");
$pool_assigned  = (int) db_val("SELECT COUNT(*) FROM zapi_pool WHERE status='assigned'");

$recent = db_all(
    "SELECT t.*, p.name AS plan_name
     FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id
     ORDER BY t.created_at DESC LIMIT 5"
);

admin_layout('Visão Geral', 'overview', function() use ($tot_tenants, $tot_active, $tot_pending, $tot_suspended, $mrr_cents, $pool_total, $pool_available, $pool_assigned, $recent) {
?>
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    </div>
    <div class="stat-val"><?= $tot_tenants ?></div>
    <div class="stat-label">Tenants no total</div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="color:#15803d; background:rgba(22,163,74,0.1);">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div class="stat-val"><?= $tot_active ?></div>
    <div class="stat-label">Tenants ativos</div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="color:#a16207; background:rgba(234,179,8,0.1);">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div class="stat-val"><?= $tot_pending ?></div>
    <div class="stat-label">Aguardando ativação</div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div class="stat-val"><?= brl_cents($mrr_cents) ?></div>
    <div class="stat-label">MRR (receita mensal)</div>
  </div>
</div>

<div class="panel">
  <h2>Pool Z-API</h2>
  <div class="row-3">
    <div>
      <div style="font-size:.7rem;color:var(--ink-3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">Total no pool</div>
      <div style="font-size:1.5rem;font-weight:800;"><?= $pool_total ?></div>
    </div>
    <div>
      <div style="font-size:.7rem;color:#15803d;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">Disponíveis</div>
      <div style="font-size:1.5rem;font-weight:800;color:#15803d;"><?= $pool_available ?></div>
    </div>
    <div>
      <div style="font-size:.7rem;color:var(--cr);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">Atribuídas</div>
      <div style="font-size:1.5rem;font-weight:800;color:var(--cr);"><?= $pool_assigned ?></div>
    </div>
  </div>
  <?php if ($pool_available <= 2): ?>
    <div style="margin-top:1rem;background:#fef3c7;color:#92400e;padding:.75rem 1rem;border-radius:8px;font-size:.8rem;font-weight:600;">
      ⚠ Pool baixo (<?= $pool_available ?> disponível). Compre mais instâncias na Z-API e adicione em <a href="zapi-pool.php" style="color:inherit;text-decoration:underline;">Z-API Pool</a>.
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>Últimos tenants</h2>
  <table>
    <thead>
      <tr><th>Nome</th><th>Plano</th><th>Status</th><th>Criado</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($recent as $t): ?>
      <tr>
        <td>
          <div style="font-weight:700;"><?= e($t['name']) ?></div>
          <div style="font-size:.7rem;color:var(--ink-3);"><?= e($t['email']) ?></div>
        </td>
        <td><?= e($t['plan_name'] ?: '—') ?></td>
        <td><span class="badge badge-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
        <td style="color:var(--ink-3);font-size:.8rem;"><?= e(date('d/m/Y', strtotime($t['created_at']))) ?></td>
        <td><a href="tenant-edit.php?id=<?= (int) $t['id'] ?>" class="btn-action secondary" style="font-size:.7rem;padding:.4rem .8rem;">Editar</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?>
      <tr><td colspan="5" style="text-align:center;color:var(--ink-3);padding:2rem;">Nenhum tenant ainda. <a href="tenant-edit.php" style="color:var(--cr);">Criar o primeiro →</a></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
});
