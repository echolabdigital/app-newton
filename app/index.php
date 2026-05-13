<?php
require_once __DIR__ . '/../config.php';
$tenant = require_tenant();
require_once __DIR__ . '/_layout.php';

$plan   = $tenant['plan_id'] ? db_one('SELECT * FROM plans WHERE id = ?', [$tenant['plan_id']]) : null;
$brand  = tenant_brand();
$zapi   = tenant_zapi();

$modules = [
    [
        'title'=>'Extrator de Leads',
        'desc'=>'Encontre clientes em potencial pelo Google Maps. Filtre por categoria, cidade e quantidade.',
        'icon'=>'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
    ],
    [
        'title'=>'CRM Kanban',
        'desc'=>'Organize seus leads em colunas (novo, contato, qualificado, fechado). Drag-and-drop entre etapas.',
        'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    ],
    [
        'title'=>'Disparador WhatsApp',
        'desc'=>'Envie mensagens em massa com mídia, variações e delays inteligentes anti-ban.',
        'icon'=>'M13 10V3L4 14h7v7l9-11h-7z',
    ],
    [
        'title'=>'Conversas',
        'desc'=>'Atenda respostas dos disparos com IA assistente, marque como interessado/não interessado.',
        'icon'=>'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
    ],
];

app_layout('Visão Geral', 'overview', function() use ($tenant, $plan, $zapi, $modules, $brand) {
?>
<!-- Banner de boas-vindas -->
<div class="panel" style="background: linear-gradient(135deg, var(--cr) 0%, var(--ink) 100%); color: #fff; border: none;">
  <h2 style="color: #fff; font-size: 1.4rem; margin-bottom: .5rem;">Bem-vindo ao <?= e($brand['name']) ?> 👋</h2>
  <p style="opacity: .9; font-size: .9rem; line-height: 1.5;">
    Sua conta está no ar. Os módulos do painel estão sendo migrados nesta semana — em breve você vai poder extrair leads, organizar no CRM e disparar mensagens diretamente daqui.
  </p>
</div>

<!-- Status real -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div class="stat-val" style="font-size:1.4rem;">
      <?php if ($tenant['status'] === 'active'): ?>
        <span style="color:#15803d;">Ativa</span>
      <?php elseif ($tenant['status'] === 'pending'): ?>
        <span style="color:#a16207;">Pendente</span>
      <?php else: ?>
        <span style="color:#b91c1c;"><?= e($tenant['status']) ?></span>
      <?php endif; ?>
    </div>
    <div class="stat-label">Status da conta</div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>
    </div>
    <div class="stat-val" style="font-size:1.4rem;"><?= e($plan['name'] ?? 'Sem plano') ?></div>
    <div class="stat-label">Plano contratado</div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
    </div>
    <div class="stat-val" style="font-size:1.4rem;">
      <?php if ($zapi && $zapi['status'] === 'connected'): ?>
        <span style="color:#15803d;">Conectado</span>
      <?php elseif ($zapi): ?>
        <span style="color:#a16207;">Aguardando QR</span>
      <?php else: ?>
        <span style="color:var(--ink-3);">Não atribuída</span>
      <?php endif; ?>
    </div>
    <div class="stat-label">WhatsApp <?= ($zapi && !empty($zapi['phone'])) ? '· ' . e($zapi['phone']) : '' ?></div>
  </div>

  <div class="stat-card">
    <div class="stat-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
    </div>
    <div class="stat-val" style="font-size:1.4rem;"><?= number_format((int) $tenant['limit_dispatch_daily'], 0, ',', '.') ?></div>
    <div class="stat-label">Disparos/dia (limite do plano)</div>
  </div>
</div>

<!-- Módulos em breve -->
<h2 style="font-size: 1rem; font-weight: 700; margin: 2rem 0 1rem; color: var(--ink-2);">Módulos do painel</h2>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
  <?php foreach ($modules as $m): ?>
    <div class="panel" style="margin: 0; opacity: .85;">
      <div style="display: flex; align-items: start; gap: 1rem;">
        <div class="stat-icon" style="margin: 0;">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20"><path d="<?= $m['icon'] ?>"/></svg>
        </div>
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;">
            <h3 style="font-size: .95rem; font-weight: 700;"><?= e($m['title']) ?></h3>
            <span style="font-size: .55rem; font-weight: 800; background: var(--ink); color: #fff; padding: 2px 6px; border-radius: 4px; letter-spacing: .04em;">EM BREVE</span>
          </div>
          <p style="font-size: .8rem; color: var(--ink-3); line-height: 1.5;"><?= e($m['desc']) ?></p>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div style="text-align:center;color:var(--ink-3);font-size:.75rem;margin-top:2rem;">
  Newtonia · Plataforma de aquisição de clientes via WhatsApp · echo_lab_digital
</div>
<?php
});
