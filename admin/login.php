<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

start_session();
admin_headers();

if (!empty($_SESSION['auth']) && auth_load() !== null) {
    redirect('index.php');
}

$error = '';
$user = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string)($_POST['usuario'] ?? ''));
    $pass = (string)($_POST['senha'] ?? '');
    $ip = 'ip:' . client_ip();

    if (!csrf_valid()) {
        $error = 'A página ficou aberta por muito tempo. Tente de novo.';
    } else {
        $wait = max(rl_wait($ip, MAX_FAILS_IP), rl_wait('global', MAX_FAILS_ALL));

        if ($wait > 0) {
            $error = 'Muitas tentativas. Aguarde ' . (int)ceil($wait / 60) . ' min e tente de novo.';
        } else {
            $auth = auth_load();
            $userOk = $auth !== null && hash_equals(strtolower((string)$auth['usuario']), strtolower($user));
            // Sempre confere a senha (mesmo com usuário errado) para a resposta demorar igual
            $passOk = password_verify($pass, $auth !== null ? (string)$auth['hash'] : DUMMY_HASH);

            if ($auth !== null && $userOk && $passOk) {
                rl_clear($ip);
                session_regenerate_id(true);
                $_SESSION = [
                    'auth' => true,
                    'login_at' => time(),
                    'last' => time(),
                    'versao' => (int)($auth['versao'] ?? 1),
                    'csrf' => bin2hex(random_bytes(32)),
                ];
                redirect(!empty($auth['trocar_senha']) ? 'senha.php' : 'index.php');
            }

            rl_hit($ip);
            rl_hit('global');
            usleep(600000);
            $error = 'Usuário ou senha incorretos.';
        }
    }
}

$notice = null;
if (isset($_GET['expirou'])) {
    $notice = ['type' => 'warn', 'message' => 'Sua sessão expirou. Entre novamente.'];
} elseif (isset($_GET['saiu'])) {
    $notice = ['type' => 'success', 'message' => 'Você saiu do painel.'];
}

admin_head('Entrar', '', false);
?>
    <section class="auth-card" aria-labelledby="titulo-login">
      <p class="eyebrow">Área restrita</p>
      <h1 id="titulo-login">Entrar no painel</h1>
      <p class="lead">Acesse para alterar os preços dos quartos.</p>

<?php if (!is_https() && !is_local()): ?>
      <div class="alert alert-warn" role="alert">
        Esta conexão não é segura (HTTP). Acesse o painel por <strong>https://</strong> antes de digitar a senha.
      </div>
<?php endif; ?>
<?php admin_alert($notice); ?>
<?php if ($error !== ''): ?>
      <div class="alert alert-error" role="alert"><?= h($error) ?></div>
<?php endif; ?>

      <form method="post" action="login.php" autocomplete="on">
        <?= csrf_field() ?>

        <div class="field">
          <label for="usuario">Usuário</label>
          <input class="input" type="text" id="usuario" name="usuario" value="<?= h($user) ?>"
            autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
        </div>

<?php password_field('senha', 'Senha', 'current-password'); ?>

        <button class="btn-primary btn-block" type="submit">Entrar</button>
      </form>

      <p class="auth-back"><a href="../index.html"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar para o site</a></p>
    </section>
<?php admin_foot(); ?>
