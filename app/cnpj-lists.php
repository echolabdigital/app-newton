<?php
require_once __DIR__ . '/../config.php';
$tenant = require_tenant();
require_once __DIR__ . '/../core/cnpj_db.php';
require_once __DIR__ . '/_layout.php';

$tid = tenant_id();

// ── Ações POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_filter') {
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $fjson  = $_POST['filter_json'] ?? '{}';
        if ($name !== '') {
            db_insert('cnpj_lists', [
                'tenant_id'   => $tid,
                'name'        => mb_substr($name, 0, 200),
                'description' => $desc ?: null,
                'filter_json' => $fjson,
            ]);
            flash('Lista salva com sucesso!', 'success');
        }
        header('Location: cnpj-lists.php');
        exit;
    }

    if ($action === 'delete') {
        $lid = (int) ($_POST['list_id'] ?? 0);
        if ($lid) {
            db_delete('cnpj_lists', 'id = :id AND tenant_id = :tid', ['id' => $lid, 'tid' => $tid]);
            flash('Lista removida.', 'info');
        }
        header('Location: cnpj-lists.php');
        exit;
    }
}

// ── Exportar itens de uma lista ────────────────────────────
if (isset($_GET['export'])) {
    $lid  = (int) $_GET['export'];
    $list = db_one('SELECT * FROM cnpj_lists WHERE id = ? AND tenant_id = ?', [$lid, $tid]);
    if (!$list) { http_response_code(404); exit('Lista não encontrada.'); }

    $items = db_all('SELECT * FROM cnpj_list_items WHERE list_id = ? ORDER BY added_at', [$lid]);

    $fname = 'lista-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($list['name'])) . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['CNPJ','Razão Social','Fantasia','UF','Município','CNAE','Setor','Telefone','E-mail'], ';');
    foreach ($items as $i) {
        fputcsv($out, [
            cnpj_fmt($i['cnpj']),
            $i['razao_social'], $i['nome_fantasia'],
            $i['uf'], $i['municipio'],
            $i['cnae'], $i['cnae_desc'],
            $i['telefone'], $i['email'],
        ], ';');
    }
    fclose($out);
    exit;
}

// ── Visualizar itens de uma lista ──────────────────────────
$view_list  = null;
$view_items = [];
if (isset($_GET['view'])) {
    $lid       = (int) $_GET['view'];
    $view_list = db_one('SELECT * FROM cnpj_lists WHERE id = ? AND tenant_id = ?', [$lid, $tid]);
    if ($view_list) {
        $view_items = db_all(
            'SELECT * FROM cnpj_list_items WHERE list_id = ? ORDER BY added_at DESC',
            [$lid]
        );
    }
}

// ── Listagem principal ─────────────────────────────────────
$lists = db_all(
    'SELECT * FROM cnpj_lists WHERE tenant_id = ? ORDER BY created_at DESC',
    [$tid]
);

