<?php

/**
 * Plugin Name: Gestión de Citas
 * Description: Plugin para gestionar la disponibilidad de citas y asignarlas a usuarios.
 * Version: 1.0
 * Author: Sergio
 */



if (!defined('ABSPATH')) exit;

/************************************************ menú de administración ***********************************************************************/

function agregar_menu_principal()
{
    add_menu_page(
        'Gestión de Citas', // Título del menú
        'Citas', // Nombre en el menú principal
        'manage_options',
        'gestion-citas',
        'mostrar_configuracion_disponibilidad', // Página para configurar la disponibilidad
        'dashicons-calendar-alt', // Icono del menú
        25
    );

    add_submenu_page(
        'gestion-citas',
        'Configurar Disponibilidad',
        'Configurar Disponibilidad',
        'manage_options',
        'configuracion-disponibilidad',
        'mostrar_configuracion_disponibilidad'
    );

    add_submenu_page(
        'gestion-citas',
        'Citas Disponibles', // Título del submenú
        'Citas Disponibles', // Nombre en el submenú
        'manage_options',
        'gestion-citas-lista', // El identificador de la página
        'mostrar_citas_disponibles' // Función para mostrar el contenido de la página
    );
}
add_action('admin_menu', 'agregar_menu_principal');

/************************************************ sección disponibilidad ***********************************************************************/

function dividir_horas_en_franjas($hora_inicio, $hora_fin, $duracion_minutos) {
    $inicio = strtotime($hora_inicio);
    $fin = strtotime($hora_fin);
    $duracion = $duracion_minutos * 60;

    $franjas = [];
    while ($inicio + $duracion <= $fin) {
        $franjas[] = [
            'desde' => date('H:i', $inicio),
            'hasta' => date('H:i', $inicio + $duracion),
            'usuarios' => null,  
            'observaciones' => null  
        ];
        $inicio += $duracion; 
    }

    return $franjas;
}

