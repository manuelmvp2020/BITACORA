<?php
// recuperar_password.php - Página de recuperación de contraseña
session_start();
require_once 'config.php';

$error = '';
$success = '';
$step = 'solicitar'; // steps: solicitar, verificar, cambiar

// ============================================
// FUNCIÓN PARA ENVIAR EMAIL
// ============================================
function enviarEmailRecuperacion($email, $token, $usuario) {
    $asunto = "Recuperación de contraseña - Bitácora de Registro";
    
    $link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/recuperar_password.php?token=" . $token;
    
    $mensaje = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f4f4f4; }
            .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
            .content { background: white; padding: 30px; border-radius: 5px; }
            .button { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #7f8c8d; font-size: 12px; }
            .token { background: #f8f9fa; padding: 10px; font-family: monospace; text-align: center; font-size: 18px; letter-spacing: 2px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📋 Bitácora de Registro</h2>
                <p>Recuperación de contraseña</p>
            </div>
            <div class='content'>
                <p>Hola <strong>" . htmlspecialchars($usuario) . "</strong>,</p>
                <p>Hemos recibido una solicitud para restablecer tu contraseña. Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                <p style='text-align: center;'>
                    <a href='" . $link . "' class='button' style='background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>🔐 Restablecer Contraseña</a>
                </p>
                <p>O copia y pega este enlace en tu navegador:</p>
                <p class='token'>" . $link . "</p>
                <p>Si no solicitaste este cambio, puedes ignorar este mensaje.</p>
                <p><strong>Este enlace expirará en 1 hora.</strong></p>
                <hr>
                <p style='font-size: 12px; color: #7f8c8d;'>Este es un mensaje automático, por favor no responder.</p>
            </div>
            <div class='footer'>
                <p>© 2024 - Sistema Bitácora de Registro</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Bitácora de Registro <noreply@bitacora.com>\r\n";
    $headers .= "Reply-To: soporte@bitacora.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $asunto, $mensaje, $headers);
}

// ============================================
// FUNCIÓN PARA GENERAR TOKEN ÚNICO
// ============================================
function generarToken() {
    return bin2hex(random_bytes(32));
}

// ============================================
// PASO 1: SOLICITAR RECUPERACIÓN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ============================================
    // SOLICITAR RECUPERACIÓN (ENVIAR EMAIL)
    // ============================================
    if ($_POST['action'] === 'solicitar') {
        $email = trim($_POST['email'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        
        if (empty($email) || empty($usuario)) {
            $error = "Por favor, complete todos los campos";
        } else {
            // Buscar usuario por email y nombre de usuario
            $sql = "SELECT id, usuario, email FROM usuarios WHERE usuario = ? AND email = ? AND activo = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $usuario, $email);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows === 1) {
                $user = $resultado->fetch_assoc();
                
                // Generar token único
                $token = generarToken();
                $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token en la base de datos
                $sql_insert = "INSERT INTO recuperacion_password (user_id, token, expiracion, usado) VALUES (?, ?, ?, 0)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iss", $user['id'], $token, $expiracion);
                
                if ($stmt_insert->execute()) {
                    // Enviar email
                    if (enviarEmailRecuperacion($email, $token, $user['usuario'])) {
                        $success = "Se ha enviado un enlace de recuperación a tu correo electrónico. Revisa tu bandeja de entrada (o spam).";
                        $step = 'solicitar'; // Mantener en mismo paso pero mostrar éxito
                    } else {
                        $error = "Error al enviar el email. Por favor, intenta más tarde.";
                        // Registrar error
                        if (function_exists('registrarLog')) {
                            registrarLog($conn, $usuario, "Error al enviar email de recuperación", "recuperar_password.php", obtenerIP(), json_encode(['error' => 'mail_failed']));
                        }
                    }
                    $stmt_insert->close();
                } else {
                    $error = "Error al generar la solicitud. Por favor, intenta nuevamente.";
                }
            } else {
                $error = "No se encontró un usuario activo con ese nombre de usuario y correo electrónico.";
                // Registrar intento fallido
                if (function_exists('registrarLog')) {
                    registrarLog($conn, $usuario, "Intento de recuperación fallido", "recuperar_password.php", obtenerIP(), json_encode(['email' => $email, 'estado' => 'usuario_no_encontrado']));
                }
            }
            $stmt->close();
        }
    }
    
    // ============================================
    // CAMBIAR CONTRASEÑA
    // ============================================
    if ($_POST['action'] === 'cambiar') {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirm_password)) {
            $error = "Por favor, complete todos los campos";
        } elseif (strlen($password) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres";
        } elseif ($password !== $confirm_password) {
            $error = "Las contraseñas no coinciden";
        } else {
            // Verificar token
            $sql = "SELECT rp.id, rp.user_id, rp.token, rp.expiracion, rp.usado, u.usuario 
                    FROM recuperacion_password rp 
                    JOIN usuarios u ON rp.user_id = u.id 
                    WHERE rp.token = ? AND rp.usado = 0 AND rp.expiracion > NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($resultado->num_rows === 1) {
                $recuperacion = $resultado->fetch_assoc();
                
                // Actualizar contraseña
                $nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql_update = "UPDATE usuarios SET password = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("si", $nuevo_hash, $recuperacion['user_id']);
                
                if ($stmt_update->execute()) {
                    // Marcar token como usado
                    $sql_usar = "UPDATE recuperacion_password SET usado = 1 WHERE id = ?";
                    $stmt_usar = $conn->prepare($sql_usar);
                    $stmt_usar->bind_param("i", $recuperacion['id']);
                    $stmt_usar->execute();
                    $stmt_usar->close();
                    
                    // Registrar cambio exitoso
                    if (function_exists('registrarLog')) {
                        registrarLog($conn, $recuperacion['usuario'], "Contraseña restablecida exitosamente", "recuperar_password.php", obtenerIP(), json_encode(['metodo' => 'recuperacion']));
                    }
                    
                    $success = "¡Contraseña cambiada exitosamente! Ahora puedes iniciar sesión con tu nueva contraseña.";
                    $step = 'completado';
                } else {
                    $error = "Error al cambiar la contraseña. Por favor, intenta nuevamente.";
                }
                $stmt_update->close();
            } else {
                $error = "El enlace de recuperación es inválido o ha expirado. Por favor, solicita uno nuevo.";
            }
            $stmt->close();
        }
    }
}

