<?php
// 1. Reanudamos la sesión para poder interactuar con ella
session_start();

// 2. Vaciamos todas las variables de sesión actuales (usuario_id, nombre, dni, etc.)
$_SESSION = array();

// 3. Borramos la cookie de sesión del navegador (Capa extra de seguridad)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruimos la sesión en el servidor
session_destroy();

// 5. Redirigimos al usuario a la página de inicio de sesión
header("Location: iniciarsesion.php");
exit;
?>