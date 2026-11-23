<?php
session_start();
require 'conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
    $contrasena = isset($_POST['contrasena']) ? $_POST['contrasena'] : '';

    if (empty($dni) || empty($contrasena)) {
        $error = "Por favor, introduce tu DNI y contraseña.";
    } else {
        try {
            $dni_mayusculas = strtoupper($dni);

            $sql = "SELECT usuario_id, nombre, contrasena, admin FROM Usuario WHERE dni = :dni";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':dni' => $dni_mayusculas]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
                session_regenerate_id(true);

                $_SESSION['usuario_id'] = $usuario['usuario_id'];
                $_SESSION['nombre']     = $usuario['nombre'];
                $_SESSION['dni']        = $dni_mayusculas;
                $_SESSION['es_admin']   = (int)$usuario['admin'] === 1;

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "DNI o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            error_log("Error en inicio de sesión: " . $e->getMessage());
            $error = "Error interno al verificar las credenciales. Inténtalo de nuevo más tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Gestpro VeriFactu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        *, *::before, *::after { box-sizing: border-box; }
        html, body { overflow-x: hidden; }
        body { font-family: 'Inter', sans-serif; }
        input, select, textarea { font-size: 16px; }
        a, button { touch-action: manipulation; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col lg:flex-row">

    <div class="hidden lg:flex lg:w-1/2 bg-slate-900 text-white flex-col justify-center items-center p-12 relative overflow-hidden">
        <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-600 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-blue-400 rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>

        <div class="z-10 text-center max-w-lg">
            <div class="mb-6 flex justify-center">
                <span class="flex h-24 w-24 items-center justify-center rounded-[2rem] bg-white shadow-2xl shadow-blue-950/20 overflow-hidden">
                    <img src="assets/branding/logo-gestpro.jpeg" alt="Logo de GestPro" class="h-16 w-16 object-contain">
                </span>
            </div>
            <h1 class="text-5xl font-bold tracking-tight mb-4">Gest<span class="text-blue-500">Pro</span></h1>
            <p class="text-xl text-slate-300 font-light mb-8">Sistema de Facturación Certificada</p>
            <div class="bg-slate-800/50 p-6 rounded-2xl border border-slate-700 backdrop-blur-sm">
                <p class="text-sm text-slate-400 leading-relaxed">
                    Accede a tu portal seguro para gestionar tus facturas y cumplir con la nueva normativa VeriFactu.
                </p>
            </div>
        </div>
    </div>

    <div class="w-full lg:w-1/2 flex min-h-screen lg:min-h-0 items-center justify-center px-4 py-10 sm:px-8 sm:py-12">
        <div class="w-full max-w-md bg-white p-6 sm:p-8 rounded-3xl shadow-2xl border border-slate-100 my-4">
            <div class="lg:hidden text-center mb-8">
                <div class="mb-4 flex justify-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-50 ring-1 ring-slate-200 overflow-hidden">
                        <img src="assets/branding/logo-gestpro.jpeg" alt="Logo de GestPro" class="h-11 w-11 object-contain">
                    </span>
                </div>
                <h1 class="text-4xl font-bold text-slate-900 tracking-tight">Gest<span class="text-blue-600">Pro</span></h1>
            </div>

            <div class="mb-8">
                <h2 class="text-2xl font-semibold text-slate-800">Bienvenido de nuevo</h2>
                <p class="text-slate-500 text-sm mt-2">Introduce tu DNI y contraseña para acceder.</p>
            </div>

            <?php if (!empty($error)) { ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
                    <p class="text-sm text-red-700 font-medium"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php } ?>

            <form action="" method="POST" class="space-y-6">
                <div>
                    <label for="dni" class="block text-sm font-medium text-slate-700 mb-2">DNI / NIF</label>
                    <input type="text" id="dni" name="dni" required 
                        value="<?php echo isset($_POST['dni']) ? htmlspecialchars($_POST['dni']) : ''; ?>"
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-slate-800 placeholder-slate-400 bg-slate-50 uppercase"
                        placeholder="12345678X">
                </div>

                <div>
                    <label for="contrasena" class="block text-sm font-medium text-slate-700 mb-2">Contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" required 
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-slate-800 placeholder-slate-400 bg-slate-50"
                        placeholder="••••••••">
                </div>

                <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-blue-200 transition transform hover:-translate-y-0.5 mt-2">
                    Acceder a mi cuenta
                </button>
            </form>
            
            <div class="mt-7 text-center text-sm">
                <p class="text-slate-500">¿No tienes una cuenta? <a href="registro.php" class="text-blue-600 font-semibold hover:underline">Regístrate aquí</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/mobile-tap.js?v=2" defer></script>
</body>
</html>
