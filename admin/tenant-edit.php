<?php
require_once __DIR__ . '/../config.php';
require_super_admin();
require_once __DIR__ . '/_layout.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_new = !$id;

if (!$is_new) {
    $tenant = db_one("SELECT * FROM tenants WHERE id = ?", [$id]);
    if (!$tenant) { http_response_code(404); die('Tenant não encontrado.'); }
} else {
    $tenant = [
        'slug'=>'', 'name'=>'', 'email'=>'', 'phone'=>'', 'document'=>'',
        'status'=>'pending', 'plan_id'=>null,
        'brand_name'=>'', 'brand_color'=>'#be123c', 'custom_domain'=>'',
        'limit_dispatch_daily'=>200, 'limit_contacts'=>5000, 'limit_extractor_monthly'=>1000,
        'zapi_instance'=>null, 'zapi_phone'=>null, 'zapi_status'=>'disconnected',
        'notes'=>''
    ];
}

$plans = db_all("SELECT * FROM plans WHERE active=1 ORDER BY display_order");

// AÇÕES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['_action'] ?? 'save';

    try {
        if ($action === 'save') {
            $data = [
                'slug'          => slugify($_POST['slug'] ?? '') ?: slugify($_POST['name'] ?? ''),
                'name'          => trim($_POST['name'] ?? ''),
                'email'         => strtolower(trim($_POST['email'] ?? '')),
                'phone'         => trim($_POST['phone'] ?? '') ?: null,
                'document'      => trim($_POST['document'] ?? '') ?: null,
                'status'        => $_POST['status'] ?? 'pending',
                'plan_id'       => $_POST['plan_id'] ? (int) $_POST['plan_id'] : null,
                'brand_name'    => trim($_POST['brand_name'] ?? '') ?: null,
                'brand_color'   => $_POST['brand_color'] ?: '#be123c',
                'custom_domain' => trim($_POST['custom_domain'] ?? '') ?: null,
                'limit_dispatch_daily'    => (int) ($_POST['limit_dispatch_daily'] ?? 200),
                'limit_contacts'          => (int) ($_POST['limit_contacts'] ?? 5000),
                'limit_extractor_monthly' => (int) ($_POST['limit_extractor_monthly'] ?? 1000),
                'notes'         => trim($_POST['notes'] ?? '') ?: null,
            ];
            // Aplica limites do plano se mudou
            if ($data['plan_id']) {
                $p = db_one("SELECT * FROM plans WHERE id = ?", [$data['plan_id']]);
                if ($p && (!$id || (int)$tenant['plan_id'] !== (int)$data['plan_id'])) {
                    $data['limit_dispatch_daily']    = (int) $p['limit_dispatch_daily'];
                    $data['limit_contacts']          = (int) $p['limit_contacts'];
                    $data['limit_extractor_monthly'] = (int) $p['limit_extractor_monthly'];
                }
            }
            if ($is_new) {
                $id = db_insert('tenants', $data);
                audit_log('tenant.created', 'tenant', $id, ['name' => $data['name']]);
                flash_set('success', 'Tenant criado com sucesso.');
            } else {
                db_update('tenants', $data, 'id = :id', ['id' => $id]);
                audit_log('tenant.updated', 'tenant', $id);
                flash_set('success', 'Tenant atualizado.');
            }
            header('Location: tenant-edit.php?id=' . $id); exit;
        }

        if ($action === 'assign_zapi') {
            if (tenant_assign_zapi($id)) {
                flash_set('success', 'Instância Z-API atribuída do pool.');
            } else {
                flash_set('error', 'Pool vazio. Adicione instâncias em Z-API Pool.');
            }
            header('Location: tenant-edit.php?id=' . $id); exit;
        }

        if ($action === 'release_zapi') {
            tenant_release_zapi($id);
            flash_set('success', 'Instância devolvida ao pool.');
            header('Location: tenant-edit.php?id=' . $id); exit;
        }

        if ($action === 'create_user') {
            $email = strtolower(trim($_POST['user_email'] ?? ''));
            $name  = trim($_POST['user_name'] ?? '');
            $pass  = $_POST['user_pass'] ?? '';
            $role  = $_POST['user_role'] ?? 'owner';
            if (!$email || !$pass || strlen($pass) < 6) {
                flash_set('error', 'Email e senha (mín 6) são obrigatórios.');
            } else {
                $existing = db_one("SELECT id FROM users WHERE email = ?", [$email]);
                if ($existing) {
                    $uid = (int) $existing['id'];
                } else {
                    $uid = db_insert('users', [
                        'email' => $email,
                        'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                        'name' => $name ?: null,
                        'is_super_admin' => 0,
                    ]);
                }
                // vincula
                $exists = db_val("SELECT 1 FROM tenant_users WHERE tenant_id = ? AND user_id = ?", [$id, $uid]);
                if (!$exists) {
                    db_insert('tenant_users', ['tenant_id' => $id, 'user_id' => $uid, 'role' => $role]);
                }
                audit_log('tenant_user.created', 'tenant', $id, ['user_id' => $uid, 'role' => $role]);
                flash_set('success', 'Usuário criado e vinculado ao tenant.');
            }
            header('Location: tenant-edit.php?id=' . $id); exit;
        }
    } catch (\Throwable $e) {
        flash_set('error', 'Erro: ' . $e->getMessage());
        header('Location: tenant-edit.php?id=' . $id); exit;
    }
}

