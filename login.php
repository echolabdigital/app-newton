<?php
require_once __DIR__ . '/config.php';

// Já logado → redireciona
if (auth_user_id()) {
    header('Location: /app/');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? '/app/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = auth_login(
        $_POST['email']    ?? '',
        $_POST['password'] ?? ''
    );
    if ($result['ok']) {
        header('Location: ' . $result['redirect']);
        exit;
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — <?= APP_NAME ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
         background: #f5f5f5; display: flex; align-items: center;
         justify-content: center; min-height: 100vh; }
  .card { background: #fff; border-radius: 12px; padding: 40px;
          width: 100%; max-width: 380px; box-shadow: 0 2px 16px rgba(0,0,0,.08); }
  h1 { font-size: 1.4rem; margin-bottom: 24px; color: #111; }
  label { display: block; font-size: .85rem; font-weight: 600;
          margin-bottom: 6px; color: #444; }
  input { width: 100%; padding: 10px 14px; border: 1px solid #ddd;
          border-radius: 8px; font-size: .95rem; margin-bottom: 16px; outline: none; }
  input:focus { border-color: #6366f1; }
  button { width: 100%; padding: 12px; background: #6366f1; color: #fff;
           border: none; border-radius: 8px; font-size: 1rem;
           font-weight: 600; cursor: pointer; }
  button:hover { background: #4f46e5; }
  .error { background: #fee2e2; color: #dc2626; padding: 10px 14px;
           border-radius: 8px; font-size: .85rem; margin-bottom: 16px; }
</style>
</head>
<body>
<div class="card">
  <h1><?= APP_NAME ?></h1>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="_redirect" value="<?= htmlspecialchars($redirect) ?>">
    <label>E-mail</label>
    <input type="email" name="email" required autofocus>
    <label>Senha</label>
    <input type="password" name="password" required>
    <button type="submit">Entrar</button>
  </form>
</div>
</body>
</html>
