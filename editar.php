<?php
// editar.php - Formulario para editar registro existente
require_once 'config.php';

// ==============================================
// DATOS DEL USUARIO Y IP
// ==============================================
$usuario_actual = $_SESSION['usuario'] ?? 'Sistema';
$ip_address = obtenerIP();

// ==============================================
// OBTENER ID DEL REGISTRO
// ==============================================
$id = $_GET['id'] ?? 0;

if ($id <= 0) {
    registrarLog($conn, $usuario_actual, "INTENTO DE EDICIÓN - ID inválido: $id", "editar", $ip_address);
    $_SESSION['mensaje'] = "ID de registro inválido";
    $_SESSION['tipo_mensaje'] = "error";
    header("Location: index.php");
    exit();
}

// ==============================================
// OBTENER DATOS DEL REGISTRO
// ==============================================
$sql = "SELECT * FROM registros_ids WHERE id_registro = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    registrarLog($conn, $usuario_actual, "INTENTO DE EDICIÓN - Registro no encontrado ID: $id", "editar", $ip_address);
    $_SESSION['mensaje'] = "Registro no encontrado";
    $_SESSION['tipo_mensaje'] = "error";
    header("Location: index.php");
    exit();
}

$registro = $resultado->fetch_assoc();

// ==============================================
// REGISTRAR ACCESO AL FORMULARIO (una vez por sesión por ID)
// ==============================================
$clave_acceso = "log_acceso_editar_" . $id;
if (!isset($_SESSION[$clave_acceso])) {
    registrarLog($conn, $usuario_actual, "Acceso al formulario de edición para registro ID: $id", "editar", $ip_address);
    $_SESSION[$clave_acceso] = true;
}

