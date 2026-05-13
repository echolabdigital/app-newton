<?php
require_once __DIR__ . '/../config.php';
$tenant = require_tenant();
require_once __DIR__ . '/../core/cnpj_db.php';
require_once __DIR__ . '/_layout.php';

$f = [
    'q'           => trim($_GET['q']            ?? ''),
    'situacao'    => $_GET['situacao']           ?? '02',
    'uf'          => strtoupper(trim($_GET['uf'] ?? '')),
    'municipio'   => trim($_GET['municipio']    ?? ''),
    'cnae'        => trim($_GET['cnae']         ?? ''),
    'porte'       => $_GET['porte']              ?? '',
    'mf'          => $_GET['mf']                 ?? '',
    'simples'     => !empty($_GET['simples']),
    'mei'         => !empty($_GET['mei']),
    'tem_email'   => !empty($_GET['tem_email']),
    'tem_tel'     => !empty($_GET['tem_tel']),
    'abertura_de' => $_GET['abertura_de']        ?? '',
    'abertura_ate'=> $_GET['abertura_ate']       ?? '',
];

$page     = max(1, (int) ($_GET['page'] ?? 1));
$per      = 50;
$results  = null;
$error    = null;
$searched = array_key_exists('q', $_GET)
         || array_key_exists('uf', $_GET)
         || array_key_exists('cnae', $_GET);

if ($searched) {
    try {
        $results = cnpj_search($f, $page, $per);
    } catch (\Throwable $e) {
        $error = 'Não foi possível conectar na base CNPJ. '
               . 'Configure as constantes CNPJ_DB_* no config.php.';
    }
}

// Quota do tenant
$tid            = tenant_id();
$quota_limit    = (int) ($tenant['limit_cnpj_monthly'] ?? 1000);
$quota_used     = 0;
$quota_remaining = $quota_limit;
try {
    $quota_used      = cnpj_quota_used($tid);
    $quota_remaining = max(0, $quota_limit - $quota_used);
} catch (\Throwable $e) {}
$quota_pct = $quota_limit > 0 ? min(100, round($quota_used / $quota_limit * 100)) : 100;

$municipio_nome = '';
if ($f['municipio'] && $f['uf']) {
    try {
        $m = cnpj_one('SELECT descricao FROM rf_municipios WHERE codigo = ?', [$f['municipio']]);
        $municipio_nome = $m['descricao'] ?? '';
    } catch (\Throwable $e) {}
}

$cnae_nome = '';
if ($f['cnae']) {
    try {
        $code = str_pad(preg_replace('/\D/', '', $f['cnae']), 7, '0', STR_PAD_LEFT);
        $c    = cnpj_one('SELECT descricao FROM rf_cnaes WHERE codigo = ?', [$code]);
        $cnae_nome = $c['descricao'] ?? '';
    } catch (\Throwable $e) {}
}

function build_qs(array $overrides): string {
    global $f, $page;
    $base = [
        'q'           => $f['q'],
        'situacao'    => $f['situacao'],
        'uf'          => $f['uf'],
        'municipio'   => $f['municipio'],
        'cnae'        => $f['cnae'],
        'porte'       => $f['porte'],
        'mf'          => $f['mf'],
        'simples'     => $f['simples']   ? '1' : '',
        'mei'         => $f['mei']       ? '1' : '',
        'tem_email'   => $f['tem_email'] ? '1' : '',
        'tem_tel'     => $f['tem_tel']   ? '1' : '',
        'abertura_de' => $f['abertura_de'],
        'abertura_ate'=> $f['abertura_ate'],
        'page'        => (string) $page,
    ];
    $merged = array_filter(array_merge($base, $overrides), fn($v) => $v !== '' && $v !== null);
    return 'cnpj.php?' . http_build_query($merged);
}

