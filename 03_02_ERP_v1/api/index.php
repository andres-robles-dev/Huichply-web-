<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// --- HELPER: Strip /api or script directory ---
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($scriptDir !== '' && strpos($path, $scriptDir) === 0) {
    $path = substr($path, strlen($scriptDir));
}

// Ensure it starts with /
if ($path === '' || $path[0] !== '/') {
    $path = '/' . $path;
}

// If it starts with /api (case insensitive), remove it
if (stripos($path, '/api') === 0) {
    $path = substr($path, 4);
}

if ($path === '') $path = '/';

// --- HELPER: Auth Check ---
function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        json_out(['error' => 'No autorizado'], 401);
    }
}

try {
    // --- ROUTES ---

    // AUTH: Login
    if ($method === 'POST' && $path === '/auth/login') {
        $in = read_json_body();
        $email = $in['email'] ?? '';
        // No security as requested for initial tests
        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM usuarios WHERE correo_electronico = ? AND rol IN ('admin', 'erp') LIMIT 1");
        $st->execute([$email]);
        $user = $st->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['rol'];
            json_out(['ok' => true, 'user' => $user]);
        } else {
            json_out(['error' => 'Usuario no encontrado o sin permisos'], 401);
        }
    }

    // AUTH: Register (ERP)
    if ($method === 'POST' && $path === '/auth/register') {
        $in = read_json_body();
        $email = $in['email'] ?? '';
        $name = $in['nombre'] ?? '';
        
        $pdo = db();
        $st = $pdo->prepare("INSERT INTO usuarios (correo_electronico, nombre, rol) VALUES (?, ?, 'erp')");
        try {
            $st->execute([$email, $name]);
            json_out(['ok' => true]);
        } catch (Exception $e) {
            json_out(['error' => 'Error al registrar: ' . $e->getMessage()], 400);
        }
    }

    // ORDERS: List
    if ($method === 'GET' && $path === '/orders') {
        require_auth();
        $pdo = db();
        $st = $pdo->query("SELECT p.*, u.nombre as cliente_nombre FROM pedidos p LEFT JOIN usuarios u ON p.usuario_id = u.id ORDER BY p.creado_en DESC");
        $orders = $st->fetchAll();
        json_out(['ok' => true, 'items' => $orders]);
    }

    // ORDERS: Update Status & Assign Freelancer
    if ($method === 'POST' && $path === '/orders/update') {
        require_auth();
        $in = read_json_body();
        $orderId = $in['id'] ?? 0;
        $status = $in['estado'] ?? '';
        $freelancerId = $in['proveedor_id'] ?? null;

        $pdo = db();
        
        // Get current status for history
        $st = $pdo->prepare("SELECT estado FROM pedidos WHERE id = ?");
        $st->execute([$orderId]);
        $oldStatus = $st->fetchColumn();

        $st = $pdo->prepare("UPDATE pedidos SET estado = ?, proveedor_id = ? WHERE id = ?");
        $st->execute([$status, $freelancerId, $orderId]);
        
        // --- INTEGRATION: Log History for Web Metrics ---
        if ($status !== $oldStatus) {
            $stHist = $pdo->prepare("INSERT INTO historial_estados_pedidos (pedido_id, estado, estado_anterior, cambiado_por, notas) VALUES (?, ?, ?, ?, ?)");
            $stHist->execute([
                $orderId, 
                $status, 
                $oldStatus, 
                'erp_user:' . $_SESSION['user_id'], 
                'Actualizado desde ERP'
            ]);
        }

        json_out(['ok' => true]);
    }

    // FREELANCERS: List
    if ($method === 'GET' && $path === '/freelancers') {
        require_auth();
        $pdo = db();
        $st = $pdo->query("SELECT * FROM proveedores_servicios ORDER BY nombre ASC");
        $freelancers = $st->fetchAll();
        json_out(['ok' => true, 'items' => $freelancers]);
    }

    // FREELANCERS: Add
    if ($method === 'POST' && $path === '/freelancers') {
        require_auth();
        $in = read_json_body();
        $name = $in['nombre'] ?? '';
        $phone = $in['telefono'] ?? '';

        $pdo = db();
        $st = $pdo->prepare("INSERT INTO proveedores_servicios (nombre, telefono, estado) VALUES (?, ?, 'active')");
        $st->execute([$name, $phone]);
        json_out(['ok' => true]);
    }

    // FREELANCERS: Toggle Status
    if ($method === 'POST' && $path === '/freelancers/toggle') {
        require_auth();
        $in = read_json_body();
        $id = $in['id'] ?? 0;
        $status = $in['estado'] ?? 'active'; // 'active' or 'inactive'

        $pdo = db();
        $st = $pdo->prepare("UPDATE proveedores_servicios SET estado = ? WHERE id = ?");
        $st->execute([$status, $id]);
        json_out(['ok' => true]);
    }

    // REVIEWS: List
    if ($method === 'GET' && $path === '/reviews') {
        require_auth();
        $pdo = db();
        $st = $pdo->query("SELECT v.*, u.nombre as cliente_nombre FROM valoraciones v JOIN usuarios u ON v.usuario_id = u.id ORDER BY v.creado_en DESC");
        $reviews = $st->fetchAll();
        json_out(['ok' => true, 'items' => $reviews]);
    }

    // USERS: List
    if ($method === 'GET' && $path === '/users') {
        require_auth();
        $pdo = db();
        $st = $pdo->query("SELECT id, nombre, correo_electronico, rol, creado_en FROM usuarios ORDER BY creado_en DESC");
        $users = $st->fetchAll();
        json_out(['ok' => true, 'items' => $users]);
    }

    json_out(['error' => 'Ruta no encontrada: ' . $path], 404);

} catch (Exception $e) {
    json_out(['error' => 'Internal Server Error: ' . $e->getMessage()], 500);
}
