<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

start_session();
admin_headers();

// Só sai por formulário com o código de segurança (evita que um link de fora deslogue a pessoa)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    end_session();
    redirect('login.php?saiu=1');
}

redirect(!empty($_SESSION['auth']) ? 'index.php' : 'login.php');