app_layout('Newton CNPJ', 'cnpj', function() use ($f, $results, $error, $searched, $page, $per, $municipio_nome, $cnae_nome, $quota_limit, $quota_used, $quota_remaining, $quota_pct) {
?>
<style>
.cnpj-wrap { display: grid; grid-template-columns: 290px 1fr; gap: 1.5rem; align-items: start; }
.filter-box { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 1.25rem; position: sticky; top: 0; }
.filter-box h3 { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--ink-3); margin-bottom: 1rem; }
.fg { margin-bottom: .85rem; }
.fg label { display: block; font-size: .72rem; font-weight: 700; color: var(--ink-2); margin-bottom: .3rem; }
.fg input, .fg select {
    width: 100%; padding: .5rem .7rem;
    border: 1px solid var(--border); border-radius: 8px;
    font-family: inherit; font-size: .8rem; color: var(--ink);
    background: var(--fog); outline: none; transition: border-color .15s;
}
.fg input:focus, .fg select:focus { border-color: var(--cr); background: var(--white); }
.fg .hint { font-size: .68rem; color: var(--cr); margin-top: .25rem; font-weight: 600; }
.chk { display: flex; align-items: center; gap: .45rem; font-size: .78rem; font-weight: 600; color: var(--ink-2); cursor: pointer; margin-bottom: .45rem; }
.chk input { width: 15px; height: 15px; accent-color: var(--cr); flex-shrink: 0; }
.sep { height: 1px; background: var(--border); margin: .9rem 0; }
.btn-search { width: 100%; padding: .65rem; background: var(--cr); color: #fff; border: none; border-radius: 10px; font-family: inherit; font-size: .85rem; font-weight: 700; cursor: pointer; transition: opacity .15s; }
.btn-search:hover { opacity: .88; }
.results-box { min-width: 0; }
.top-bar { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; flex-wrap: wrap; }
.top-bar .count { font-size: .82rem; color: var(--ink-3); font-weight: 600; flex: 1; }
.quota-bar-wrap { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
.quota-bar-label { display: flex; justify-content: space-between; font-size: .75rem; font-weight: 700; margin-bottom: .5rem; }
.quota-bar-track { height: 8px; background: var(--fog); border-radius: 99px; overflow: hidden; }
.quota-bar-fill { height: 100%; border-radius: 99px; transition: width .4s; }
.tbl { width: 100%; border-collapse: collapse; font-size: .77rem; }
.tbl th { text-align: left; padding: .6rem .75rem; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-3); border-bottom: 2px solid var(--border); white-space: nowrap; }
.tbl td { padding: .6rem .75rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.tbl tr:hover td { background: var(--fog); }
.cnpj-num { font-family: monospace; font-size: .78rem; color: var(--ink-2); white-space: nowrap; }
.tc { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: .65rem; font-weight: 700; }
.b-ativa   { background: #dcfce7; color: #15803d; }
.b-baixada { background: #fee2e2; color: #b91c1c; }
.b-outros  { background: #fef9c3; color: #854d0e; }
.pager { display: flex; gap: .4rem; justify-content: center; margin-top: 1.25rem; flex-wrap: wrap; }
.pbtn { padding: .38rem .7rem; border: 1px solid var(--border); border-radius: 7px; background: var(--white); color: var(--ink-2); font-size: .75rem; font-weight: 600; text-decoration: none; }
.pbtn:hover { background: var(--fog); }
.pbtn.cur { background: var(--cr); color: #fff; border-color: var(--cr); }
.empty-box { text-align: center; padding: 3rem; color: var(--ink-3); }
.modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1000; align-items: center; justify-content: center; }
.modal-box { background: var(--white); border-radius: 16px; padding: 2rem; width: 420px; max-width: 92vw; }
</style>

<?php
// Barra de quota mensal
$bar_color = $quota_pct >= 90 ? '#dc2626' : ($quota_pct >= 70 ? '#d97706' : 'var(--cr)');
?>
<div class="quota-bar-wrap">
  <div class="quota-bar-label">
    <span>Leads baixados este mês</span>
    <span style="color:<?= $bar_color ?>;">
      <?= number_format($quota_used, 0, ',', '.') ?> de <?= number_format($quota_limit, 0, ',', '.') ?>
      &nbsp;·&nbsp;
      <?php if ($quota_remaining > 0): ?>
        <strong><?= number_format($quota_remaining, 0, ',', '.') ?></strong> disponíveis
      <?php else: ?>
        <strong style="color:#dc2626;">Limite atingido</strong>
      <?php endif; ?>
    </span>
  </div>
  <div class="quota-bar-track">
    <div class="quota-bar-fill" style="width:<?= $quota_pct ?>%;background:<?= $bar_color ?>;"></div>
  </div>
</div>

<form method="get" action="cnpj.php" id="frm">
<div class="cnpj-wrap">

  <!-- FILTROS -->
  <aside class="filter-box">
    <h3>Filtros de Prospecção</h3>

    <div class="fg">
      <label>CNPJ ou Razão Social</label>
      <input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="12.345.678/0001-00 ou nome" autocomplete="off">
    </div>

    <div class="sep"></div>

    <div class="fg">
      <label>Estado (UF)</label>
      <select name="uf" id="sel-uf" onchange="loadMunicipios(this.value)">
        <option value="">Todos os estados</option>
        <?php foreach (CNPJ_UFS as $uf): ?>
          <option value="<?= $uf ?>" <?= $f['uf']===$uf?'selected':'' ?>><?= $uf ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Município</label>
      <select name="municipio" id="sel-mun">
        <option value="">Todos</option>
        <?php if ($f['municipio'] && $municipio_nome): ?>
          <option value="<?= e($f['municipio']) ?>" selected><?= e($municipio_nome) ?></option>
        <?php endif; ?>
      </select>
    </div>

    <div class="fg">
      <label>CNAE (código ou início)</label>
      <input type="text" name="cnae" value="<?= e($f['cnae']) ?>" placeholder="4711, 47, 8599…">
      <?php if ($cnae_nome): ?>
        <div class="hint"><?= e(mb_substr($cnae_nome, 0, 50)) ?></div>
      <?php endif; ?>
    </div>

    <div class="fg">
      <label>Porte</label>
      <select name="porte">
        <?php foreach (CNPJ_PORTES as $k => $v): ?>
          <option value="<?= $k ?>" <?= $f['porte']===$k?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Situação cadastral</label>
      <select name="situacao">
        <?php foreach (CNPJ_SITUACOES as $k => $v): ?>
          <option value="<?= $k ?>" <?= $f['situacao']===$k?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label>Tipo</label>
      <select name="mf">
        <option value="">Matriz + Filial</option>
        <option value="1" <?= $f['mf']==='1'?'selected':'' ?>>Apenas Matriz</option>
        <option value="2" <?= $f['mf']==='2'?'selected':'' ?>>Apenas Filial</option>
      </select>
    </div>

    <div class="fg">
      <label>Abertura — de</label>
      <input type="date" name="abertura_de"  value="<?= e($f['abertura_de']) ?>">
    </div>
    <div class="fg">
      <label>Abertura — até</label>
      <input type="date" name="abertura_ate" value="<?= e($f['abertura_ate']) ?>">
    </div>

    <div class="sep"></div>

    <label class="chk"><input type="checkbox" name="tem_email" value="1" <?= $f['tem_email']?'checked':'' ?>><span>Tem e-mail</span></label>
    <label class="chk"><input type="checkbox" name="tem_tel"   value="1" <?= $f['tem_tel']?'checked':'' ?>><span>Tem telefone</span></label>
    <label class="chk"><input type="checkbox" name="simples"   value="1" <?= $f['simples']?'checked':'' ?>><span>Simples Nacional</span></label>
    <label class="chk"><input type="checkbox" name="mei"       value="1" <?= $f['mei']?'checked':'' ?>><span>MEI</span></label>

    <div class="sep"></div>
    <button type="submit" class="btn-search">Buscar Empresas</button>
  </aside>

  <!-- RESULTADOS -->
  <div class="results-box">
    <?php if ($error): ?>
      <div class="panel" style="border-color:#fca5a5;background:#fef2f2;">
        <strong style="color:#b91c1c;">Erro de conexão</strong>
        <p style="font-size:.8rem;color:#991b1b;margin-top:.4rem;"><?= e($error) ?></p>
      </div>

    <?php elseif (!$searched): ?>
      <div class="panel empty-box">
        <div style="font-size:3rem;margin-bottom:1rem;">🏢</div>
        <h2 style="font-size:1.1rem;font-weight:800;margin-bottom:.5rem;">Newton CNPJ</h2>
        <p style="color:var(--ink-3);font-size:.85rem;line-height:1.65;">
          Prospecte qualquer empresa da base completa da Receita Federal.<br>
          Use os filtros ao lado e clique em <strong>Buscar Empresas</strong>.
        </p>
        <div style="margin-top:1.5rem;display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;font-size:.78rem;color:var(--ink-3);">
          <span>📍 Filtro por cidade/estado</span>
          <span>🏭 Filtro por CNAE</span>
          <span>📧 Filtro por e-mail/telefone</span>
          <span>⬇ Exportação CSV</span>
        </div>
      </div>

    <?php else:
      $total = $results['total'] ?? 0;
      $rows  = $results['rows']  ?? [];

      $export_qs = http_build_query(array_filter([
        'q'           => $f['q'],
        'situacao'    => $f['situacao'] !== 'all' ? $f['situacao'] : '',
        'uf'          => $f['uf'],
        'municipio'   => $f['municipio'],
        'cnae'        => $f['cnae'],
        'porte'       => $f['porte'],
        'mf'          => $f['mf'],
        'simples'     => $f['simples']   ? '1' : '',
        'mei'         => $f['mei']       ? '1' : '',
        'tem_email'   => $f['tem_email'] ? '1' : '',
        'tem_tel'     => $f['tem_tel']   ? '1' : '',
        'abertura_de' => $f['abertura_de'],
        'abertura_ate'=> $f['abertura_ate'],
      ])); ?>

      <div class="top-bar">
        <span class="count">
          <?php if ($total === 0): ?>
            Nenhuma empresa encontrada
          <?php else: ?>
            <strong><?= number_format($total, 0, ',', '.') ?></strong> empresa<?= $total !== 1 ? 's' : '' ?> — página <?= $page ?>
          <?php endif; ?>
        </span>
        <?php if ($rows && $quota_remaining > 0): ?>
          <a href="cnpj-export.php?<?= $export_qs ?>" class="btn-action" target="_blank" style="font-size:.78rem;padding:.5rem 1rem;">⬇ CSV (<?= number_format(min(5000, $quota_remaining), 0, ',', '.') ?> leads)</a>
          <button type="button" class="btn-action secondary" onclick="showModal()" style="font-size:.78rem;padding:.5rem 1rem;">📋 Salvar Lista</button>
        <?php elseif ($rows && $quota_remaining <= 0): ?>
          <span style="font-size:.78rem;color:#b91c1c;font-weight:700;">⚠ Limite mensal atingido</span>
        <?php endif; ?>
      </div>

      <?php if ($rows): ?>
        <div class="panel" style="padding:0;overflow:hidden;">
          <table class="tbl">
            <thead>
              <tr>
                <th>CNPJ</th>
                <th>Razão Social</th>
                <th>Fantasia</th>
                <th>Setor (CNAE)</th>
                <th>UF</th>
                <th>Município</th>
                <th>Telefone</th>
                <th>E-mail</th>
                <th>Sit.</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="cnpj-num"><?= cnpj_fmt($r['cnpj']) ?></td>
                  <td class="tc" title="<?= e($r['razao_social']) ?>"><?= e($r['razao_social']) ?></td>
                  <td class="tc" title="<?= e($r['nome_fantasia']) ?>"><?= e($r['nome_fantasia'] ?: '—') ?></td>
                  <td class="tc" title="<?= e($r['cnae_desc']) ?>" style="max-width:140px;"><?= e($r['cnae_desc'] ?: $r['cnae_principal']) ?></td>
                  <td style="font-weight:700;"><?= e($r['uf']) ?></td>
                  <td class="tc"><?= e($r['municipio']) ?></td>
                  <td style="white-space:nowrap;font-size:.75rem;"><?= e($r['telefone'] ?: '—') ?></td>
                  <td class="tc" style="max-width:160px;"><?= $r['email'] ? '<a href="mailto:'.e($r['email']).'" style="color:var(--cr);font-size:.75rem;">'.e($r['email']).'</a>' : '—' ?></td>
                  <td>
                    <?php $s = $r['situacao_cadastral']; ?>
                    <span class="badge <?= $s==='02'?'b-ativa':($s==='08'?'b-baixada':'b-outros') ?>">
                      <?= cnpj_situacao_label($s) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($total > $per):
          $max_pages = min((int) ceil($total / $per), 200);
          $start     = max(1, $page - 4);
          $end       = min($max_pages, $page + 4);
        ?>
          <div class="pager">
            <?php if ($page > 1): ?>
              <a href="<?= build_qs(['page' => $page - 1]) ?>" class="pbtn">‹</a>
            <?php endif; ?>
            <?php for ($p = $start; $p <= $end; $p++): ?>
              <a href="<?= build_qs(['page' => $p]) ?>" class="pbtn <?= $p===$page?'cur':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $max_pages): ?>
              <a href="<?= build_qs(['page' => $page + 1]) ?>" class="pbtn">›</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="panel empty-box">
          <div style="font-size:2rem;margin-bottom:.75rem;">🔍</div>
          <p style="font-weight:700;">Nenhuma empresa encontrada</p>
          <p style="font-size:.82rem;margin-top:.4rem;color:var(--ink-3);">Tente ampliar os filtros ou buscar por outro termo.</p>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</form>

<!-- Modal salvar lista -->
<div class="modal-bg" id="modal">
  <div class="modal-box">
    <h3 style="margin-bottom:1rem;">Salvar Pesquisa como Lista</h3>
    <form method="post" action="cnpj-lists.php">
      <input type="hidden" name="action" value="save_filter">
      <input type="hidden" name="filter_json" id="fjson">
      <div style="margin-bottom:.85rem;">
        <label style="display:block;font-size:.78rem;font-weight:700;margin-bottom:.35rem;">Nome da lista</label>
        <input type="text" name="name" required placeholder="Ex: Restaurantes SP 2026"
          style="width:100%;padding:.6rem .8rem;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:.85rem;">
      </div>
      <div style="margin-bottom:1.25rem;">
        <label style="display:block;font-size:.78rem;font-weight:700;margin-bottom:.35rem;">Descrição (opcional)</label>
        <textarea name="description" rows="2" style="width:100%;padding:.6rem .8rem;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:.85rem;resize:vertical;"></textarea>
      </div>
      <div style="display:flex;gap:.75rem;">
        <button type="button" class="btn-action secondary" onclick="closeModal()" style="flex:1;">Cancelar</button>
        <button type="submit" class="btn-action" style="flex:1;">Salvar</button>
      </div>
    </form>
  </div>
</div>

<script>
function loadMunicipios(uf) {
  const sel = document.getElementById('sel-mun');
  sel.innerHTML = '<option value="">Carregando...</option>';
  if (!uf) { sel.innerHTML = '<option value="">Todos</option>'; return; }
  fetch('cnpj-api.php?action=municipios&uf=' + uf)
    .then(r => r.json())
    .then(data => {
      sel.innerHTML = '<option value="">Todos</option>';
      data.forEach(m => {
        const o = document.createElement('option');
        o.value = m.codigo; o.textContent = m.nome; sel.appendChild(o);
      });
    })
    .catch(() => { sel.innerHTML = '<option value="">Erro ao carregar</option>'; });
}

(function() {
  const uf  = document.getElementById('sel-uf').value;
  const mun = '<?= e($f['municipio']) ?>';
  if (!uf) return;
  fetch('cnpj-api.php?action=municipios&uf=' + uf)
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('sel-mun');
      sel.innerHTML = '<option value="">Todos</option>';
      data.forEach(m => {
        const o = document.createElement('option');
        o.value = m.codigo; o.textContent = m.nome;
        if (m.codigo === mun) o.selected = true;
        sel.appendChild(o);
      });
    });
})();

function showModal() {
  const fd = new FormData(document.getElementById('frm'));
  const obj = {};
  for (const [k, v] of fd.entries()) obj[k] = v;
  document.getElementById('fjson').value = JSON.stringify(obj);
  document.getElementById('modal').style.display = 'flex';
}
function closeModal() {
  document.getElementById('modal').style.display = 'none';
}
document.getElementById('modal').addEventListener('click', e => {
  if (e.target === document.getElementById('modal')) closeModal();
});
</script>
<?php
});
