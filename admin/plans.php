<?php
require_once __DIR__ . '/../config.php';
require_super_admin();
require_once __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $pid = (int) ($_POST['plan_id'] ?? 0);
        $data = [
            'name'        => trim($_POST['name'] ?? ''),
            'price_cents' => (int) round(((float) str_replace(',', '.', $_POST['price'] ?? 0)) * 100),
            'limit_dispatch_daily'    => (int) ($_POST['limit_dispatch_daily'] ?? 0),
            'limit_contacts'          => (int) ($_POST['limit_contacts'] ?? 0),
            'limit_extractor_monthly' => (int) ($_POST['limit_extractor_monthly'] ?? 0),
            'active'                  => isset($_POST['active']) ? 1 : 0,
        ];
        if ($pid) {
            db_update('plans', $data, 'id = :id', ['id' => $pid]);
            audit_log('plan.updated', 'plan', $pid);
            flash_set('success', 'Plano atualizado.');
        } else {
            $data['code'] = slugify($data['name']);
            $data['features'] = json_encode([]);
            db_insert('plans', $data);
            flash_set('success', 'Plano criado.');
        }
    } catch (\Throwable $e) {
        flash_set('error', 'Erro: ' . $e->getMessage());
    }
    header('Location: plans.php'); exit;
}

$plans = db_all("SELECT * FROM plans ORDER BY display_order, id");

admin_layout('Planos', 'plans', function() use ($plans) {
?>
<div class="panel">
  <h2>Planos disponíveis</h2>
  <p style="color:var(--ink-3);font-size:.8rem;margin-bottom:1rem;">
    Edite limites/preço diretamente abaixo. Mudanças NÃO afetam tenants já criados — eles mantêm os limites do momento da criação. Pra reaplicar, edite o tenant e troque o plano.
  </p>
  <table>
    <thead>
      <tr><th>Plano</th><th>Preço/mês</th><th>Disparos/dia</th><th>Contatos</th><th>Extrações/mês</th><th>Ativo</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($plans as $p): ?>
      <tr>
        <td><strong><?= e($p['name']) ?></strong> <span style="font-size:.7rem;color:var(--ink-3);font-family:monospace;">[<?= e($p['code']) ?>]</span></td>
        <td><?= brl_cents((int)$p['price_cents']) ?></td>
        <td><?= number_format((int)$p['limit_dispatch_daily'], 0, ',', '.') ?></td>
        <td><?= number_format((int)$p['limit_contacts'], 0, ',', '.') ?></td>
        <td><?= number_format((int)$p['limit_extractor_monthly'], 0, ',', '.') ?></td>
        <td><?= $p['active'] ? '<span class="badge badge-active">sim</span>' : '<span class="badge badge-cancelled">não</span>' ?></td>
        <td>
          <button type="button" class="btn-action secondary" style="font-size:.7rem;padding:.4rem .8rem;" onclick="editPlan(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">Editar</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel" id="edit-form">
  <h2 id="edit-title">Novo plano</h2>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="plan_id" id="plan_id" value="">
    <div class="row-2">
      <div class="field"><label>Nome *</label><input type="text" name="name" id="f-name" required></div>
      <div class="field"><label>Preço mensal (R$) *</label><input type="text" name="price" id="f-price" required placeholder="99,00"></div>
    </div>
    <div class="row-3">
      <div class="field"><label>Disparos/dia</label><input type="number" name="limit_dispatch_daily" id="f-dispatch" min="0"></div>
      <div class="field"><label>Limite contatos</label><input type="number" name="limit_contacts" id="f-contacts" min="0"></div>
      <div class="field"><label>Extrações/mês</label><input type="number" name="limit_extractor_monthly" id="f-extractor" min="0"></div>
    </div>
    <div class="field" style="display:flex;align-items:center;gap:.5rem;">
      <input type="checkbox" name="active" id="f-active" style="width:auto;" checked>
      <label for="f-active" style="margin:0;">Ativo (visível pra novos tenants)</label>
    </div>
    <div style="display:flex;gap:1rem;justify-content:flex-end;">
      <button type="button" class="btn-action secondary" onclick="resetForm()">Limpar</button>
      <button type="submit" class="btn-action" id="submit-btn">Criar plano</button>
    </div>
  </form>
</div>

<script>
function editPlan(p) {
  document.getElementById('plan_id').value = p.id;
  document.getElementById('f-name').value = p.name;
  document.getElementById('f-price').value = (p.price_cents / 100).toFixed(2).replace('.', ',');
  document.getElementById('f-dispatch').value = p.limit_dispatch_daily;
  document.getElementById('f-contacts').value = p.limit_contacts;
  document.getElementById('f-extractor').value = p.limit_extractor_monthly;
  document.getElementById('f-active').checked = p.active == 1;
  document.getElementById('edit-title').innerText = 'Editando: ' + p.name;
  document.getElementById('submit-btn').innerText = 'Salvar alterações';
  document.getElementById('edit-form').scrollIntoView({behavior:'smooth'});
}
function resetForm() {
  document.querySelector('#edit-form form').reset();
  document.getElementById('plan_id').value = '';
  document.getElementById('edit-title').innerText = 'Novo plano';
  document.getElementById('submit-btn').innerText = 'Criar plano';
}
</script>
<?php
});