function mostrar_configuracion_disponibilidad() {
    echo '<style>
   .wrap h1 {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
}

h3 {
    font-size: 18px;
    margin-top: 20px;
    margin-bottom: 10px;
}

select, input[type="time"], input[type="number"], button {
    margin-top: 5px;
    margin-bottom: 15px;
}

/* Estilos del formulario */
form {
    background-color: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

/* Estilos de los días */
.dias-container label {
    display: inline-block;
    margin-right: 15px;
    margin-bottom: 10px;
}

.dias-container input {
    margin-right: 5px;
}

/* Estilos de los horarios */
#horarios-container {
    margin-bottom: 20px;
}

.horario {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.horario label {
    margin-right: 10px;
}

.horario input[type="time"] {
    margin-right: 10px;
    width: 120px;
}

button {
    cursor: pointer;
    padding: 8px 15px;
    background-color: #0073aa;
    color: #fff;
    border: none;
    border-radius: 3px;
}

button:hover {
    background-color: #005a8d;
}

button.remove-horario {
    background-color: #e74c3c;
    margin-left: 10px;
}

button.remove-horario:hover {
    background-color: #c0392b;
}

/* Estilo del contenedor de selección de meses */
select {
    width: 200px;
    padding: 8px;
    font-size: 14px;
    margin-right: 10px;
}

/* Botón de guardar */
.button-primary {
    background-color: #0073aa;
    color: white;
    padding: 10px 20px;
    font-size: 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.button-primary:hover {
    background-color: #005a8d;
}

/* Mensajes de éxito */
.updated p {
    color: #28a745;
    font-weight: bold;
}

.error p {
    color: #dc3545;
    font-weight: bold;
}
</style>';
    if (isset($_POST['guardar_disponibilidad']) && check_admin_referer('guardar_disponibilidad', 'nonce_guardar_disponibilidad')) {
        $configuracion = isset($_POST['disponibilidad']) ? $_POST['disponibilidad'] : [];
        $mes = isset($_POST['disponibilidad']['mes']) ? $_POST['disponibilidad']['mes'] : '';
        $configuracion_guardada = get_option('configuracion_disponibilidad', []);
        if (isset($configuracion['duracion']['minutos'])) {
            $duracion_minutos = $configuracion['duracion']['minutos'];
            if (isset($configuracion['horarios'])) {
                foreach ($configuracion['horarios'] as $index => $horario) {
                    $horarios_fraccionados = dividir_horas_en_franjas($horario['desde'], $horario['hasta'], $duracion_minutos);
                    $configuracion['horarios'][$index] = $horarios_fraccionados;
                }
            }
        }
        if ($mes && isset($configuracion_guardada[$mes])) {
            foreach ($configuracion['dias'] as $dia) {
                if (!isset($configuracion_guardada[$mes][$dia])) {
                    $configuracion_guardada[$mes][$dia] = [
                        'dias' => [$dia],
                        'horarios' => isset($configuracion['horarios']) ? $configuracion['horarios'] : [],
                        'duracion' => isset($configuracion['duracion']) ? $configuracion['duracion'] : []
                    ];
                } else {
                    $configuracion_guardada[$mes][$dia]['horarios'] = array_merge($configuracion_guardada[$mes][$dia]['horarios'], $configuracion['horarios']);
                }
            }
        } else {
            foreach ($configuracion['dias'] as $dia) {
                $configuracion_guardada[$mes][$dia] = [
                    'dias' => [$dia],
                    'horarios' => isset($configuracion['horarios']) ? $configuracion['horarios'] : [],
                    'duracion' => isset($configuracion['duracion']) ? $configuracion['duracion'] : []
                ];
            }
        }

        update_option('configuracion_disponibilidad', $configuracion_guardada);
        echo '<div class="updated"><p>Disponibilidad guardada con éxito para el mes de ' . $mes . '.</p></div>';
    }
    $configuracion_guardada = get_option('configuracion_disponibilidad', []);
    $mes_seleccionado = isset($_POST['disponibilidad']['mes']) ? $_POST['disponibilidad']['mes'] : (isset($_GET['mes']) ? $_GET['mes'] : null);
    $configuracion = ($mes_seleccionado && isset($configuracion_guardada[$mes_seleccionado]))
        ? $configuracion_guardada[$mes_seleccionado]
        : [];

    echo '<div class="wrap">';
    echo '<h1>Configurar Disponibilidad</h1>';
    echo '<form method="POST">';
    wp_nonce_field('guardar_disponibilidad', 'nonce_guardar_disponibilidad');
    echo '<h3>Selecciona Mes</h3>';
    echo '<select name="disponibilidad[mes]" onchange="this.form.submit()">';
    $meses = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
    foreach ($meses as $mes) {
        $selected = ($mes_seleccionado === $mes) ? 'selected' : '';
        echo "<option value='$mes' $selected>$mes</option>";
    }
    echo '</select>';
    echo '<h3>Selecciona Días</h3>';
    echo '<div class="dias-container">';
    foreach (range(1, 31) as $dia) {
        $checked = (isset($configuracion['dias']) && in_array($dia, $configuracion['dias'])) ? 'checked' : '';
        echo "<label><input type='checkbox' name='disponibilidad[dias][]' value='$dia' $checked /> $dia</label>";
    }
    echo '</div>';
    echo '<h3>Selecciona Horarios</h3>';
    echo '<div id="horarios-container">';
    if (isset($configuracion['horarios']) && is_array($configuracion['horarios'])) {
        foreach ($configuracion['horarios'] as $index => $horario) {
            $desde = $horario['desde'];
            $hasta = $horario['hasta'];
            echo "<div class='horario' data-index='$index'>
                    <label>Desde: <input type='time' name='disponibilidad[horarios][$index][desde]' value='$desde' /></label>
                    <label>Hasta: <input type='time' name='disponibilidad[horarios][$index][hasta]' value='$hasta' /></label>
                    <button type='button' class='remove-horario'>Eliminar Horario</button>
                </div>";
        }
    } else {
        echo "<div class='horario' data-index='0'>
                <label>Desde: <input type='time' name='disponibilidad[horarios][0][desde]' /></label>
                <label>Hasta: <input type='time' name='disponibilidad[horarios][0][hasta]' /></label>
                <button type='button' class='remove-horario'>Eliminar Horario</button>
            </div>";
    }
    echo '</div>';
    echo '<button type="button" id="add-horario">Añadir Horario</button>';
    echo '<h3>Duración de la Cita</h3>';
    echo '<label>Minutos: <input type="number" name="disponibilidad[duracion][minutos]" value="' . (isset($configuracion['duracion']['minutos']) ? $configuracion['duracion']['minutos'] : 30) . '" min="1" /></label>';
    echo '<br><br>';
    echo '<button type="submit" name="guardar_disponibilidad" class="button-primary">Guardar Disponibilidad</button>';
    echo '</form>';
    echo '<script>
        document.getElementById("add-horario").addEventListener("click", function() {
            const container = document.getElementById("horarios-container");
            const index = container.querySelectorAll(".horario").length;
            const nuevoHorario = document.createElement("div");
            nuevoHorario.classList.add("horario");
            nuevoHorario.setAttribute("data-index", index);
            nuevoHorario.innerHTML = `
                <label>Desde: <input type="time" name="disponibilidad[horarios][${index}][desde]" /></label>
                <label>Hasta: <input type="time" name="disponibilidad[horarios][${index}][hasta]" /></label>
                <button type="button" class="remove-horario">Eliminar Horario</button>
            `;
            container.appendChild(nuevoHorario);
        });
        document.addEventListener("click", function(e) {
            if (e.target && e.target.classList.contains("remove-horario")) {
                const horarioDiv = e.target.closest(".horario");
                horarioDiv.remove();
            }
        });
    </script>';
}

/************************************************ sección disponibilidad ***********************************************************************/

function mostrar_citas_disponibles() {
    // Obtener la configuración guardada desde la base de datos
    $configuracion_guardada = get_option('configuracion_disponibilidad', []);
    
    echo '<div class="wrap">';
    echo '<h1>Datos de Disponibilidad</h1>';

    // Verificar si hay datos
    if (empty($configuracion_guardada)) {
        echo '<p>No hay disponibilidad configurada.</p>';
        echo '</div>';
        return;
    }

    // Si se ha enviado un formulario para actualizar las observaciones o el usuario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_datos'])) {
        // Obtener los datos enviados
        $mes = $_POST['mes'];
        $dia = $_POST['dia'];

        // Verificar si la configuración para el mes y día existe
        if (isset($configuracion_guardada[$mes][$dia])) {
            foreach ($configuracion_guardada[$mes][$dia]['horarios'] as &$grupo_horarios) {
                foreach ($grupo_horarios as &$franja) {
                    // Actualizar observación si se envió
                    if (isset($_POST['observacion_' . $franja['desde'] . '_' . $franja['hasta']])) {
                        $franja['observaciones'] = sanitize_text_field($_POST['observacion_' . $franja['desde'] . '_' . $franja['hasta']]);
                    }

                    // Actualizar usuario si se seleccionó
                    if (isset($_POST['usuario_' . $franja['desde'] . '_' . $franja['hasta']])) {
                        $franja['usuarios'] = sanitize_text_field($_POST['usuario_' . $franja['desde'] . '_' . $franja['hasta']]);
                    }
                }
            }

            // Guardar la configuración en la base de datos
            update_option('configuracion_disponibilidad', $configuracion_guardada);

            // Recargar la página para reflejar los datos actualizados
            echo '<script>location.reload();</script>';
            exit;
        }
    }

    // Lógica para eliminar un día completo
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_dia'])) {
        $mes = sanitize_text_field($_POST['mes']);
        $dia = sanitize_text_field($_POST['dia']);

        // Verificar si el día existe
        if (isset($configuracion_guardada[$mes][$dia])) {
            // Eliminar el día completo
            unset($configuracion_guardada[$mes][$dia]);

            // Guardar la configuración actualizada
            update_option('configuracion_disponibilidad', $configuracion_guardada);

            // Recargar la página para reflejar los cambios
            echo '<script>location.reload();</script>';
            exit;
        } else {
            echo '<p style="color: red;">El día no existe o ya ha sido eliminado.</p>';
        }
    }

    // Obtener la lista de usuarios de WordPress
    $usuarios_wp = get_users();

    // Mostrar los datos de disponibilidad
    foreach ($configuracion_guardada as $mes => $dias) {
        // Solo mostrar el mes si tiene días disponibles
        if (empty($dias)) {
            continue;  // Si no hay días, no mostramos el mes
        }

        echo "<h2>$mes</h2>";

        foreach ($dias as $dia => $config) {
            echo "<h3>Día $dia</h3>";

            // Formulario para eliminar el día completo
            echo '<form method="post" onsubmit="return confirm(\'¿Estás seguro de que deseas eliminar este día?\')">';
            echo '<input type="hidden" name="mes" value="' . esc_attr($mes) . '" />';
            echo '<input type="hidden" name="dia" value="' . esc_attr($dia) . '" />';
            echo '<button type="submit" name="eliminar_dia" class="button button-danger">Eliminar Día</button>';
            echo '</form>';

            // Formulario para actualizar observaciones y usuario
            echo '<form method="post">';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Desde</th>';
            echo '<th>Hasta</th>';
            echo '<th>Usuario</th>';
            echo '<th>Observaciones</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            // Recorrer las franjas horarias
            if (isset($config['horarios']) && is_array($config['horarios'])) {
                foreach ($config['horarios'] as $grupo_horarios) {
                    if (is_array($grupo_horarios)) {
                        foreach ($grupo_horarios as $franja) {
                            echo '<tr>';
                            echo "<td>{$franja['desde']}</td>";
                            echo "<td>{$franja['hasta']}</td>";

                            // Desplegable para seleccionar usuario
                            echo '<td>';
                            echo '<select name="usuario_' . $franja['desde'] . '_' . $franja['hasta'] . '">';
                            echo '<option value="">N/A</option>'; // Opción predeterminada para "Sin asignar"
                            
                            // Verificar si hay un usuario asignado a esta franja
                            $usuario_asignado = isset($franja['usuarios']) ? $franja['usuarios'] : '';
                            
                            // Recorrer los usuarios registrados en WordPress
                            foreach ($usuarios_wp as $usuario) {
                                // Si el ID del usuario coincide con el valor almacenado en la franja, lo marcamos como seleccionado
                                $selected = ($usuario_asignado == $usuario->ID) ? 'selected' : '';
                                echo '<option value="' . esc_attr($usuario->ID) . '" ' . $selected . '>' . esc_html($usuario->display_name) . '</option>';
                            }
                            echo '</select>';
                            echo '</td>';

                            // Campo para observaciones
                            echo '<td>';
                            echo '<input type="text" name="observacion_' . $franja['desde'] . '_' . $franja['hasta'] . '" value="' . esc_attr($franja['observaciones']) . '" />';
                            echo '<input type="hidden" name="mes" value="' . esc_attr($mes) . '" />';
                            echo '<input type="hidden" name="dia" value="' . esc_attr($dia) . '" />';
                            echo '</td>';
                            echo '</tr>';
                        }
                    }
                }
            }

            echo '</tbody>';
            echo '</table>';
            echo '<button type="submit" name="actualizar_datos" class="button">Guardar Cambios</button>';
            echo '</form>';
        }
    }

    echo '</div>';
}