$users = $is_new ? [] : db_all(
    "SELECT u.*, tu.role FROM users u
     JOIN tenant_users tu ON tu.user_id = u.id
     WHERE tu.tenant_id = ? ORDER BY tu.created_at",
    [$id]
);

admin_layout(($is_new ? 'Novo Tenant' : 'Editar: ' . $tenant['name']), 'tenants', function() use ($tenant, $is_new, $id, $plans, $users) {
?>

<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="_action" value="save">

  <div class="panel">
    <h2>Identificação</h2>
    <div class="row-2">
      <div class="field">
        <label>Nome da empresa *</label>
        <input type="text" name="name" required value="<?= e($tenant['name']) ?>">
      </div>
      <div class="field">
        <label>Slug (URL) *</label>
        <input type="text" name="slug" pattern="[a-z0-9\-]+" required value="<?= e($tenant['slug']) ?>" placeholder="auto a partir do nome">
      </div>
    </div>
    <div class="row-3">
      <div class="field">
        <label>Email *</label>
        <input type="email" name="email" required value="<?= e($tenant['email']) ?>">
      </div>
      <div class="field">
        <label>Telefone</label>
        <input type="text" name="phone" value="<?= e($tenant['phone']) ?>">
      </div>
      <div class="field">
        <label>CNPJ/CPF</label>
        <input type="text" name="document" value="<?= e($tenant['document']) ?>">
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>Plano e Status</h2>
    <div class="row-2">
      <div class="field">
        <label>Plano</label>
        <select name="plan_id">
          <option value="">— sem plano —</option>
          <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)$tenant['plan_id']===(int)$p['id']?'selected':'' ?>>
              <?= e($p['name']) ?> · <?= brl_cents((int)$p['price_cents']) ?>/mês
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Status</label>
        <select name="status">
          <option value="pending"   <?= $tenant['status']==='pending'?'selected':'' ?>>Pendente (aguarda pagamento)</option>
          <option value="active"    <?= $tenant['status']==='active'?'selected':'' ?>>Ativo</option>
          <option value="suspended" <?= $tenant['status']==='suspended'?'selected':'' ?>>Suspenso</option>
          <option value="cancelled" <?= $tenant['status']==='cancelled'?'selected':'' ?>>Cancelado</option>
        </select>
      </div>
    </div>
    <div class="row-3">
      <div class="field">
        <label>Limite disparos/dia</label>
        <input type="number" name="limit_dispatch_daily" value="<?= (int)$tenant['limit_dispatch_daily'] ?>" min="0">
      </div>
      <div class="field">
        <label>Limite contatos</label>
        <input type="number" name="limit_contacts" value="<?= (int)$tenant['limit_contacts'] ?>" min="0">
      </div>
      <div class="field">
        <label>Limite extrações/mês</label>
        <input type="number" name="limit_extractor_monthly" value="<?= (int)$tenant['limit_extractor_monthly'] ?>" min="0">
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>White-label</h2>
    <div class="row-3">
      <div class="field">
        <label>Nome exibido no painel</label>
        <input type="text" name="brand_name" value="<?= e($tenant['brand_name']) ?>" placeholder="Padrão: nome da empresa">
      </div>
      <div class="field">
        <label>Cor primária</label>
        <input type="color" name="brand_color" value="<?= e($tenant['brand_color'] ?: '#be123c') ?>" style="height:42px;padding:.3rem;">
      </div>
      <div class="field">
        <label>Domínio próprio (CNAME)</label>
        <input type="text" name="custom_domain" value="<?= e($tenant['custom_domain']) ?>" placeholder="ex: app.casadasflores.online">
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>Notas internas</h2>
    <div class="field">
      <textarea name="notes" rows="3" style="resize:vertical;font-family:inherit;"><?= e($tenant['notes']) ?></textarea>
    </div>
  </div>

  <div style="display:flex;gap:1rem;justify-content:flex-end;margin-bottom:2rem;">
    <a href="tenants.php" class="btn-action secondary">Cancelar</a>
    <button type="submit" class="btn-action"><?= $is_new ? 'Criar Tenant' : 'Salvar alterações' ?></button>
  </div>
</form>

<?php if (!$is_new): ?>

<!-- Z-API -->
<div class="panel">
  <h2>Instância Z-API</h2>
  <?php if ($tenant['zapi_instance']): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
      <div>
        <div style="font-family:monospace;font-size:.85rem;"><?= e($tenant['zapi_instance']) ?></div>
        <div style="font-size:.7rem;color:var(--ink-3);margin-top:.25rem;">
          Status: <strong><?= e($tenant['zapi_status']) ?></strong>
          <?= $tenant['zapi_phone'] ? '· Conectado em ' . e($tenant['zapi_phone']) : '' ?>
        </div>
      </div>
      <form method="POST" onsubmit="return confirm('Devolver esta instância ao pool? O tenant ficará sem WhatsApp.');">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="release_zapi">
        <button type="submit" class="btn-action danger">Devolver ao pool</button>
      </form>
    </div>
  <?php else: ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
      <p style="color:var(--ink-3);font-size:.85rem;margin:0;">Nenhuma instância atribuída. Quando você atribuir, uma instância <code>available</code> do pool será reservada.</p>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="assign_zapi">
        <button type="submit" class="btn-action">Atribuir do pool</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<!-- Usuários -->
<div class="panel">
  <h2>Usuários do tenant</h2>
  <?php if ($users): ?>
    <table style="margin-bottom:1.5rem;">
      <thead><tr><th>Email</th><th>Nome</th><th>Role</th><th>Último login</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['name'] ?? '') ?></td>
            <td><span class="badge badge-active"><?= e($u['role']) ?></span></td>
            <td style="color:var(--ink-3);font-size:.8rem;"><?= $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime($u['last_login_at']))) : 'nunca' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="POST" style="border-top:1px solid var(--border);padding-top:1.25rem;">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="create_user">
    <h3 style="font-size:.85rem;font-weight:700;margin-bottom:1rem;">Adicionar usuário ao tenant</h3>
    <div class="row-3">
      <div class="field">
        <label>Email *</label>
        <input type="email" name="user_email" required>
      </div>
      <div class="field">
        <label>Nome</label>
        <input type="text" name="user_name">
      </div>
      <div class="field">
        <label>Senha inicial *</label>
        <input type="text" name="user_pass" required minlength="6">
      </div>
    </div>
    <div class="row-2">
      <div class="field">
        <label>Role</label>
        <select name="user_role">
          <option value="owner">Owner (controle total)</option>
          <option value="admin">Admin</option>
          <option value="operator">Operador</option>
        </select>
      </div>
      <div style="display:flex;align-items:end;">
        <button type="submit" class="btn-action">Criar usuário</button>
      </div>
    </div>
  </form>
</div>

<?php endif; ?>
<?php
});