// ==============================================
// PROCESAR ACTUALIZACIÓN
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rango_militar = $_POST['rango_militar'];
    $usuario = $_POST['usuario'];
    $roles = $_POST['roles'];
    $estatus = $_POST['estatus'];
    $designacion = $_POST['designacion'];
    $fecha_registro = $_POST['fecha_registro'];
    $configuracion = $_POST['configuracion'];
    
    // Guardar valores anteriores para comparar
    $valores_anteriores = [
        'rango_militar' => $registro['rango_militar'],
        'usuario' => $registro['usuario'],
        'roles' => $registro['roles'] ?? '',
        'estatus' => $registro['estatus'],
        'designacion' => $registro['designacion'],
        'fecha_registro' => $registro['fecha_registro'],
        'configuracion' => $registro['configuracion'] ?? ''
    ];
    
    $sql = "UPDATE registros_ids SET 
            rango_militar = ?, 
            usuario = ?, 
            roles = ?,
            estatus = ?, 
            designacion = ?, 
            fecha_registro = ?, 
            configuracion = ? 
            WHERE id_registro = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssi", $rango_militar, $usuario, $roles, $estatus, $designacion, $fecha_registro, $configuracion, $id);
    
    if ($stmt->execute()) {
        // Detectar cambios
        $cambios = [];
        if ($valores_anteriores['rango_militar'] != $rango_militar)
            $cambios[] = "Rango: '{$valores_anteriores['rango_militar']}' → '$rango_militar'";
        if ($valores_anteriores['usuario'] != $usuario)
            $cambios[] = "Usuario: '{$valores_anteriores['usuario']}' → '$usuario'";
        if ($valores_anteriores['roles'] != $roles)
            $cambios[] = "Roles: '{$valores_anteriores['roles']}' → '$roles'";
        if ($valores_anteriores['estatus'] != $estatus)
            $cambios[] = "Estatus: '{$valores_anteriores['estatus']}' → '$estatus'";
        if ($valores_anteriores['designacion'] != $designacion)
            $cambios[] = "Designación: '{$valores_anteriores['designacion']}' → '$designacion'";
        if ($valores_anteriores['fecha_registro'] != $fecha_registro)
            $cambios[] = "Fecha: '{$valores_anteriores['fecha_registro']}' → '$fecha_registro'";
        if ($valores_anteriores['configuracion'] != $configuracion)
            $cambios[] = "Configuración: '{$valores_anteriores['configuracion']}' → '$configuracion'";
        
        $descripcion_cambios = empty($cambios) ? "Sin cambios" : implode("; ", $cambios);
        $log_mensaje = "ACTUALIZACIÓN EXITOSA - ID: $id - Usuario: $usuario - Cambios: $descripcion_cambios";
        registrarLog($conn, $usuario_actual, $log_mensaje, "editar", $ip_address);
        
        $_SESSION['mensaje'] = "Registro actualizado correctamente";
        $_SESSION['tipo_mensaje'] = "success";
        header("Location: index.php");
        exit();
    } else {
        $error = "Error al actualizar: " . $conn->error;
        registrarLog($conn, $usuario_actual, "ERROR AL EDITAR - ID: $id - " . $conn->error, "editar", $ip_address);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Registro - Bitácora IDs</title>
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
            border-bottom: 2px solid #f39c12;
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
            border-color: #f39c12;
            box-shadow: 0 0 0 3px rgba(243,156,18,0.1);
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
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #e67e22, #f39c12);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(243, 156, 18, 0.4);
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
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }
        
        .btn-primary:active, .btn-secondary:active, .btn-danger:active {
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
        
        .card-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #f39c12;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .card-info p {
            margin: 8px 0;
            color: #2c3e50;
            font-size: 15px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
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
            background-color: #f39c12;
            color: white;
            border-color: #f39c12;
            transform: translateY(-1px);
        }
        
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
            z-index: 1;
        }
        
        .input-icon-wrapper input,
        .input-icon-wrapper select,
        .input-icon-wrapper textarea {
            padding-left: 40px;
        }
        
        .input-icon-wrapper textarea + .input-icon {
            top: 15px;
            transform: none;
        }
        
        .estatus-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
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
        <h1>✏️ Editar Registro #<?php echo $id; ?></h1>
        
        <?php if (isset($error)): ?>
            <div class="error">
                <strong>❌ Error:</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <span style="font-size: 20px; margin-right: 10px;">ℹ️</span>
            Estás editando el registro <strong>#<?php echo $id; ?></strong> - Creado: 
            <?php 
            $fecha_creacion_raw = $registro['created_at'] ?? $registro['fecha_registro'] ?? '';
            if (!empty($fecha_creacion_raw) && $fecha_creacion_raw != '0000-00-00') {
                $timestamp = strtotime($fecha_creacion_raw);
                $formato = 'd/m/Y';
                if (date('H:i:s', $timestamp) != '00:00:00') {
                    $formato = 'd/m/Y H:i';
                }
                echo date($formato, $timestamp);
            } else {
                echo 'No disponible';
            }
            ?>
        </div>
        
        <div class="card-info">
            <p><strong>📋 Información actual:</strong></p>
            <p>• <strong>Rango:</strong> <?php echo htmlspecialchars($registro['rango_militar']); ?></p>
            <p>• <strong>Usuario:</strong> <?php echo htmlspecialchars($registro['usuario']); ?></p>
            <p>• <strong>Roles:</strong> 
                <?php if (!empty($registro['roles'])): ?>
                    <span style="background-color: #3498db; color: white; padding: 3px 10px; border-radius: 12px; font-size: 12px;">
                        <?php echo htmlspecialchars($registro['roles']); ?>
                    </span>
                <?php else: ?>
                    <span style="color: #999;">No asignado</span>
                <?php endif; ?>
            </p>
            <p>• <strong>Estatus:</strong> 
                <span class="estatus-badge" style="background-color: <?php 
                    echo $registro['estatus'] == 'Activo' ? '#d4edda' : 
                        ($registro['estatus'] == 'Inactivo' ? '#f8d7da' : 
                        ($registro['estatus'] == 'Pendiente' ? '#fff3cd' : '#e2e3e5')); 
                ?>; color: <?php 
                    echo $registro['estatus'] == 'Activo' ? '#155724' : 
                        ($registro['estatus'] == 'Inactivo' ? '#721c24' : 
                        ($registro['estatus'] == 'Pendiente' ? '#856404' : '#383d41')); 
                ?>;">
                    <?php echo $registro['estatus']; ?>
                </span>
            </p>
        </div>
        
        <form method="POST" action="" id="formEditar">
            <div class="form-row">
                <div class="form-group">
                    <label for="rango_militar" class="required">Rango / IP</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">🌐</span>
                        <input type="text" id="rango_militar" name="rango_militar" required 
                               value="<?php echo htmlspecialchars($registro['rango_militar']); ?>"
                               placeholder="Ej: 192.168.1.1 - 192.168.1.50">
                    </div>
                    <div class="sugerencias">
                        <span class="sugerencia-btn" onclick="document.getElementById('rango_militar').value = '192.168.1.1 - 192.168.1.50'">📡 Rango DHCP</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('rango_militar').value = '10.0.0.1/24'">🔌 Subred /24</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="usuario" class="required">Usuario</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" id="usuario" name="usuario" required 
                               value="<?php echo htmlspecialchars($registro['usuario']); ?>"
                               placeholder="Nombre del usuario">
                    </div>
                    <div class="sugerencias">
                        <span class="sugerencia-btn" onclick="document.getElementById('usuario').value = 'admin'">👑 admin</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('usuario').value = 'supervisor'">👁️ supervisor</span>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="roles">Roles / Permisos</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">🔑</span>
                        <input type="text" id="roles" name="roles" 
                               value="<?php echo htmlspecialchars($registro['roles'] ?? ''); ?>"
                               placeholder="Ej: Administrador, Supervisor, Operador, etc."
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
                    </div>
                    <small style="color: #7f8c8d; display: block; margin-top: 8px;">
                        💡 Puedes escribir cualquier rol personalizado
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="estatus" class="required">Estatus</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon">⚡</span>
                        <select id="estatus" name="estatus" required>
                            <option value="Activo" <?php echo $registro['estatus'] == 'Activo' ? 'selected' : ''; ?>>🟢 Activo</option>
                            <option value="Inactivo" <?php echo $registro['estatus'] == 'Inactivo' ? 'selected' : ''; ?>>🔴 Inactivo</option>
                            <option value="Pendiente" <?php echo $registro['estatus'] == 'Pendiente' ? 'selected' : ''; ?>>🟡 Pendiente</option>
                            <option value="Bloqueado" <?php echo $registro['estatus'] == 'Bloqueado' ? 'selected' : ''; ?>>⚫ Bloqueado</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="designacion" class="required">Designación / Control</label>
                <div class="input-icon-wrapper">
                    <span class="input-icon" style="top: 15px;">📝</span>
                    <textarea id="designacion" name="designacion" required 
                              placeholder="Ej: DHCP - Rango Principal, Reserva para Servidor, etc."><?php echo htmlspecialchars($registro['designacion']); ?></textarea>
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
                               value="<?php echo $registro['fecha_registro']; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="configuracion">Configuración</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon" style="top: 15px;">⚙️</span>
                        <textarea id="configuracion" name="configuracion" 
                                  placeholder="Ej: VLAN 10, MAC: AA:BB:CC, Gateway, etc."><?php echo htmlspecialchars($registro['configuracion']); ?></textarea>
                    </div>
                    <div class="sugerencias">
                        <span class="sugerencia-btn" onclick="document.getElementById('configuracion').value = 'VLAN 10, Gateway: 192.168.1.1'">🌐 VLAN 10</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('configuracion').value = 'MAC: AA:BB:CC:DD:EE:FF'">📱 MAC Address</span>
                        <span class="sugerencia-btn" onclick="document.getElementById('configuracion').value = 'DNS: 8.8.8.8, 8.8.4.4'">🔍 DNS</span>
                    </div>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary" id="btnActualizar">
                    <span>💾</span> Actualizar Registro
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <span>↩️</span> Cancelar
                </a>
                <a href="index.php?eliminar=<?php echo $id; ?>" 
                   class="btn btn-danger" 
                   onclick="return confirm('¿Estás seguro de eliminar este registro?\nID: <?php echo $id; ?>\nRango: <?php echo addslashes($registro['rango_militar']); ?>')">
                   <span>🗑️</span> Eliminar
                </a>
            </div>
        </form>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ecf0f1; font-size: 12px; color: #7f8c8d;">
            <p><strong>📊 Información del sistema:</strong></p>
            <p>• Creado: 
                <?php 
                if (isset($registro['created_at']) && !empty($registro['created_at']) && $registro['created_at'] != '0000-00-00') {
                    $timestamp = strtotime($registro['created_at']);
                    $formato = 'd/m/Y';
                    if (date('H:i:s', $timestamp) != '00:00:00') {
                        $formato = 'd/m/Y H:i:s';
                    }
                    echo date($formato, $timestamp);
                } else {
                    echo 'No disponible';
                }
                ?>
            </p>
            <p>• Última actualización: 
                <?php 
                if (isset($registro['updated_at']) && !empty($registro['updated_at']) && $registro['updated_at'] != '0000-00-00') {
                    $timestamp = strtotime($registro['updated_at']);
                    $formato = 'd/m/Y';
                    if (date('H:i:s', $timestamp) != '00:00:00') {
                        $formato = 'd/m/Y H:i:s';
                    }
                    echo date($formato, $timestamp);
                } else {
                    echo 'No disponible';
                }
                ?>
            </p>
            <p>• IP de edición: <?php echo $ip_address; ?></p>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            let btn = document.getElementById('btnActualizar');
            btn.innerHTML = '<span>⏳</span> Actualizando...';
            btn.disabled = true;
            
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
                btn.innerHTML = '<span>💾</span> Actualizar Registro';
                btn.disabled = false;
            }
        });

        // Auto-mayúsculas para roles
        document.getElementById('roles').addEventListener('blur', function() {
            if (this.value) {
                this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1);
            }
        });

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

        document.getElementById('formEditar').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>