// ============================================
// VERIFICAR TOKEN EN URL
// ============================================
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verificar si el token es válido
    $sql = "SELECT rp.id, rp.user_id, rp.token, rp.expiracion, rp.usado, u.usuario 
            FROM recuperacion_password rp 
            JOIN usuarios u ON rp.user_id = u.id 
            WHERE rp.token = ? AND rp.usado = 0 AND rp.expiracion > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 1) {
        $recuperacion = $resultado->fetch_assoc();
        $step = 'cambiar';
    } else {
        $error = "El enlace de recuperación es inválido o ha expirado. Por favor, solicita uno nuevo.";
        $step = 'solicitar';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Bitácora de Registro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #1a2639 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.8;
            font-size: 14px;
        }
        
        .icon {
            font-size: 50px;
            margin-bottom: 15px;
        }
        
        .content {
            padding: 30px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .alert.error {
            background-color: #fee;
            color: #c0392b;
            border-left: 4px solid #c0392b;
        }
        
        .alert.success {
            background-color: #e8f5e9;
            color: #27ae60;
            border-left: 4px solid #27ae60;
        }
        
        .alert i {
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #3498db;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(52,152,219,0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(39,174,96,0.4);
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #ecf0f1;
        }
        
        .footer a {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
        }
        
        .strength-weak { background: #e74c3c; width: 33%; }
        .strength-medium { background: #f39c12; width: 66%; }
        .strength-strong { background: #27ae60; width: 100%; }
        
        .requirement {
            font-size: 11px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .requirement.valid {
            color: #27ae60;
        }
        
        .requirement.invalid {
            color: #e74c3c;
        }
        
        @media (max-width: 480px) {
            .content {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Recuperar Contraseña</h1>
                <p>Restablece el acceso a tu cuenta</p>
            </div>
            
            <div class="content">
                <?php if ($error): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === 'solicitar' || ($step === 'solicitar' && !$success)): ?>
                    <!-- FORMULARIO PARA SOLICITAR RECUPERACIÓN -->
                    <form method="POST" action="recuperar_password.php" id="formSolicitar">
                        <input type="hidden" name="action" value="solicitar">
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nombre de Usuario</label>
                            <input type="text" name="usuario" id="usuario" 
                                   placeholder="Ingresa tu nombre de usuario" 
                                   value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>"
                                   required autofocus>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Correo Electrónico</label>
                            <input type="email" name="email" id="email" 
                                   placeholder="tucorreo@ejemplo.com" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación
                        </button>
                    </form>
                    
                <?php elseif ($step === 'cambiar'): ?>
                    <!-- FORMULARIO PARA CAMBIAR CONTRASEÑA -->
                    <form method="POST" action="recuperar_password.php" id="formCambiar">
                        <input type="hidden" name="action" value="cambiar">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? $_POST['token'] ?? ''); ?>">
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Nueva Contraseña</label>
                            <input type="password" name="password" id="password" 
                                   placeholder="Mínimo 6 caracteres" 
                                   required>
                            <div class="password-strength">
                                <div class="strength-bar">
                                    <div class="strength-bar-fill" id="strengthBar"></div>
                                </div>
                                <div id="strengthText" class="requirement"></div>
                            </div>
                            <div class="requirement" id="lengthReq">
                                <i class="fas fa-circle" style="font-size: 8px;"></i> Mínimo 6 caracteres
                            </div>
                            <div class="requirement" id="numberReq">
                                <i class="fas fa-circle" style="font-size: 8px;"></i> Al menos un número
                            </div>
                            <div class="requirement" id="letterReq">
                                <i class="fas fa-circle" style="font-size: 8px;"></i> Al menos una letra
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirmar Contraseña</label>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   placeholder="Repite la nueva contraseña" 
                                   required>
                            <div id="matchMsg" class="requirement"></div>
                        </div>
                        
                        <button type="submit" class="btn btn-success" id="btnCambiar" disabled>
                            <i class="fas fa-save"></i> Cambiar Contraseña
                        </button>
                    </form>
                    
                <?php elseif ($step === 'completado'): ?>
                    <!-- MENSAJE DE COMPLETADO -->
                    <div style="text-align: center;">
                        <i class="fas fa-check-circle" style="font-size: 60px; color: #27ae60; margin-bottom: 20px;"></i>
                        <p style="margin-bottom: 20px;">Tu contraseña ha sido cambiada exitosamente.</p>
                        <a href="login.php" class="btn btn-primary" style="text-decoration: none; display: inline-block;">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Volver al inicio de sesión</a>
            </div>
        </div>
    </div>
    
    <script>
        // Validación de fortaleza de contraseña
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const btnCambiar = document.getElementById('btnCambiar');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const lengthReq = document.getElementById('lengthReq');
        const numberReq = document.getElementById('numberReq');
        const letterReq = document.getElementById('letterReq');
        const matchMsg = document.getElementById('matchMsg');
        
        function checkPasswordStrength() {
            if (!passwordInput) return;
            
            const password = passwordInput.value;
            let strength = 0;
            let checks = {
                length: password.length >= 6,
                number: /[0-9]/.test(password),
                letter: /[a-zA-Z]/.test(password)
            };
            
            // Actualizar requisitos visuales
            if (lengthReq) {
                lengthReq.innerHTML = checks.length ? 
                    '<i class="fas fa-check-circle" style="color: #27ae60;"></i> Mínimo 6 caracteres' : 
                    '<i class="fas fa-circle" style="font-size: 8px;"></i> Mínimo 6 caracteres';
                lengthReq.className = checks.length ? 'requirement valid' : 'requirement invalid';
            }
            
            if (numberReq) {
                numberReq.innerHTML = checks.number ? 
                    '<i class="fas fa-check-circle" style="color: #27ae60;"></i> Al menos un número' : 
                    '<i class="fas fa-circle" style="font-size: 8px;"></i> Al menos un número';
                numberReq.className = checks.number ? 'requirement valid' : 'requirement invalid';
            }
            
            if (letterReq) {
                letterReq.innerHTML = checks.letter ? 
                    '<i class="fas fa-check-circle" style="color: #27ae60;"></i> Al menos una letra' : 
                    '<i class="fas fa-circle" style="font-size: 8px;"></i> Al menos una letra';
                letterReq.className = checks.letter ? 'requirement valid' : 'requirement invalid';
            }
            
            // Calcular fortaleza
            if (checks.length) strength++;
            if (checks.number) strength++;
            if (checks.letter) strength++;
            
            // Actualizar barra de fortaleza
            if (strengthBar) {
                if (strength === 0) {
                    strengthBar.style.width = '0%';
                    strengthBar.className = 'strength-bar-fill';
                    if (strengthText) strengthText.innerHTML = '';
                } else if (strength === 1) {
                    strengthBar.style.width = '33%';
                    strengthBar.className = 'strength-bar-fill strength-weak';
                    if (strengthText) strengthText.innerHTML = '🔴 Débil';
                } else if (strength === 2) {
                    strengthBar.style.width = '66%';
                    strengthBar.className = 'strength-bar-fill strength-medium';
                    if (strengthText) strengthText.innerHTML = '🟡 Media';
                } else if (strength === 3) {
                    strengthBar.style.width = '100%';
                    strengthBar.className = 'strength-bar-fill strength-strong';
                    if (strengthText) strengthText.innerHTML = '🟢 Fuerte';
                }
            }
            
            return checks.length && checks.number && checks.letter;
        }
        
        function checkPasswordMatch() {
            if (!passwordInput || !confirmInput) return false;
            
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm === '') {
                if (matchMsg) matchMsg.innerHTML = '';
                return false;
            }
            
            if (password === confirm) {
                if (matchMsg) {
                    matchMsg.innerHTML = '<i class="fas fa-check-circle" style="color: #27ae60;"></i> Las contraseñas coinciden';
                    matchMsg.className = 'requirement valid';
                }
                return true;
            } else {
                if (matchMsg) {
                    matchMsg.innerHTML = '<i class="fas fa-times-circle" style="color: #e74c3c;"></i> Las contraseñas no coinciden';
                    matchMsg.className = 'requirement invalid';
                }
                return false;
            }
        }
        
        function validateForm() {
            if (!btnCambiar) return;
            
            const isStrong = checkPasswordStrength();
            const doMatch = checkPasswordMatch();
            
            btnCambiar.disabled = !(isStrong && doMatch);
        }
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength();
                validateForm();
            });
        }
        
        if (confirmInput) {
            confirmInput.addEventListener('input', function() {
                checkPasswordMatch();
                validateForm();
            });
        }
        
        // Prevenir envío duplicado
        let formSubmitting = false;
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (formSubmitting) {
                    e.preventDefault();
                    return false;
                }
                formSubmitting = true;
                
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                    btn.disabled = true;
                    
                    setTimeout(function() {
                        if (formSubmitting) {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                            formSubmitting = false;
                        }
                    }, 10000);
                }
            });
        });
    </script>
</body>
</html>