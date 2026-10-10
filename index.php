<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_setup.php';

try {
    $pdo = db();
    ensureTables($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    $dbError = $e->getMessage();
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function money($value): string {
    return '$' . number_format((float)$value, 0, ',', '.');
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if (isset($pdo) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.'];
        header('Location: index.php'); exit;
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $cliente = trim((string)($_POST['cliente'] ?? ''));
            $hamburguesa = (string)($_POST['hamburguesa'] ?? 'Sencilla');
            $cantidad = filter_var($_POST['cantidad'] ?? 1, FILTER_VALIDATE_INT);
            $direccion = trim((string)($_POST['direccion_entrega'] ?? ''));
            $propinaRaw = str_replace(',', '.', (string)($_POST['propina'] ?? '0'));
            $propina = filter_var($propinaRaw, FILTER_VALIDATE_FLOAT);
            $estado = (string)($_POST['estado'] ?? 'Recibido');

            $hamburguesasValidas = ['Sencilla', 'Doble', 'Mixta'];
            $estadosValidos = ['Recibido', 'En preparación', 'En camino', 'Entregado', 'Cancelado'];

            if ($cliente === '' || mb_strlen($cliente) > 120 || $direccion === '' || mb_strlen($direccion) > 255) {
                throw new RuntimeException('Escribe el nombre del cliente y una dirección válida.');
            }
            if (!in_array($hamburguesa, $hamburguesasValidas, true)) {
                throw new RuntimeException('Selecciona una hamburguesa válida.');
            }
            if ($cantidad === false || $cantidad < 1 || $cantidad > 99) {
                throw new RuntimeException('La cantidad debe estar entre 1 y 99.');
            }
            if ($propina === false || $propina < 0 || $propina > 100000000) {
                throw new RuntimeException('La propina debe ser un valor válido y no negativo.');
            }
            if (!in_array($estado, $estadosValidos, true)) {
                $estado = 'Recibido';
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE pedidos SET cliente=?, hamburguesa=?, cantidad=?, direccion_entrega=?, propina=?, estado=? WHERE id=?');
                $stmt->execute([$cliente, $hamburguesa, $cantidad, $direccion, $propina, $estado, $id]);
                $_SESSION['flash'] = ['type' => 'success', 'text' => '¡Pedido actualizado con éxito!'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO pedidos (cliente, hamburguesa, cantidad, direccion_entrega, propina, estado) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$cliente, $hamburguesa, $cantidad, $direccion, $propina, $estado]);
                $_SESSION['flash'] = ['type' => 'success', 'text' => '¡Pedido recibido! La cocina ya está en sintonía.'];
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) throw new RuntimeException('Pedido no válido.');
            $stmt = $pdo->prepare('DELETE FROM pedidos WHERE id=?');
            $stmt->execute([$id]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Pedido eliminado.'];
        }
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo completar la operación. Verifica la conexión con la base de datos.'];
    }
    header('Location: index.php'); exit;
}

$editing = null;
if (isset($pdo) && isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $editing = $stmt->fetch() ?: null;
}

$search = trim((string)($_GET['q'] ?? ''));
$filterStatus = (string)($_GET['estado'] ?? '');
$orders = [];
$stats = ['total' => 0, 'activos' => 0, 'tips' => 0];
if (isset($pdo)) {
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(cliente LIKE ? OR direccion_entrega LIKE ? OR CAST(id AS CHAR) LIKE ?)';
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    $validStatuses = ['Recibido', 'En preparación', 'En camino', 'Entregado', 'Cancelado'];
    if (in_array($filterStatus, $validStatuses, true)) {
        $where[] = 'estado = ?'; $params[] = $filterStatus;
    }
    $sql = 'SELECT * FROM pedidos' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY creado_en DESC, id DESC';
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $orders = $stmt->fetchAll();

    $stats = $pdo->query("SELECT COUNT(*) total, SUM(estado NOT IN ('Entregado','Cancelado')) activos, COALESCE(SUM(propina),0) tips FROM pedidos")->fetch();
}
$burgers = ['Sencilla', 'Doble', 'Mixta'];
$statuses = ['Recibido', 'En preparación', 'En camino', 'Entregado', 'Cancelado'];
$form = $editing ?: ['id' => 0, 'cliente' => '', 'hamburguesa' => 'Sencilla', 'cantidad' => 1, 'direccion_entrega' => '', 'propina' => '0.00', 'estado' => 'Recibido'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neon Burger Club | Pedidos</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="scanlines" aria-hidden="true"></div>
<header class="topbar">
    <a class="brand" href="index.php"><span class="brand-icon">★</span><span>NEON <b>BURGER</b><small>CLUB • EST. 1986</small></span></a>
    <div class="open-sign"><span class="pulse"></span> ABIERTO 24/7</div>
</header>

<main class="container">
    <?php if (isset($dbError)): ?>
        <section class="notice error"><strong>¡Houston, tenemos un problema!</strong><br>No fue posible conectar con MySQL. Revisa las credenciales, que el servidor permita conexiones y que la base de datos exista.<br><small><?= h($dbError) ?></small></section>
    <?php else: ?>
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow">★ SABOR CLÁSICO, VIBRA ELÉCTRICA ★</p>
            <h1>ORDERS<br><span>AFTER DARK</span></h1>
            <p class="hero-sub">Tú pones el antojo. Nosotros ponemos el rock &amp; roll.</p>
            <a href="#pedido-form" class="button button-pink">＋ TOMAR PEDIDO</a>
        </div>
        <div class="hero-art" aria-hidden="true">
            <div class="sun"></div><div class="checker"></div>
            <div class="burger">
                <div class="bun-top"><i></i><i></i><i></i><i></i><i></i></div>
                <div class="lettuce"></div><div class="cheese"></div><div class="patty"></div><div class="bun-bottom"></div>
            </div>
            <div class="art-caption">FRESH • HOT • RADICAL</div>
            <span class="spark spark-one">✦</span><span class="spark spark-two">✧</span>
        </div>
    </section>

    <?php if ($flash): ?><div class="notice <?= h($flash['type']) ?>"><?= h($flash['text']) ?></div><?php endif; ?>

    <section class="stats">
        <article class="stat-card"><span class="stat-icon">🍔</span><div><small>PEDIDOS REGISTRADOS</small><strong><?= (int)$stats['total'] ?></strong></div></article>
        <article class="stat-card"><span class="stat-icon">⚡</span><div><small>EN MOVIMIENTO</small><strong><?= (int)$stats['activos'] ?></strong></div></article>
        <article class="stat-card"><span class="stat-icon">♡</span><div><small>PROPINAS ACUMULADAS</small><strong><?= money($stats['tips']) ?></strong></div></article>
    </section>

    <section class="content-grid">
        <section class="panel form-panel" id="pedido-form">
            <div class="panel-heading"><div><p class="eyebrow">PANEL DE CONTROL</p><h2><?= $editing ? 'EDITAR PEDIDO' : 'NUEVO PEDIDO' ?></h2></div><span class="mini-sticker">HOT<br>STUFF!</span></div>
            <form method="post" action="index.php" class="order-form">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
                <label for="cliente">NOMBRE DEL CLIENTE <span>*</span></label>
                <input id="cliente" name="cliente" maxlength="120" required placeholder="Ej. Alex Rivera" value="<?= h($form['cliente']) ?>">

                <label for="hamburguesa">¿QUÉ SE TE ANTOJA? <span>*</span></label>
                <div class="burger-options">
                    <?php foreach ($burgers as $burger): ?>
                    <label class="burger-option">
                        <input type="radio" name="hamburguesa" value="<?= h($burger) ?>" <?= $form['hamburguesa'] === $burger ? 'checked' : '' ?>>
                        <span class="option-emoji"><?= $burger === 'Sencilla' ? '🍔' : ($burger === 'Doble' ? '🍔🍔' : '🧀') ?></span>
                        <strong><?= h($burger) ?></strong><small><?= $burger === 'Sencilla' ? 'El clásico' : ($burger === 'Doble' ? 'Doble actitud' : 'La combinación') ?></small>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="two-col">
                    <div><label for="cantidad">CANTIDAD</label><input id="cantidad" type="number" name="cantidad" min="1" max="99" required value="<?= (int)$form['cantidad'] ?>"></div>
                    <div><label for="propina">PROPINA ($)</label><input id="propina" type="number" name="propina" min="0" max="100000000" step="100" value="<?= h($form['propina']) ?>" placeholder="0"></div>
                </div>
                <label for="direccion_entrega">DIRECCIÓN DE ENTREGA <span>*</span></label>
                <textarea id="direccion_entrega" name="direccion_entrega" rows="2" maxlength="255" required placeholder="Calle, número, barrio y referencias..."><?= h($form['direccion_entrega']) ?></textarea>

                <?php if ($editing): ?>
                <label for="estado">ESTADO DEL PEDIDO</label>
                <select id="estado" name="estado"><?php foreach ($statuses as $status): ?><option value="<?= h($status) ?>" <?= $form['estado'] === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?></select>
                <?php else: ?><input type="hidden" name="estado" value="Recibido"><?php endif; ?>

                <button class="button button-pink full-width" type="submit"><?= $editing ? '✦ GUARDAR CAMBIOS' : '＋ CONFIRMAR PEDIDO' ?></button>
                <?php if ($editing): ?><a class="cancel-edit" href="index.php">Cancelar edición</a><?php endif; ?>
            </form>
            <p class="form-foot">✦ Hecho con cariño y mucho queso.</p>
        </section>

        <section class="panel orders-panel" id="pedidos">
            <div class="panel-heading"><div><p class="eyebrow">LA PARRILLA ESTÁ ENCENDIDA</p><h2>LISTA DE PEDIDOS</h2></div><span class="order-count"><?= count($orders) ?> ITEMS</span></div>
            <form class="filters" method="get" action="index.php">
                <input name="q" value="<?= h($search) ?>" placeholder="Buscar cliente, dirección o #..." aria-label="Buscar pedidos">
                <select name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><?php foreach ($statuses as $status): ?><option value="<?= h($status) ?>" <?= $filterStatus === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?></select>
                <button class="button button-dark" type="submit">FILTRAR</button>
                <?php if ($search !== '' || $filterStatus !== ''): ?><a class="reset-link" href="index.php">Limpiar</a><?php endif; ?>
            </form>

            <?php if (!$orders): ?>
                <div class="empty-state"><div>🍟</div><h3>¡Todavía no hay pedidos!</h3><p>El primer antojo está a un clic de distancia.</p><a href="#pedido-form" class="text-link">Crear el primer pedido ↗</a></div>
            <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                <article class="order-card">
                    <div class="order-top"><span class="order-number">#<?= str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT) ?></span><span class="status status-<?= strtolower(str_replace([' ', 'ó'], ['-', 'o'], $order['estado'])) ?>"><?= h($order['estado']) ?></span></div>
                    <div class="order-main"><div class="order-burger"><?= $order['hamburguesa'] === 'Doble' ? '🍔🍔' : ($order['hamburguesa'] === 'Mixta' ? '🧀🍔' : '🍔') ?></div><div class="order-info"><h3><?= h($order['cliente']) ?></h3><p><?= h($order['hamburguesa']) ?> <span>× <?= (int)$order['cantidad'] ?></span></p><p class="address">⌖ <?= h($order['direccion_entrega']) ?></p></div></div>
                    <div class="order-bottom"><span>♡ Propina: <b><?= money($order['propina']) ?></b></span><small><?= date('d/m/Y · H:i', strtotime($order['creado_en'])) ?></small></div>
                    <div class="order-actions"><a class="button button-outline" href="index.php?editar=<?= (int)$order['id'] ?>#pedido-form">✎ EDITAR</a>
                    <form method="post" action="index.php" onsubmit="return confirm('¿Seguro que quieres eliminar este pedido?');"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$order['id'] ?>"><button class="button button-delete" type="submit">× ELIMINAR</button></form></div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </section>
    <footer><span>NEON BURGER CLUB © 1986–2026</span><span>GOOD FOOD. GOOD MOOD. <b>★</b></span></footer>
    <?php endif; ?>
</main>
</body>
</html>