/************************************************ sección disponibilidad ***********************************************************************/

// Función para registrar el endpoint
function registrar_endpoint_citas_sin_usuario() {
    register_rest_route('citas/v1', '/disponibles/', array(
        'methods' => 'GET',  // Usamos el método GET
        'callback' => 'obtener_citas_sin_usuario',  // Llamamos a la función que obtendrá las citas
        'permission_callback' => '__return_true',  // Permitimos acceso sin restricciones para prueba
    ));
}
add_action('rest_api_init', 'registrar_endpoint_citas_sin_usuario');

// Función que se encarga de obtener las citas sin usuario asignado
function obtener_citas_sin_usuario(WP_REST_Request $request) {
    // Obtener la configuración guardada desde la base de datos
    $configuracion_guardada = get_option('configuracion_disponibilidad', []);
    
    // Preparar el array para almacenar las citas sin usuario
    $citas_sin_usuario = [];
    
    // Recorremos la configuración de disponibilidad
    foreach ($configuracion_guardada as $mes => $dias) {
        foreach ($dias as $dia => $config) {
            // Verificar si hay horarios y recorrerlos
            if (isset($config['horarios']) && is_array($config['horarios'])) {
                foreach ($config['horarios'] as $grupo_horarios) {
                    if (is_array($grupo_horarios)) {
                        foreach ($grupo_horarios as $franja) {
                            // Comprobamos si no hay usuario asignado
                            if (empty($franja['usuarios'])) {
                                // Si no hay usuario, almacenamos la cita
                                $citas_sin_usuario[] = [
                                    'mes' => $mes,
                                    'dia' => $dia,
                                    'desde' => $franja['desde'],
                                    'hasta' => $franja['hasta'],
                                    'observaciones' => $franja['observaciones'] ?? '',
                                ];
                            }
                        }
                    }
                }
            }
        }
    }

    // Retornar la respuesta en formato JSON
    return rest_ensure_response($citas_sin_usuario);
}

