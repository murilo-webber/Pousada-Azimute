<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$auth = require_login(true);
$forced = !empty($auth['trocar_senha']);
$flash = take_flash();
$error = '';

if ($forced && $flash === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $flash = ['type' => 'warn', 'message' => 'Por segurança, crie uma senha nova antes de continuar.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = 'ip:' . client_ip();
    $old = (string)($_POST['atual'] ?? '');
    $new = (string)($_POST['nova'] ?? '');
    $again = (string)($_POST['confirmar'] ?? '');

    if (!csrf_valid()) {
        $error = 'A página ficou aberta por muito tempo. Tente de novo.';
    } elseif (rl_wait($ip, MAX_FAILS_IP) > 0) {
        $error = 'Muitas tentativas. Aguarde alguns minutos e tente de novo.';
    } elseif (!password_verify($old, (string)$auth['hash'])) {
        rl_hit($ip);
        rl_hit('global');
        usleep(600000);
        $error = 'A senha atual não confere.';
    } elseif (strlen($new) < MIN_PASSWORD) {
        $error = 'A senha nova precisa ter pelo menos ' . MIN_PASSWORD . ' caracteres.';
    } elseif (strlen($new) > 72) {
        $error = 'A senha nova pode ter no máximo 72 caracteres.';
    } elseif ($new === $old) {
        $error = 'A senha nova precisa ser diferente da atual.';
    } elseif (strtolower($new) === strtolower((string)$auth['usuario'])) {
        $error = 'A senha não pode ser igual ao nome de usuário.';
    } elseif (!hash_equals($new, $again)) {
        $error = 'A confirmação não é igual à senha nova.';
    } else {
        $auth['hash'] = password_hash($new, PASSWORD_DEFAULT);
        $auth['trocar_senha'] = false;
        $auth['versao'] = (int)($auth['versao'] ?? 1) + 1;
        $auth['atualizado'] = date('c');

        if (auth_save($auth)) {
            rl_clear($ip);
            session_regenerate_id(true);
            $_SESSION['versao'] = $auth['versao'];   // esta sessão continua; as outras caem
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            flash('success', 'Senha alterada com sucesso.');
            redirect('index.php');
        }
        $error = 'Não foi possível salvar a senha. A pasta "admin/data" precisa de permissão de escrita.';
    }
}

admin_head('Trocar senha', 'senha', !$forced, true);
?>
    <section class="auth-card" aria-labelledby="titulo-senha">
      <p class="eyebrow">Segurança</p>
      <h1 id="titulo-senha">Trocar senha</h1>
      <p class="lead">Use pelo menos <?= MIN_PASSWORD ?> caracteres. Uma frase longa é mais segura e mais fácil de lembrar.</p>

<?php admin_alert($flash); ?>
<?php if ($error !== ''): ?>
      <div class="alert alert-error" role="alert"><?= h($error) ?></div>
<?php endif; ?>

      <form method="post" action="senha.php">
        <?= csrf_field() ?>
        <input type="text" name="usuario" value="<?= h($auth['usuario']) ?>" autocomplete="username" hidden>

<?php
password_field('atual', 'Senha atual', 'current-password');
password_field('nova', 'Senha nova', 'new-password', 'Mínimo de ' . MIN_PASSWORD . ' caracteres.');
password_field('confirmar', 'Repita a senha nova', 'new-password');
?>

        <button class="btn-primary btn-block" type="submit">Salvar senha nova</button>
      </form>
    </section>
<?php admin_foot(); ?>
