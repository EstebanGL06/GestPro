# GestPro — Sistema de Facturación adaptado a Verifactu

Aplicación web full stack orientada a autónomos y pequeñas empresas para la gestión de clientes, emisión y consulta de facturas en PDF y trazabilidad de registros conforme a los requisitos técnicos del reglamento **Verifactu**.

## Funcionalidades principales
- **Autenticación y roles:** Registro de usuarios con envío de correo de bienvenida (PHPMailer), inicio de sesión seguro (`password_hash` / `password_verify`) y separación de permisos entre **Cliente** y **Administrador**.
- **Gestión de facturas:** Creación, edición, listado con filtrado por DNI, vista detallada y eliminación transaccional de facturas y líneas de concepto.
- **Generación de facturas en PDF:** Exportación dinámica en formato A4 con cálculo de base imponible, IVA/IRPF y estado de verificación mediante **TCPDF**.
- **Panel analítico:** Visualización de evolución de ingresos por fecha mediante **Chart.js** y diseño *responsive* adaptado a dispositivos móviles.

## Stack tecnológico
- **Backend:** PHP 8+, PDO (MySQL / MariaDB)
- **Frontend:** HTML5, Tailwind CSS, JavaScript (Chart.js)
- **Librerías:** TCPDF (generación de PDF), PHPMailer (notificaciones SMTP)

## Instalación y configuración en local
1. Clona el repositorio:
   ```bash
   git clone https://github.com/tu-usuario/gestpro.git
   ```
2. Crea la base de datos en MySQL/MariaDB e importa el esquema con las tablas `Usuario`, `Factura`, `LineaFactura` y `RegistroVerifactu`.
3. Copia el archivo de configuración de ejemplo y añade tus credenciales locales:
   ```bash
   cp config.example.php config.php
   ```
4. Instala o coloca las dependencias `TCPDF` (`vendor/tcpdf/`) y `PHPMailer` (`PHPMailer/src/`).
5. Inicia tu servidor local (Apache/Nginx o `php -S localhost:8000`) y accede a `index.php`.

## Equipo de desarrollo
Proyecto Final de Grado Superior en Desarrollo de Aplicaciones Web (DAW) desarrollado en equipo de 4 integrantes.