app_layout('Listas CNPJ', 'cnpj', function() use ($lists, $view_list, $view_items, $tid) {
?>
<style>
.list-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.25rem; }
.list-card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 1.25rem; }
.list-card h3 { font-size: .9rem; font-weight: 700; margin-bottom: .25rem; }
.list-meta { font-size: .72rem; color: var(--ink-3); margin-bottom: 1rem; }
.list-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.tbl { width: 100%; border-collapse: collapse; font-size: .77rem; }
.tbl th { text-align: left; padding: .55rem .7rem; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-3); border-bottom: 2px solid var(--border); white-space: nowrap; }
.tbl td { padding: .55rem .7rem; border-bottom: 1px solid var(--border); }
.tc { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cnpj-n { font-family: monospace; font-size: .77rem; }
</style>

<?php if ($view_list): ?>
  <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
    <a href="cnpj-lists.php" class="btn-action secondary" style="font-size:.78rem;padding:.45rem .9rem;">← Voltar</a>
    <div>
      <h2 style="font-size:1.1rem;font-weight:800;"><?= e($view_list['name']) ?></h2>
      <?php if ($view_list['description']): ?>
        <p style="font-size:.8rem;color:var(--ink-3);margin-top:.15rem;"><?= e($view_list['description']) ?></p>
      <?php endif; ?>
    </div>
    <div style="margin-left:auto;display:flex;gap:.5rem;">
      <a href="cnpj-lists.php?export=<?= $view_list['id'] ?>" class="btn-action" style="font-size:.78rem;padding:.45rem .9rem;">⬇ CSV</a>
    </div>
  </div>

  <?php if ($view_items): ?>
    <div class="panel" style="padding:0;overflow:hidden;">
      <table class="tbl">
        <thead>
          <tr>
            <th>CNPJ</th><th>Razão Social</th><th>UF</th>
            <th>Município</th><th>Setor</th><th>Telefone</th><th>E-mail</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($view_items as $i): ?>
            <tr>
              <td class="cnpj-n"><?= cnpj_fmt($i['cnpj']) ?></td>
              <td class="tc" title="<?= e($i['razao_social']) ?>"><?= e($i['razao_social']) ?></td>
              <td style="font-weight:700;"><?= e($i['uf']) ?></td>
              <td class="tc"><?= e($i['municipio']) ?></td>
              <td class="tc" title="<?= e($i['cnae_desc']) ?>"><?= e($i['cnae_desc'] ?: $i['cnae']) ?></td>
              <td><?= e($i['telefone'] ?: '—') ?></td>
              <td class="tc"><?= $i['email'] ? '<a href="mailto:'.e($i['email']).'" style="color:var(--cr);">' . e($i['email']) . '</a>' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="panel" style="text-align:center;padding:2rem;color:var(--ink-3);">
      Esta lista ainda não tem itens. Adicione empresas a partir da busca.
    </div>
  <?php endif; ?>

<?php else: ?>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
    <div>
      <h2 style="font-size:1.1rem;font-weight:800;">Listas de Prospecção</h2>
      <p style="font-size:.8rem;color:var(--ink-3);margin-top:.2rem;">Pesquisas salvas da base CNPJ para uso nos disparos.</p>
    </div>
    <a href="cnpj.php" class="btn-action" style="font-size:.8rem;">+ Nova Busca</a>
  </div>

  <?php if ($lists): ?>
    <div class="list-grid">
      <?php foreach ($lists as $l):
        $filter = $l['filter_json'] ? json_decode($l['filter_json'], true) : [];
        $tags   = array_filter([
            !empty($filter['uf'])   ? $filter['uf']          : null,
            !empty($filter['cnae']) ? 'CNAE: '.$filter['cnae'] : null,
            !empty($filter['porte']) && isset(CNPJ_PORTES[$filter['porte']]) ? CNPJ_PORTES[$filter['porte']] : null,
        ]);
      ?>
        <div class="list-card">
          <h3><?= e($l['name']) ?></h3>
          <div class="list-meta">
            <?php if ($l['description']): ?><p style="margin-bottom:.3rem;"><?= e(mb_substr($l['description'], 0, 80)) ?></p><?php endif; ?>
            <?php foreach ($tags as $t): ?>
              <span style="display:inline-block;background:var(--fog);border:1px solid var(--border);border-radius:5px;padding:1px 7px;font-size:.65rem;font-weight:700;margin:.1rem;"><?= e($t) ?></span>
            <?php endforeach; ?>
            <br style="margin:.4rem 0;">
            Criada em <?= date('d/m/Y', strtotime($l['created_at'])) ?>
          </div>
          <div class="list-actions">
            <a href="cnpj-lists.php?view=<?= $l['id'] ?>" class="btn-action" style="font-size:.75rem;padding:.4rem .85rem;">Ver itens</a>
            <a href="cnpj-lists.php?export=<?= $l['id'] ?>" class="btn-action secondary" style="font-size:.75rem;padding:.4rem .85rem;">⬇ CSV</a>
            <?php
              $fq = http_build_query(array_filter((array) ($filter ?? [])));
              if ($fq):
            ?>
              <a href="cnpj.php?<?= $fq ?>" class="btn-action secondary" style="font-size:.75rem;padding:.4rem .85rem;">Reabrir busca</a>
            <?php endif; ?>
            <form method="post" style="margin:0;" onsubmit="return confirm('Excluir esta lista?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="list_id" value="<?= $l['id'] ?>">
              <button type="submit" class="btn-action secondary" style="font-size:.75rem;padding:.4rem .85rem;color:#b91c1c;">Excluir</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="panel" style="text-align:center;padding:3rem;color:var(--ink-3);">
      <div style="font-size:2.5rem;margin-bottom:1rem;">📋</div>
      <p style="font-weight:700;">Nenhuma lista salva ainda</p>
      <p style="font-size:.82rem;margin-top:.4rem;">Faça uma busca e clique em <strong>Salvar Lista</strong> para guardar seus filtros.</p>
      <a href="cnpj.php" class="btn-action" style="margin-top:1.25rem;display:inline-flex;">Ir para Newton CNPJ</a>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php
});
