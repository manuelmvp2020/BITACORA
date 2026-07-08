<?php
// login.php - Formulario de inicio de sesión
session_start();

// Si ya está logueado, redirigir al index
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bitácora</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 600;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .info {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>📋 Bitácora</h1>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="error">
                <?php
                $error = $_GET['error'];
                if ($error == 'campos_vacios') echo '❌ Por favor, complete todos los campos';
                elseif ($error == 'password_incorrecta') echo '❌ Contraseña incorrecta';
                elseif ($error == 'usuario_no_existe') echo '❌ Usuario no encontrado o inactivo';
                else echo '❌ Error al iniciar sesión';
                ?>
            </div>
        <?php endif; ?>
        
        <!--div class="info"-->
            <!--🔐 Credenciales por defecto:<br-->
            <!--Usuario: <strong>admin</strong><br-->
            <!--Contraseña: <strong>admin123</strong-->
        <!--/div-->
        
        <form method="POST" action="procesar_login.php">
            <div class="form-group">
                <label>👤 Usuario</label>
                <input type="text" name="usuario" required autofocus>
            </div>
            <div class="form-group">
                <label>🔒 Contraseña</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Iniciar Sesión</button>
        </form>
        <div class="footer">
            Sistema de Bitácora - Control de Personal
        </div>
    </div>
</body>
</html>