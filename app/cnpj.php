<?php
/**
 * Newton AI — Newton CNPJ: busca e prospecção de empresas
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/cnpj_db.php';

$f = array_map('trim', array_filter($_GET, 'is_string'));

$page    = max(1, (int) ($_GET['page'] ?? 1));
$per     = 20;
$results = ['rows' => [], 'total' => 0];

if (!empty($f)) {
    $results = cnpj_search($f, $page, $per);
}

$total_pages = min(200, (int) ceil($results['total'] / $per));

// Quota info
$q_limit = cnpj_monthly_limit($tenant_id);
$q_used  = cnpj_monthly_used($tenant_id);
$q_pct   = cnpj_usage_pct($q_used, $q_limit);
$q_addon = (int) db_val(
    'SELECT cnpj_addon_credits FROM tenants WHERE id = ?',
    [$tenant_id]
);
$q_bar_class = $q_pct >= 90 ? 'danger' : ($q_pct >= 50 ? 'warn' : 'ok');

app_layout('Newton CNPJ', 'cnpj', function () use ($f, $results, $page, $total_pages, $q_limit, $q_used, $q_pct, $q_addon, $q_bar_class) {
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Newton CNPJ</title>
<style>
  .cnpj-wrap        { display:flex; gap:20px; align-items:flex-start; }
  .cnpj-filters     { width:240px; flex-shrink:0; background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .cnpj-results     { flex:1; min-width:0; }
  .filter-group     { margin-bottom:14px; }
  .filter-group label  { display:block; font-size:.78rem; font-weight:600; color:#6b7280; margin-bottom:4px; }
  .filter-group input,
  .filter-group select { width:100%; box-sizing:border-box; padding:7px 10px; border:1px solid #e5e7eb; border-radius:8px; font-size:.85rem; }
  .btn-primary      { background:var(--cr,#6366f1); color:#fff; border:none; border-radius:8px; padding:9px 18px; cursor:pointer; font-size:.9rem; width:100%; }
  .quota-bar-wrap   { background:#e5e7eb; border-radius:6px; height:10px; margin-top:6px; }
  .quota-bar        { height:10px; border-radius:6px; transition:width .4s; background:#22c55e; }
  .quota-bar.warn   { background:#f59e0b; }
  .quota-bar.danger { background:#ef4444; }
  .quota-info       { background:#fff; border-radius:12px; padding:14px 18px; margin-bottom:16px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .quota-info small { color:#6b7280; font-size:.8rem; }
  .results-card     { background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .results-table    { width:100%; border-collapse:collapse; font-size:.85rem; }
  .results-table th { background:#f9fafb; font-weight:600; padding:10px 12px; text-align:left; border-bottom:1px solid #e5e7eb; }
  .results-table td { padding:10px 12px; border-bottom:1px solid #f3f4f6; vertical-align:top; }
  .results-table tr:last-child td { border-bottom:none; }
  .badge            { display:inline-block; padding:2px 8px; border-radius:999px; font-size:.72rem; font-weight:600; }
  .badge-green      { background:#d1fae5; color:#065f46; }
  .badge-red        { background:#fee2e2; color:#991b1b; }
  .badge-gray       { background:#f3f4f6; color:#374151; }
  .pagination       { display:flex; gap:6px; align-items:center; padding:14px 18px; flex-wrap:wrap; }
  .pagination a,
  .pagination span  { padding:6px 12px; border-radius:6px; font-size:.85rem; text-decoration:none; border:1px solid #e5e7eb; }
  .pagination a     { color:var(--cr,#6366f1); }
  .pagination span.current { background:var(--cr,#6366f1); color:#fff; border-color:var(--cr,#6366f1); }
  .export-btn       { display:inline-flex; align-items:center; gap:6px; background:#22c55e; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:.85rem; cursor:pointer; text-decoration:none; }
  .export-btn:hover { background:#16a34a; }
  .empty-state      { padding:60px 20px; text-align:center; color:#9ca3af; }
  .modal-bg         { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:100; align-items:center; justify-content:center; }
  .modal-bg.open    { display:flex; }
  .modal            { background:#fff; border-radius:14px; padding:28px; max-width:420px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,.2); }
  .modal h3         { margin:0 0 16px; }
  .modal input, .modal textarea { width:100%; box-sizing:border-box; padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:.9rem; margin-bottom:10px; }
  .modal-actions    { display:flex; gap:10px; justify-content:flex-end; margin-top:6px; }
  .btn-cancel       { background:#f3f4f6; color:#374151; border:none; border-radius:8px; padding:8px 16px; cursor:pointer; }
</style>
</head>
<body>

<!-- Quota bar -->
<div class="quota-info">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <strong>Newton CNPJ</strong> &nbsp;
      <small>Leads este mês: <?= number_format($q_used) ?> / <?= number_format($q_limit) ?>
        <?php if ($q_addon > 0): ?> &nbsp;|&nbsp; <?= number_format($q_addon) ?> créditos extras<?php endif; ?>
      </small>
    </div>
    <a href="cnpj-lists.php" style="font-size:.82rem;color:var(--cr,#6366f1)">Minhas listas ›</a>
  </div>
  <div class="quota-bar-wrap">
    <div class="quota-bar <?= $q_bar_class ?>" style="width:<?= $q_pct ?>%"></div>
  </div>
</div>

<form method="get" id="cnpj-form">
<div class="cnpj-wrap">

  <!-- Filters -->
  <aside class="cnpj-filters">
    <div class="filter-group">
      <label>CNPJ ou Razão Social</label>
      <input type="text" name="q" value="<?= e($f['q'] ?? '') ?>" placeholder="Ex: 12.345.678 ou Empresa XPTO">
    </div>
    <div class="filter-group">
      <label>Situação</label>
      <select name="situacao">
        <option value="">Todas</option>
        <?php foreach (CNPJ_SITUACOES as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($f['situacao'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label>UF</label>
      <select name="uf" id="uf-sel" onchange="loadMunicipios()">
        <option value="">Todos</option>
        <?php foreach (CNPJ_UFS as $uf): ?>
        <option value="<?= $uf ?>" <?= ($f['uf'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label>Município</label>
      <select name="municipio" id="mun-sel">
        <option value="">Todos</option>
        <?php if (!empty($f['municipio'])): ?>
        <option value="<?= e($f['municipio']) ?>" selected><?= e($f['municipio']) ?></option>
        <?php endif; ?>
      </select>
    </div>
    <div class="filter-group">
      <label>CNAE principal</label>
      <input type="text" name="cnae" value="<?= e($f['cnae'] ?? '') ?>" placeholder="Ex: 6201500">
    </div>
    <div class="filter-group">
      <label>Porte</label>
      <select name="porte">
        <option value="">Todos</option>
        <?php foreach (CNPJ_PORTES as $k => $v): ?>
        <option value="<?= $k ?>" <?= ($f['porte'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label>Matriz / Filial</label>
      <select name="mf">
        <option value="">Ambos</option>
        <option value="1" <?= ($f['mf'] ?? '') === '1' ? 'selected' : '' ?>>Matriz</option>
        <option value="2" <?= ($f['mf'] ?? '') === '2' ? 'selected' : '' ?>>Filial</option>
      </select>
    </div>
    <div class="filter-group">
      <label>Abertura de</label>
      <input type="date" name="abertura_de" value="<?= e($f['abertura_de'] ?? '') ?>">
    </div>
    <div class="filter-group">
      <label>Abertura até</label>
      <input type="date" name="abertura_ate" value="<?= e($f['abertura_ate'] ?? '') ?>">
    </div>
    <div class="filter-group">
      <label style="display:flex;align-items:center;gap:6px">
        <input type="checkbox" name="tem_email" value="1" <?= !empty($f['tem_email']) ? 'checked' : '' ?>>
        Com e-mail
      </label>
      <label style="display:flex;align-items:center;gap:6px;margin-top:6px">
        <input type="checkbox" name="tem_tel" value="1" <?= !empty($f['tem_tel']) ? 'checked' : '' ?>>
        Com telefone
      </label>
      <label style="display:flex;align-items:center;gap:6px;margin-top:6px">
        <input type="checkbox" name="simples" value="1" <?= !empty($f['simples']) ? 'checked' : '' ?>>
        Simples Nacional
      </label>
      <label style="display:flex;align-items:center;gap:6px;margin-top:6px">
        <input type="checkbox" name="mei" value="1" <?= !empty($f['mei']) ? 'checked' : '' ?>>
        MEI
      </label>
    </div>
    <button type="submit" class="btn-primary">Buscar</button>
  </aside>

  <!-- Results -->
  <main class="cnpj-results">
    <?php if (empty($f)): ?>
      <div class="empty-state">
        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <p>Use os filtros ao lado para buscar empresas na base da Receita Federal.</p>
      </div>
    <?php else: ?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <strong><?= number_format($results['total']) ?> empresa(s) encontrada(s)</strong>
        <div style="display:flex;gap:8px">
          <?php if ($results['total'] > 0): ?>
          <button type="button" onclick="openSaveModal()" style="background:#f3f4f6;color:#374151;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:.85rem">
            Salvar lista
          </button>
          <a href="cnpj-export.php?<?= http_build_query($f) ?>" class="export-btn">
            Exportar CSV &nbsp;<small>(<?= number_format(min($q_limit - $q_used, 5000)) ?> disponíveis)</small>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="results-card">
        <?php if (empty($results['rows'])): ?>
          <div class="empty-state">Nenhuma empresa encontrada com esses filtros.</div>
        <?php else: ?>
        <table class="results-table">
          <thead>
            <tr>
              <th>CNPJ</th>
              <th>Razão Social / Fantasia</th>
              <th>CNAE</th>
              <th>UF / Município</th>
              <th>Contato</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results['rows'] as $r): ?>
            <tr>
              <td style="font-family:monospace"><?= cnpj_fmt($r['cnpj']) ?></td>
              <td>
                <strong><?= e($r['razao_social']) ?></strong>
                <?php if ($r['nome_fantasia']): ?><br><small><?= e($r['nome_fantasia']) ?></small><?php endif; ?>
              </td>
              <td><?= e($r['cnae_fiscal_principal']) ?></td>
              <td><?= e($r['uf']) ?> / <?= e($r['municipio']) ?></td>
              <td>
                <?php if ($r['telefone1']): ?>
                  <?= e(($r['ddd1'] ? '(' . $r['ddd1'] . ') ' : '') . $r['telefone1']) ?><br>
                <?php endif; ?>
                <?php if ($r['correio_eletronico']): ?>
                  <small><?= e($r['correio_eletronico']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $sit = $r['situacao_cadastral'];
                  $cls = $sit === '02' ? 'badge-green' : ($sit === '08' ? 'badge-red' : 'badge-gray');
                ?>
                <span class="badge <?= $cls ?>"><?= cnpj_situacao_label($sit) ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($f, ['page' => $page - 1])) ?>">‹ Anterior</a>
          <?php endif; ?>
          <?php
            $start = max(1, $page - 3);
            $end   = min($total_pages, $page + 3);
            for ($i = $start; $i <= $end; $i++):
          ?>
            <?php if ($i === $page): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?<?= http_build_query(array_merge($f, ['page' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a href="?<?= http_build_query(array_merge($f, ['page' => $page + 1])) ?>">Próxima ›</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
</form>

<!-- Save list modal -->
<div class="modal-bg" id="save-modal">
  <div class="modal">
    <h3>Salvar lista de empresas</h3>
    <form method="post" action="cnpj-lists.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_filter">
      <input type="hidden" name="filter_json" value="<?= e(json_encode($f)) ?>">
      <input type="hidden" name="item_count" value="<?= $results['total'] ?>">
      <input type="text" name="name" placeholder="Nome da lista" required>
      <textarea name="description" placeholder="Descrição (opcional)" rows="3"></textarea>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn-primary" style="width:auto">Salvar</button>
      </div>
    </form>
  </div>
</div>

<script>
function openSaveModal() { document.getElementById('save-modal').classList.add('open'); }
function closeModal()     { document.getElementById('save-modal').classList.remove('open'); }

async function loadMunicipios() {
    const uf  = document.getElementById('uf-sel').value;
    const sel = document.getElementById('mun-sel');
    sel.innerHTML = '<option value="">Todos</option>';
    if (!uf) return;
    const data = await fetch('cnpj-api.php?action=municipios&uf=' + uf).then(r => r.json());
    data.forEach(m => {
        const o = document.createElement('option');
        o.value = m.codigo; o.textContent = m.nome;
        sel.appendChild(o);
    });
}

// Reload municipios if UF already selected
if (document.getElementById('uf-sel').value) loadMunicipios();
</script>
</body>
</html>
<?php
});