function registrar_endpoint_asignar_usuario_json() {
    register_rest_route('citas/v1', '/asignar/', [
        'methods' => 'POST',
        'callback' => 'asignar_usuario_a_cita_json',
        'permission_callback' => '__return_true',  
        'args' => [
            'mes' => [
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_string($param);
                }
            ],
            'dia' => [
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ],
            'desde' => [
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_string($param);
                }
            ],
            'hasta' => [
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_string($param);
                }
            ],
            'usuario_id' => [
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);
}
add_action('rest_api_init', 'registrar_endpoint_asignar_usuario_json');

function asignar_usuario_a_cita_json( $data ) {
    // Obtener los datos del JSON
    $mes = sanitize_text_field($data['mes']);
    $dia = intval($data['dia']);
    $desde = sanitize_text_field($data['desde']);
    $hasta = sanitize_text_field($data['hasta']);
    $usuario_id = intval($data['usuario_id']);

    // Obtener la configuración de disponibilidad guardada
    $configuracion_guardada = get_option('configuracion_disponibilidad', []);

    // Verificar si los datos existen para el mes y el día
    if (isset($configuracion_guardada[$mes][$dia])) {
        // Buscar la franja horaria específica
        foreach ($configuracion_guardada[$mes][$dia]['horarios'] as &$grupo_horarios) {
            foreach ($grupo_horarios as &$franja) {
                if ($franja['desde'] == $desde && $franja['hasta'] == $hasta && (empty($franja['usuarios']) || is_null($franja['usuarios']))) {
                    // Asignar el usuario a la franja
                    $franja['usuarios'] = $usuario_id;

                    // Guardar la configuración actualizada
                    update_option('configuracion_disponibilidad', $configuracion_guardada);

                    // Responder con éxito
                    return new WP_REST_Response('Usuario asignado correctamente', 200);
                }
            }
        }
    }

    // Si no se encuentra la cita o ya tiene usuario asignado, devolver error
    return new WP_REST_Response('Cita no encontrada o ya tiene usuario asignado', 400);
}
