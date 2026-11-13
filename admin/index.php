<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

require_login();

$current = prices_load();
$values = [];
$errors = [];
$note = $current['observacao'];
$flash = take_flash();

foreach (ROOMS as $slug => $name) {
    $values[$slug] = format_price($current['quartos'][$slug]['preco']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $flash = ['type' => 'error', 'message' => 'A página ficou aberta por muito tempo. Confira os valores e salve de novo.'];
    } else {
        $parsed = [];
        $posted = isset($_POST['preco']) && is_array($_POST['preco']) ? $_POST['preco'] : [];

        foreach (ROOMS as $slug => $name) {
            $raw = isset($posted[$slug]) && is_string($posted[$slug]) ? $posted[$slug] : '';
            $values[$slug] = $raw;
            [$parsed[$slug], $err] = parse_price($raw);
            if ($err !== null) {
                $errors[$slug] = $err;
            }
        }

        $note = trim((string)($_POST['observacao'] ?? ''));
        $note = preg_replace('/\s+/u', ' ', $note) ?? '';
        if (function_exists('mb_substr')) {
            $note = mb_substr($note, 0, 240, 'UTF-8');
        }

        if ($errors) {
            $flash = ['type' => 'error', 'message' => 'Corrija os campos destacados e salve de novo.'];
        } elseif (prices_save($parsed, $note)) {
            flash('success', 'Preços salvos. O site já mostra os novos valores.');
            redirect('index.php');
        } else {
            $flash = ['type' => 'error', 'message' => 'Não foi possível salvar. A pasta "data" do site precisa de permissão de escrita (veja o GUIA-DO-SITE.txt).'];
        }
    }
}

admin_head('Preços dos quartos', 'precos');
?>
    <section class="admin-card" aria-labelledby="titulo-precos">
      <p class="eyebrow">Painel</p>
      <h1 id="titulo-precos">Preços dos quartos</h1>
      <p class="lead">Informe o valor da diária de cada quarto. Ele aparece no site como “a partir de”. Deixe em branco para mostrar “Valores sob consulta”.</p>

<?php admin_alert($flash); ?>

      <form method="post" action="index.php" novalidate>
        <?= csrf_field() ?>

        <div class="price-grid">
<?php foreach (ROOMS as $slug => $name): $id = 'preco-' . $slug; $hasErr = isset($errors[$slug]); ?>
          <div class="field price-row<?= $hasErr ? ' has-error' : '' ?>">
            <label for="<?= h($id) ?>"><?= h($name) ?></label>
            <div class="money">
              <span class="money-prefix" aria-hidden="true">R$</span>
              <input class="input" type="text" inputmode="decimal" id="<?= h($id) ?>"
                name="preco[<?= h($slug) ?>]" value="<?= h($values[$slug]) ?>"
                placeholder="Sob consulta" autocomplete="off"
                <?= $hasErr ? 'aria-invalid="true" aria-describedby="' . h($id) . '-erro"' : '' ?>>
            </div>
<?php if ($hasErr): ?>
            <p class="field-error" id="<?= h($id) ?>-erro"><?= h($errors[$slug]) ?></p>
<?php endif; ?>
          </div>
<?php endforeach; ?>
        </div>

        <div class="field">
          <label for="observacao">Aviso exibido abaixo dos preços</label>
          <textarea class="input" id="observacao" name="observacao" rows="3" maxlength="240"
            aria-describedby="observacao-dica"><?= h($note) ?></textarea>
          <p class="hint" id="observacao-dica">Até 240 caracteres. Deixe em branco para não mostrar aviso.</p>
        </div>

        <div class="form-actions">
          <button class="btn-primary" type="submit">Salvar preços</button>
          <p class="hint">Última alteração: <?= h(format_date($current['atualizado'])) ?></p>
        </div>
      </form>
    </section>
<?php admin_foot(); ?>
