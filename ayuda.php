<?php
// ayuda.php - Página de ayuda y documentación
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayuda y Documentación</title>
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
        }
        
        /* ===== SIDEBAR (mismo estilo) ===== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a2639 0%, #2c3e50 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            font-size: 22px;
            margin-bottom: 5px;
            color: #fff;
        }
        
        .sidebar-header p {
            font-size: 12px;
            opacity: 0.7;
            color: #ecf0f1;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-section {
            padding: 15px 25px 5px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
        }
        
        .menu-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            margin: 5px 0;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #3498db;
        }
        
        .menu-item.active {
            background: rgba(52,152,219,0.2);
            color: white;
            border-left-color: #3498db;
        }
        
        .menu-item i {
            width: 20px;
            font-size: 18px;
        }
        
        .sidebar-footer {
            padding: 20px;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            position: sticky;
            bottom: 0;
            background: #1a2639;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #2c3e50;
        }
        
        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .page-title i {
            color: #3498db;
            margin-right: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* ===== AYUDA STYLES ===== */
        .help-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .help-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .help-header h1 {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .help-header p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .help-header i {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .search-box {
            max-width: 600px;
            margin: 30px auto 0;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 18px;
        }
        
        .help-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .help-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .help-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .help-card-icon {
            width: 60px;
            height: 60px;
            background: #ebf5fb;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .help-card-icon i {
            font-size: 28px;
            color: #3498db;
        }
        
        .help-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .help-card p {
            color: #7f8c8d;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .help-card a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .help-card a:hover {
            color: #2980b9;
        }
        
        .faq-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .faq-section h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .faq-item {
            border-bottom: 1px solid #ecf0f1;
            margin-bottom: 15px;
        }
        
        .faq-question {
            padding: 15px 0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .faq-question i {
            transition: transform 0.3s;
        }
        
        .faq-question.active i {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            padding: 0 0 15px 0;
            color: #7f8c8d;
            line-height: 1.6;
            display: none;
        }
        
        .faq-answer.show {
            display: block;
        }
        
        .quick-tips {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .tip {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .tip i {
            font-size: 24px;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .tip kbd {
            background: #2c3e50;
            color: white;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 14px;
            margin: 5px 0;
            display: inline-block;
        }
        
        .tip p {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .contact-support {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 40px;
            border-radius: 10px;
            text-align: center;
        }
        
        .contact-support h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .contact-support p {
            opacity: 0.9;
            margin-bottom: 25px;
        }
        
        .contact-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid white;
            color: white;
        }
        
        .btn-outline:hover {
            background: white;
            color: #2c3e50;
        }
        
        .version-info {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            color: #7f8c8d;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar .menu-item span:not(.fa),
            .sidebar .menu-section,
            .sidebar-header p,
            .sidebar-footer span {
                display: none;
            }
            
            .sidebar-header h3 {
                font-size: 14px;
            }
            
            .menu-item {
                justify-content: center;
                padding: 15px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .help-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-tips {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .help-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .quick-tips {
                grid-template-columns: 1fr;
            }
            
            .help-header h1 {
                font-size: 28px;
            }
            
            .help-header p {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>📋 BITÁCORA</h3>
            <p>Sistema de Control</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">PRINCIPAL</div>
            <a href="index.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="crear.php" class="menu-item">
                <i class="fas fa-plus-circle"></i>
                <span>Nuevo Registro</span>
            </a>
            
            <div class="menu-section">GESTIÓN</div>
            <a href="importar.php" class="menu-item">
                <i class="fas fa-file-import"></i>
                <span>Importar</span>
            </a>
            <a href="exportar.php" class="menu-item">
                <i class="fas fa-file-export"></i>
                <span>Exportar</span>
            </a>
            
            <div class="menu-section">REPORTES</div>
            <a href="reportes.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Estadísticas</span>
            </a>
            <a href="imprimir.php" class="menu-item">
                <i class="fas fa-print"></i>
                <span>Imprimir</span>
            </a>
            
            <div class="menu-section">CONFIGURACIÓN</div>
            <a href="configuracion.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </a>
            <a href="ayuda.php" class="menu-item active">
                <i class="fas fa-question-circle"></i>
                <span>Ayuda</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <span>© 2024 - v1.0</span>
        </div>
    </div>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <!-- Top bar -->
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <i class="fas fa-question-circle"></i> Ayuda y Documentación
            </div>
            <div class="user-info">
                <div class="user-avatar">A</div>
            </div>
        </div>

        <div class="help-container">
            <!-- Header -->
            <div class="help-header">
                <i class="fas fa-life-ring"></i>
                <h1>¿Cómo podemos ayudarte?</h1>
                <p>Encuentra respuestas a tus preguntas y aprende a usar el sistema</p>
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Buscar en la ayuda...">
                </div>
            </div>

            <!-- Grid de ayuda -->
            <div class="help-grid">
                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h3>Primeros pasos</h3>
                    <p>Aprende los conceptos básicos y cómo empezar a usar el sistema rápidamente.</p>
                    <a href="#">Ver guía <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Gestión de registros</h3>
                    <p>Cómo crear, editar, eliminar y buscar registros en la bitácora.</p>
                    <a href="#">Ver tutorial <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Reportes y estadísticas</h3>
                    <p>Genera informes, analiza datos y visualiza estadísticas del sistema.</p>
                    <a href="#">Explorar <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h3>Importar y exportar</h3>
                    <p>Aprende a importar datos desde archivos CSV/Excel y exportar registros.</p>
                    <a href="#">Ver documentación <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3>Configuración</h3>
                    <p>Personaliza el sistema, gestiona estatus, roles y preferencias.</p>
                    <a href="#">Configurar <i class="fas fa-arrow-right"></i></a>
                </div>

                <div class="help-card">
                    <div class="help-card-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Seguridad</h3>
                    <p>Consejos de seguridad, respaldos y buenas prácticas.</p>
                    <a href="#">Leer más <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <!-- Consejos rápidos -->
            <h2 style="color: #2c3e50; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-lightbulb" style="color: #f39c12;"></i> Consejos rápidos
            </h2>
            
            <div class="quick-tips">
                <div class="tip">
                    <i class="fas fa-keyboard"></i>
                    <kbd>Ctrl + N</kbd>
                    <p>Nuevo registro</p>
                </div>
                <div class="tip">
                    <i class="fas fa-keyboard"></i>
                    <kbd>Ctrl + F</kbd>
                    <p>Buscar</p>
                </div>
                <div class="tip">
                    <i class="fas fa-keyboard"></i>
                    <kbd>Ctrl + P</kbd>
                    <p>Imprimir</p>
                </div>
                <div class="tip">
                    <i class="fas fa-keyboard"></i>
                    <kbd>Ctrl + A</kbd>
                    <p>Seleccionar todo</p>
                </div>
            </div>

            <!-- FAQ -->
            <div class="faq-section">
                <h2><i class="fas fa-question-circle"></i> Preguntas frecuentes</h2>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        ¿Cómo puedo recuperar un registro eliminado?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Los registros eliminados no se pueden recuperar automáticamente. 
                        Si tienes un respaldo de la base de datos, puedes restaurarlo desde 
                        la sección de Configuración > Respaldo. Te recomendamos hacer respaldos 
                        periódicos para evitar pérdida de información.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        ¿Qué formatos de archivo soporta el importador?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        El sistema soporta archivos CSV (separados por comas) y Excel (.xlsx, .xls). 
                        Asegúrate de que tu archivo tenga los encabezados correctos: id_registro, 
                        rango_militar, usuario, roles, estatus, designacion, fecha_registro, configuracion.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        ¿Cómo puedo cambiar los colores del sistema?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Puedes personalizar la apariencia del sistema en Configuración > Apariencia. 
                        Allí encontrarás opciones para cambiar el color primario, secundario, 
                        modo oscuro/claro y tamaño de fuente.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        ¿Con qué frecuencia se recomienda hacer respaldos?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Recomendamos hacer respaldos diarios si el sistema se usa activamente, 
                        o al menos semanalmente. Puedes configurar respaldos automáticos en 
                        Configuración > Respaldo para que se realicen sin intervención manual.
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        ¿Puedo tener múltiples usuarios en el sistema?
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        Actualmente el sistema está diseñado para un solo usuario. Sin embargo, 
                        puedes diferenciar los registros por el campo "usuario" para llevar un 
                        control de quién creó cada registro.
                    </div>
                </div>
            </div>

            <!-- Contacto y soporte -->
            <div class="contact-support">
                <h3>¿Necesitas más ayuda?</h3>
                <p>Nuestro equipo de soporte está disponible para ayudarte</p>
                
                <div class="contact-buttons">
                    <a href="#" class="btn btn-primary">
                        <i class="fas fa-envelope"></i> Enviar correo
                    </a>
                    <a href="#" class="btn btn-success">
                        <i class="fas fa-comment"></i> Chat en vivo
                    </a>
                    <a href="#" class="btn btn-outline">
                        <i class="fas fa-phone"></i> Solicitar llamada
                    </a>
                </div>
            </div>

            <!-- Versión y actualizaciones -->
            <div class="version-info">
                <p><i class="fas fa-code-branch"></i> Versión 1.0.0 - Última actualización: 15/03/2024</p>
                <p style="margin-top: 10px; font-size: 12px;">
                    Documentación completa disponible en 
                    <a href="#" style="color: #3498db; text-decoration: none;">nuestro sitio web</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar en móviles
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Cerrar sidebar al hacer clic fuera
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Función para toggle FAQ
        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            const icon = element.querySelector('i');
            
            answer.classList.toggle('show');
            element.classList.toggle('active');
            
            if (answer.classList.contains('show')) {
                icon.style.transform = 'rotate(180deg)';
            } else {
                icon.style.transform = 'rotate(0deg)';
            }
        }

        // Búsqueda simple en ayuda
        document.querySelector('.search-box input').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            
            if (searchTerm.length > 2) {
                // Simular búsqueda
                console.log('Buscando:', searchTerm);
                // Aquí iría la lógica real de búsqueda
            }
        });

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'h') {
                e.preventDefault();
                document.querySelector('.search-box input').focus();
            }
        });
    </script>
</body>
</html>