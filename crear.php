<?php
// crear.php - Formulario para crear nuevo registro
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rango_militar = $_POST['rango_militar'];
    $usuario = $_POST['usuario'];
    $roles = $_POST['roles']; // Campo de texto libre
    $estatus = $_POST['estatus'];
    $designacion = $_POST['designacion'];
    $fecha_registro = $_POST['fecha_registro'];
    $configuracion = $_POST['configuracion'];
    
    // Incluimos 'roles' en la consulta
    $sql = "INSERT INTO registros_ids (rango_militar, usuario, roles, estatus, designacion, fecha_registro, configuracion) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $rango_militar, $usuario, $roles, $estatus, $designacion, $fecha_registro, $configuracion);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Registro creado correctamente";
        $_SESSION['tipo_mensaje'] = "success";
        header("Location: index.php");
        exit();
    } else {
        $error = "Error al crear: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nuevo Registro - Bitácora IDs</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            font-size: 28px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #34495e;
            font-size: 14px;
        }
        
        .required::after {
            content: " *";
            color: #e74c3c;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            background-color: #fafafa;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
            background-color: #fff;
        }
        
        input:hover, select:hover, textarea:hover {
            border-color: #b0b0b0;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #229954, #27ae60);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #7f8c8d, #95a5a6);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
        }
        
        .btn-primary:active, .btn-secondary:active {
            transform: translateY(0);
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c0392b;
            font-weight: 500;
        }
        
        .info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Grid para dos columnas */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Sugerencias rápidas */
        .sugerencias {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        
        .sugerencia-btn {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            color: #34495e;
        }
        
        .sugerencia-btn:hover {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
            transform: translateY(-1px);
        }
        
        .sugerencia-btn i {
            margin-right: 4px;
        }
        
        /* Iconos en inputs */
        .input-icon-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #95a5a6;
        }
        
        .input-icon-wrapper input,
        .input-icon-wrapper select,
        .input-icon-wrapper textarea {
            padding-left: 35px;
        }
        
        /* Tooltips */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }
        
        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 10px;
            background-color: #2c3e50;
            color: white;
            font-size: 12px;
            border-radius: 4px;
            white-space: nowrap;
            display: none;
            z-index: 1000;
            margin-bottom: 5px;
        }
        
        [data-tooltip]:hover:before {
            display: block;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>➕ Nueva Designación de ID</h1>
        
        <?php if (isset($error)): ?>
            <div class="error">
                <strong>❌ Error:</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <span style="font-size: 20px; margin-right: 10px;">ℹ️</span>
            Los campos marcados con <span style="color: #e74c3c; font-weight: bold;">*</span> son obligatorios
        </div>
        
        <form method="POST" action="" id="formRegistro">
            <div class="form-row">
                <div class="form-group">
                    <label for="rango_militar" class="required">Rango / IP</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">🌐</span>
                        <input type="text" id="rango_militar" name="rango_militar" required 
                               placeholder="Ej: RANGO MILITAR "
                               value="<?php echo isset($_POST['rango_militar']) ? htmlspecialchars($_POST['rango_militar']) : ''; ?>">
                    </div>
                    <div class="sugerencias">
                        <span class="sugerencia-btn" onclick="document.getElementById('rango_militar').value = '192.168.1.1 - 192.168.1.50'">📡 Rango DHCP</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('rango_militar').value = '10.0.0.1/24'">🔌 Subred /24</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('rango_militar').value = '172.16.0.1 - 172.16.0.100'">🌍 Rango VPN</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="usuario" class="required">Usuario</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" id="usuario" name="usuario" required 
                               placeholder="Nombre del usuario"
                               value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>">
                    </div>
                    <div class="sugerencias">
                        <span class="sugerencia-btn" onclick="document.getElementById('usuario').value = 'admin'">👑 admin</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('usuario').value = 'supervisor'">👁️ supervisor</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('usuario').value = 'operador'">⚙️ operador</span>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="roles">Roles / Permisos</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">🔑</span>
                        <input type="text" id="roles" name="roles" 
                               placeholder="Ej: Administrador, Supervisor, Operador, etc."
                               value="<?php echo isset($_POST['roles']) ? htmlspecialchars($_POST['roles']) : ''; ?>"
                               list="roles-sugerencias">
                    </div>
                    <datalist id="roles-sugerencias">
                        <option value="Administrador">👑 Administrador</option>
                        <option value="Supervisor">👁️ Supervisor</option>
                        <option value="Operador">⚙️ Operador</option>
                        <option value="Consultor">📊 Consultor</option>
                        <option value="Usuario">👤 Usuario</option>
                        <option value="Invitado">🔑 Invitado</option>
                        <option value="Root">⚡ Root</option>
                        <option value="Desarrollador">💻 Desarrollador</option>
                        <option value="Analista">📈 Analista</option>
                        <option value="Auditor">🔍 Auditor</option>
                    </datalist>
                    <div class="sugerencias">
                        <span class="sugerencia-btn" onclick="document.getElementById('roles').value = 'Administrador'">👑 Administrador</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('roles').value = 'Supervisor'">👁️ Supervisor</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('roles').value = 'Operador'">⚙️ Operador</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('roles').value = 'Consultor'">📊 Consultor</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('roles').value = 'Usuario'">👤 Usuario</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('roles').value = 'Invitado'">🔑 Invitado</span>
                    </div>
                    <small style="color: #7f8c8d; display: block; margin-top: 8px;">
                        <span style="background-color: #f0f0f0; padding: 2px 8px; border-radius: 12px;">💡 Puedes escribir cualquier rol personalizado</span>
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="estatus" class="required">Estatus</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">⚡</span>
                        <select id="estatus" name="estatus" required>
                            <option value="" disabled <?php echo !isset($_POST['estatus']) ? 'selected' : ''; ?>>Seleccionar estatus...</option>
                            <option value="Activo" <?php echo (isset($_POST['estatus']) && $_POST['estatus'] == 'Activo') ? 'selected' : ''; ?>>🟢 Activo</option>
                            <option value="Inactivo" <?php echo (isset($_POST['estatus']) && $_POST['estatus'] == 'Inactivo') ? 'selected' : ''; ?>>🔴 Inactivo</option>
                            <option value="Pendiente" <?php echo (isset($_POST['estatus']) && $_POST['estatus'] == 'Pendiente') ? 'selected' : ''; ?>>🟡 Pendiente</option>
                            <option value="Bloqueado" <?php echo (isset($_POST['estatus']) && $_POST['estatus'] == 'Bloqueado') ? 'selected' : ''; ?>>⚫ Bloqueado</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="designacion" class="required">Designación / Control</label>
                <div class="input-icon-wrapper">
                    <span class="input-icon" style="top: 15px;">📝</span>
                    <textarea id="designacion" name="designacion" required 
                              placeholder="Ej: DHCP - Rango Principal, Reserva para Servidor, etc."><?php echo isset($_POST['designacion']) ? htmlspecialchars($_POST['designacion']) : ''; ?></textarea>
                </div>
                <div class="sugerencias">
                    <span class="sugerencia-btn" onclick="document.getElementById('designacion').value = 'DHCP - Rango Principal para clientes'">📡 DHCP Principal</span>
                    <span class="sugerencia-btn" onclick="document.getElementById('designacion').value = 'Reserva para Servidor Web'">🌐 Reserva Servidor</span>
                    <span class="sugerencia-btn" onclick="document.getElementById('designacion').value = 'VPN - Acceso Remoto'">🔒 VPN</span>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="fecha_registro" class="required">Fecha</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">📅</span>
                        <input type="date" id="fecha_registro" name="fecha_registro" 
                               value="<?php echo isset($_POST['fecha_registro']) ? $_POST['fecha_registro'] : date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="configuracion">Configuración</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon" style="top: 15px;">⚙️</span>
                        <textarea id="configuracion" name="configuracion" 
                                  placeholder="Ej: VLAN 10, MAC: AA:BB:CC, Gateway, etc."><?php echo isset($_POST['configuracion']) ? htmlspecialchars($_POST['configuracion']) : ''; ?></textarea>
                    </div>
                    <div class="sugerencias">
                        <span class="sugerencia-btn" onclick="document.getElementById('configuracion').value = 'VLAN 10, Gateway: 192.168.1.1'">🌐 VLAN 10</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('configuracion').value = 'MAC: AA:BB:CC:DD:EE:FF'">📱 MAC Address</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('configuracion').value = 'DNS: 8.8.8.8, 8.8.4.4'">🔍 DNS</span>
                    </div>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary" id="btnGuardar">
                    <span>💾</span> Guardar Registro
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <span>↩️</span> Cancelar
                </a>
            </div>
        </form>
    </div>

    <script>
        // Validación del formulario
        document.getElementById('formRegistro').addEventListener('submit', function(e) {
            let btn = document.getElementById('btnGuardar');
            btn.innerHTML = '<span>⏳</span> Guardando...';
            btn.disabled = true;
            
            // Validar que los campos requeridos no estén vacíos
            let requeridos = document.querySelectorAll('[required]');
            let valido = true;
            
            requeridos.forEach(campo => {
                if (!campo.value.trim()) {
                    campo.style.borderColor = '#e74c3c';
                    valido = false;
                } else {
                    campo.style.borderColor = '';
                }
            });
            
            if (!valido) {
                e.preventDefault();
                alert('❌ Por favor completa todos los campos obligatorios');
                btn.innerHTML = '<span>💾</span> Guardar Registro';
                btn.disabled = false;
            }
        });

        // Auto-mayúsculas para roles (opcional)
        document.getElementById('roles').addEventListener('blur', function() {
            if (this.value) {
                // Capitalizar primera letra
                this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
            }
        });

        // Mostrar caracteres restantes para textareas largos
        function setupCharacterCounter(textareaId, maxLength = 500) {
            let textarea = document.getElementById(textareaId);
            if (textarea) {
                textarea.addEventListener('input', function() {
                    let remaining = maxLength - this.value.length;
                    if (remaining < 50) {
                        // Podríamos mostrar un contador si quisieramos
                    }
                });
            }
        }

        setupCharacterCounter('designacion');
        setupCharacterCounter('configuracion');

        // Confirmación antes de salir si hay cambios
        let formChanged = false;
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('change', () => formChanged = true);
            element.addEventListener('keyup', () => formChanged = true);
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'Tienes cambios sin guardar. ¿Estás seguro de que quieres salir?';
            }
        });

        document.getElementById('formRegistro').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>