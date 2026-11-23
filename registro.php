<?php
session_start();

require 'conexion.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$mailCfg = $config['mail'] ?? [];

$error = '';
$exito = '';
$base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '')), '/');
$base_url = $base_path !== '' ? $base_path : '';
$login_url = $base_url . '/iniciarsesion.php';
$logo_url = $base_url . '/assets/branding/logo-gestpro.jpeg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar = $_POST['confirmar_contrasena'] ?? '';

    if (empty($nombre) || empty($dni) || empty($email) || empty($contrasena) || empty($confirmar)) {
        $error = "Todos los campos son obligatorios.";
    } elseif ($contrasena !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($contrasena) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            $contrasena_hasheada = password_hash($contrasena, PASSWORD_DEFAULT);
            $dni_mayusculas = strtoupper($dni);

            $sql = "INSERT INTO Usuario (nombre, dni, email, contrasena) VALUES (:nombre, :dni, :email, :contrasena)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $nombre,
                ':dni' => $dni_mayusculas,
                ':email' => $email,
                ':contrasena' => $contrasena_hasheada
            ]);

            $mailHost     = $mailCfg['host'] ?? (getenv('MAIL_HOST') ?: '');
            $mailUser     = $mailCfg['username'] ?? (getenv('MAIL_USER') ?: '');
            $mailPass     = $mailCfg['password'] ?? (getenv('MAIL_PASS') ?: '');
            $mailPort     = (int) ($mailCfg['port'] ?? (getenv('MAIL_PORT') ?: 465));
            $mailFrom     = $mailCfg['from_email'] ?? (getenv('MAIL_FROM') ?: $mailUser);
            $mailFromName = $mailCfg['from_name'] ?? (getenv('MAIL_FROM_NAME') ?: 'Gestpro VeriFactu');

            if ($mailHost !== '' && $mailUser !== '' && $mailPass !== '') {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $mailHost;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $mailUser;
                    $mail->Password   = $mailPass;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = $mailPort;

                    $mail->setFrom($mailFrom, $mailFromName);
                    $mail->addAddress($email, $nombre);

                    $safeNombre = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
                    $mail->isHTML(true);
                    $mail->Subject = 'Bienvenido a Gestpro VeriFactu';
                    $mail->Body    = "
                        <h2>Hola {$safeNombre},</h2>
                        <p>¡Gracias por registrarte en Gestpro VeriFactu!</p>
                        <p>Tu cuenta ha sido creada con éxito. Ya puedes iniciar sesión y empezar a emitir facturas adaptadas a la nueva normativa de la Agencia Tributaria.</p>
                        <br>
                        <p>Un saludo,<br><strong>El equipo de Gestpro.</strong></p>
                    ";
                    $mail->AltBody = "Hola {$nombre},

¡Gracias por registrarte en Gestpro VeriFactu!
Tu cuenta ha sido creada con éxito.

Un saludo,
El equipo de Gestpro.";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("El correo de bienvenida no se pudo enviar. Mailer Error: {$mail->ErrorInfo}");
                }
            }

            $exito = "Usuario registrado correctamente. Ya puedes iniciar sesión.";
            $_POST = array();

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Error: El DNI o el Email ya están registrados en el sistema.";
            } else {
                error_log("Error en el registro de usuario: " . $e->getMessage());
                $error = "Ha ocurrido un error inesperado al procesar tu solicitud. Inténtalo más tarde.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Gestpro VeriFactu</title>
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
                    <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo de GestPro" class="h-16 w-16 object-contain">
                </span>
            </div>
            <h1 class="text-5xl font-bold tracking-tight mb-4">Gest<span class="text-blue-500">Pro</span></h1>
            <p class="text-xl text-slate-300 font-light mb-8">Sistema de Facturación Certificada</p>
            <div class="bg-slate-800/50 p-6 rounded-2xl border border-slate-700 backdrop-blur-sm">
                <p class="text-sm text-slate-400 leading-relaxed">
                    Crea tu cuenta segura para empezar a emitir facturas adaptadas a la nueva normativa de la Agencia Tributaria.
                </p>
            </div>
        </div>
    </div>

    <div class="w-full lg:w-1/2 flex min-h-screen lg:min-h-0 items-center justify-center px-4 py-10 sm:px-8 sm:py-12 overflow-y-auto">
        <div class="w-full max-w-md bg-white p-6 sm:p-8 rounded-3xl shadow-2xl border border-slate-100 my-4">
            <div class="lg:hidden text-center mb-8">
                <div class="mb-4 flex justify-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-50 ring-1 ring-slate-200 overflow-hidden">
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo de GestPro" class="h-11 w-11 object-contain">
                    </span>
                </div>
                <h1 class="text-4xl font-bold text-slate-900 tracking-tight">Gest<span class="text-blue-600">Pro</span></h1>
            </div>

            <div class="mb-8">
                <h2 class="text-2xl font-semibold text-slate-800">Crear una cuenta</h2>
                <p class="text-slate-500 text-sm mt-2">Rellena los datos para darte de alta en el sistema.</p>
            </div>

            <?php if (!empty($error)) { ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-md">
                    <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php } ?>

            <?php if (!empty($exito)) { ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-md">
                    <p class="text-sm text-green-700 font-medium"><?php echo htmlspecialchars($exito); ?></p>
                    <a href="<?php echo htmlspecialchars($login_url); ?>" class="text-sm text-green-800 underline mt-2 inline-block">Ir a iniciar sesión</a>
                </div>
            <?php } ?>

            <form action="" method="POST" class="space-y-5">
                <div>
                    <label for="nombre" class="block text-sm font-medium text-slate-700 mb-1">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" required 
                        value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
                        class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-slate-800 bg-slate-50"
                        placeholder="Ej: Juan Pérez">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="dni" class="block text-sm font-medium text-slate-700 mb-1">DNI / NIF</label>
                        <input type="text" id="dni" name="dni" required 
                            value="<?php echo isset($_POST['dni']) ? htmlspecialchars($_POST['dni']) : ''; ?>"
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-slate-800 bg-slate-50 uppercase"
                            placeholder="12345678X">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Correo electrónico</label>
                        <input type="email" id="email" name="email" required 
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-slate-800 bg-slate-50"
                            placeholder="correo@ejemplo.com">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="contrasena" class="block text-sm font-medium text-slate-700 mb-1">Contraseña</label>
                        <input type="password" id="contrasena" name="contrasena" required 
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-slate-800 bg-slate-50"
                            placeholder="Mín. 6 caracteres">
                    </div>

                    <div>
                        <label for="confirmar_contrasena" class="block text-sm font-medium text-slate-700 mb-1">Repetir contraseña</label>
                        <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" required 
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-slate-800 bg-slate-50"
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-blue-200 transition transform hover:-translate-y-0.5 mt-4">
                    Completar registro
                </button>
            </form>
            
            <div class="mt-8 text-center text-sm">
                <p class="text-slate-500">¿Ya tienes una cuenta? <a href="<?php echo htmlspecialchars($login_url); ?>" class="text-blue-600 font-semibold hover:underline">Inicia sesión aquí</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/mobile-tap.js?v=2" defer></script>
</body>
</html>
