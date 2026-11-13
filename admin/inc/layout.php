<?php
declare(strict_types=1);

/* Cabeçalho e rodapé das telas do painel. */

if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'layout.php') {
    http_response_code(404);
    exit;
}

const FONT_AWESOME = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';

function admin_head(string $title, string $active = '', bool $withMenu = true, ?bool $withLogout = null): void
{
    $withLogout = $withLogout ?? $withMenu;
    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="theme-color" content="#111111">
  <title><?= h($title) ?> | Painel da Pousada Azimute</title>

  <link rel="icon" href="../favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32.png">
  <script src="../theme.js"></script>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="<?= FONT_AWESOME ?>">
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin">
  <a class="skip-link" href="#main">Pular para o conteúdo</a>

  <header class="admin-bar">
    <a class="admin-logo" href="index.php" aria-label="Painel da Pousada Azimute">
      <img class="logo-img logo-dark" src="../logo.png" alt="Pousada Azimute" width="960" height="480">
      <img class="logo-img logo-light" src="../logo-claro.png" alt="Pousada Azimute" width="960" height="480">
    </a>

<?php if ($withMenu): ?>
    <nav class="admin-nav" aria-label="Menu do painel">
      <a href="index.php"<?= $active === 'precos' ? ' class="active" aria-current="page"' : '' ?>>Preços</a>
      <a href="senha.php"<?= $active === 'senha' ? ' class="active" aria-current="page"' : '' ?>>Senha</a>
      <a href="../index.html" target="_blank" rel="noopener noreferrer">Ver o site <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
    </nav>
<?php endif; ?>

    <div class="admin-actions">
      <button class="theme-toggle" type="button" data-theme-toggle
        aria-label="Ativar tema claro" title="Ativar tema claro">
        <i class="fa-solid fa-sun" aria-hidden="true"></i>
      </button>
<?php if ($withLogout): ?>
      <form method="post" action="sair.php">
        <?= csrf_field() ?>
        <button class="btn-ghost" type="submit"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sair</button>
      </form>
<?php endif; ?>
    </div>
  </header>

  <main id="main" class="admin-main">
<?php
}

function admin_foot(): void
{
    ?>
  </main>

  <footer class="admin-foot">
    <p>Área restrita da Pousada Azimute. Nunca compartilhe sua senha.</p>
  </footer>

  <script src="admin.js" defer></script>
</body>
</html>
<?php
}

function admin_alert(?array $flash): void
{
    if (!$flash) {
        return;
    }
    $type = $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warn' ? 'warn' : 'error');
    $role = $type === 'error' ? 'alert' : 'status';
    echo '<div class="alert alert-' . $type . '" role="' . $role . '">' . h($flash['message']) . '</div>' . "\n";
}

function password_field(string $id, string $label, string $autocomplete, string $hint = ''): void
{
    ?>
    <div class="field">
      <label for="<?= h($id) ?>"><?= h($label) ?></label>
      <div class="input-wrap">
        <input class="input" type="password" id="<?= h($id) ?>" name="<?= h($id) ?>"
          autocomplete="<?= h($autocomplete) ?>" required<?= $hint !== '' ? ' aria-describedby="' . h($id) . '-hint"' : '' ?>>
        <button class="pw-toggle" type="button" data-toggle-password="<?= h($id) ?>"
          aria-label="Mostrar senha" aria-pressed="false">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>
        </button>
      </div>
<?php if ($hint !== ''): ?>
      <p class="hint" id="<?= h($id) ?>-hint"><?= h($hint) ?></p>
<?php endif; ?>
    </div>
<?php
}
