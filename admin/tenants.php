<?php
require_once __DIR__ . '/../config.php';
require_super_admin();
require_once __DIR__ . '/_layout.php';

$status_filter = $_GET['status'] ?? '';
$q             = trim($_GET['q'] ?? '');

$where  = []; $params = [];
if ($status_filter && in_array($status_filter, ['active','pending','suspended','cancelled'], true)) {
    $where[] = 't.status = ?';
    $params[] = $status_filter;
}
if ($q !== '') {
    $where[] = '(t.name LIKE ? OR t.email LIKE ? OR t.slug LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$tenants = db_all(
    "SELECT t.*, p.name AS plan_name, p.price_cents
     FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id
     $wsql
     ORDER BY t.created_at DESC",
    $params
);

admin_layout('Tenants', 'tenants', function() use ($tenants, $status_filter, $q) {
?>
<script>
document.getElementById('header-actions').innerHTML = '<a href="tenant-edit.php" class="btn-action"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path d="M12 4v16m8-8H4"/></svg> Novo Tenant</a>';
</script>

<div class="panel">
  <form method="GET" style="display:flex;gap:1rem;align-items:end;margin-bottom:1.5rem;">
    <div style="flex:1;">
      <label>Buscar</label>
      <input type="text" name="q" placeholder="Nome, email ou slug..." value="<?= e($q) ?>">
    </div>
    <div style="width:180px;">
      <label>Status</label>
      <select name="status">
        <option value="">Todos</option>
        <option value="active"    <?= $status_filter==='active'?'selected':'' ?>>Ativos</option>
        <option value="pending"   <?= $status_filter==='pending'?'selected':'' ?>>Pendentes</option>
        <option value="suspended" <?= $status_filter==='suspended'?'selected':'' ?>>Suspensos</option>
        <option value="cancelled" <?= $status_filter==='cancelled'?'selected':'' ?>>Cancelados</option>
      </select>
    </div>
    <button type="submit" class="btn-action secondary">Filtrar</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>Tenant</th>
        <th>Plano</th>
        <th>Status</th>
        <th>Z-API</th>
        <th>Criado</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($tenants as $t): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:.75rem;">
            <div style="width:32px;height:32px;border-radius:50%;background:<?= e($t['brand_color'] ?: '#be123c') ?>;flex-shrink:0;"></div>
            <div>
              <div style="font-weight:700;"><?= e($t['brand_name'] ?: $t['name']) ?></div>
              <div style="font-size:.7rem;color:var(--ink-3);"><?= e($t['email']) ?></div>
            </div>
          </div>
        </td>
        <td>
          <?php if ($t['plan_name']): ?>
            <div style="font-weight:600;"><?= e($t['plan_name']) ?></div>
            <div style="font-size:.7rem;color:var(--ink-3);"><?= brl_cents((int)$t['price_cents']) ?>/mês</div>
          <?php else: ?>
            <span style="color:var(--ink-3);">—</span>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
        <td>
          <?php if ($t['zapi_instance']): ?>
            <span style="font-family:monospace;font-size:.7rem;color:var(--ink-3);">…<?= e(substr($t['zapi_instance'], -8)) ?></span>
          <?php else: ?>
            <span style="font-size:.7rem;color:var(--ink-3);">não atribuída</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--ink-3);font-size:.8rem;"><?= e(date('d/m/Y', strtotime($t['created_at']))) ?></td>
        <td style="text-align:right;">
          <a href="tenant-edit.php?id=<?= (int)$t['id'] ?>" class="btn-action secondary" style="font-size:.7rem;padding:.4rem .8rem;">Editar</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$tenants): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--ink-3);padding:2rem;">Nenhum tenant encontrado.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
});
