<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'block_student_engagement', language 'es'.
 *
 * @package    block_student_engagement
 * @copyright  2026 Bastian Coquedano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Participación estudiantil';
$string['student_engagement:addinstance'] = 'Agregar un nuevo bloque de Participación estudiantil';
$string['student_engagement:myaddinstance'] = 'Agregar un nuevo bloque de Participación estudiantil al tablero';
$string['student_engagement:view'] = 'Ver bloque de Participación estudiantil';
$string['student_engagement:viewreport'] = 'Ver reporte de Participación estudiantil';
$string['contentnotready'] = 'Las métricas de participación aún no están disponibles.';
$string['cachenotavailable'] = 'Las métricas de participación aún no se han calculado para este curso.';
$string['dashboard_subtitle'] = 'Resumen de engagement cacheado para revision rapida docente.';
$string['dashboard_active_caption'] = 'Estudiantes con actividad reciente.';
$string['dashboard_inactive_caption'] = 'Estudiantes sin actividad reciente.';
$string['dashboard_at_risk_caption'] = 'Estudiantes que requieren seguimiento.';
$string['dashboard_completion_caption'] = 'Progreso promedio en actividades completables.';
$string['nopermissions'] = 'No tienes permisos para ver este bloque.';

$string['active_days_threshold'] = 'Umbral de días activos';
$string['active_days_threshold_desc'] = 'Número de días con actividad reciente para considerar a un estudiante activo.';
$string['inactive_days_threshold'] = 'Umbral de días de inactividad';
$string['inactive_days_threshold_desc'] = 'Número de días sin actividad para considerar a un estudiante inactivo.';
$string['report_event_goal'] = 'Meta de eventos del reporte';
$string['report_event_goal_desc'] = 'Número de eventos del curso necesarios para otorgar los 30 puntos completos en el reporte de engagement.';
$string['risk_enabled'] = 'Habilitar calculo de riesgo academico';
$string['risk_enabled_desc'] = 'Cuando esta habilitado, el cron calcula riesgo academico por estudiante y lo guarda en tablas de cache.';
$string['risk_grade_weight'] = 'Peso de riesgo: nota';
$string['risk_grade_weight_desc'] = 'Porcentaje de peso para la componente de riesgo por nota.';
$string['risk_completion_weight'] = 'Peso de riesgo: completitud';
$string['risk_completion_weight_desc'] = 'Porcentaje de peso para la componente de riesgo por completitud.';
$string['risk_inactivity_weight'] = 'Peso de riesgo: inactividad';
$string['risk_inactivity_weight_desc'] = 'Porcentaje de peso para la componente de riesgo por inactividad.';
$string['risk_participation_weight'] = 'Peso de riesgo: participacion';
$string['risk_participation_weight_desc'] = 'Porcentaje de peso para la componente de riesgo por participacion.';
$string['risk_inactivity_days_threshold'] = 'Umbral de inactividad para riesgo (dias)';
$string['risk_inactivity_days_threshold_desc'] = 'Cantidad de dias sin actividad para alcanzar el maximo riesgo por inactividad.';
$string['risk_event_goal'] = 'Meta de eventos para riesgo';
$string['risk_event_goal_desc'] = 'Cantidad de eventos recientes para alcanzar riesgo cero por participacion.';
$string['risk_level_observation_min'] = 'Umbral de riesgo: observacion';
$string['risk_level_observation_min_desc'] = 'Puntaje minimo para clasificar un estudiante en nivel observacion.';
$string['risk_level_high_min'] = 'Umbral de riesgo: alto';
$string['risk_level_high_min_desc'] = 'Puntaje minimo para clasificar un estudiante en riesgo alto.';
$string['risk_level_critical_min'] = 'Umbral de riesgo: critico';
$string['risk_level_critical_min_desc'] = 'Puntaje minimo para clasificar un estudiante en riesgo critico.';
$string['risk_start_percentage'] = 'Porcentaje de inicio de etapa media';
$string['risk_start_percentage_desc'] = 'Desde este porcentaje de avance del curso, el riesgo se evalua con severidad normal.';
$string['risk_critical_percentage'] = 'Porcentaje de inicio de etapa critica';
$string['risk_critical_percentage_desc'] = 'Desde este porcentaje de avance del curso, el riesgo se evalua con mayor severidad.';
$string['risk_course_progress_mode'] = 'Modo de avance de curso para riesgo';
$string['risk_course_progress_mode_desc'] = 'Define como calcular el porcentaje de avance del curso para ajustar las etapas.';
$string['risk_course_progress_mode_course_dates'] = 'Fechas del curso';
$string['risk_course_progress_mode_graded_completion'] = 'Completitud calificable';

$string['active_students'] = 'Estudiantes activos';
$string['active_students_7_days'] = 'Estudiantes activos (7 dias)';
$string['inactive_students'] = 'Estudiantes inactivos';
$string['at_risk_students'] = 'Estudiantes en riesgo';
$string['average_completion'] = 'Completitud promedio';
$string['inactive_students_over_threshold'] = 'Estudiantes inactivos > 14 dias';
$string['most_active_user'] = 'Usuario más activo';
$string['most_active_interactions'] = 'Interacciones: {$a}';
$string['no_inactive_students'] = 'No se encontraron estudiantes inactivos.';
$string['no_most_active_user'] = 'No hay un estudiante activo disponible.';
$string['last_calculated'] = 'Último cálculo';
$string['task_calculate_engagement'] = 'Calcular y refrescar cache de engagement';
$string['view_engagement_report'] = 'Ver reporte de participación';
$string['view_at_risk_users_report'] = 'Ver estudiantes en riesgo';
$string['view_recommendations'] = 'Ver recomendaciones';
$string['coming_soon'] = 'Proximamente';
$string['report_title'] = 'Reporte de participación';
$string['report_subtitle'] = 'Métricas detalladas de engagement estudiantil para {$a}.';
$string['report_formula'] = 'Fórmula del puntaje: actividades completadas hasta 70 puntos y eventos del curso hasta 30 puntos.';
$string['report_student'] = 'Estudiante';
$string['report_events'] = 'Eventos del curso';
$string['report_completed'] = 'Actividades completadas';
$string['report_score'] = 'Puntuación de engagement';
$string['report_no_students'] = 'No se encontraron estudiantes en este curso.';
$string['view_inactive_users_report'] = 'Ver inactivos';
$string['report_inactive_title'] = 'Reporte de estudiantes inactivos';
$string['report_inactive_subtitle'] = 'Lista completa de estudiantes inactivos para {$a}.';
$string['report_inactive_formula'] = 'Incluye días de inactividad y último acceso al curso.';
$string['report_days_inactive'] = 'Días de inactividad';
$string['report_last_course_access'] = 'Último acceso al curso';
$string['report_never'] = 'Nunca';
$string['report_no_inactive_students'] = 'No se encontraron estudiantes inactivos en este curso.';
$string['report_recent_events'] = 'Eventos recientes';
$string['report_current_grade'] = 'Nota actual';
$string['report_pass_grade'] = 'Nota mínima';
$string['report_grade_gap'] = 'Brecha de nota';
$string['report_risk_score'] = 'Puntaje de riesgo';
$string['report_risk_level'] = 'Nivel de riesgo';
$string['report_risk_flags'] = 'Factores de riesgo';
$string['report_no_students_with_filters'] = 'No se encontraron estudiantes con los filtros seleccionados.';

$string['filter_all'] = 'Todos';
$string['filter_risk_level'] = 'Nivel de riesgo';
$string['filter_risk_level_high_critical'] = 'Alto + Crítico';
$string['filter_group'] = 'Grupo';
$string['filter_status'] = 'Estado';
$string['filter_status_active'] = 'Activo';
$string['filter_status_inactive'] = 'Inactivo';
$string['filter_date_from'] = 'Fecha desde';
$string['filter_date_to'] = 'Fecha hasta';
$string['filter_apply'] = 'Aplicar filtros';
$string['filter_clear'] = 'Limpiar';
$string['filters_active_summary'] = 'Filtros activos:';

$string['risk_level_label_0'] = 'Normal';
$string['risk_level_label_1'] = 'Observación';
$string['risk_level_label_2'] = 'Alto';
$string['risk_level_label_3'] = 'Crítico';
$string['risk_flag_low_grade'] = 'Nota baja';
$string['risk_flag_low_completion'] = 'Completitud baja';
$string['risk_flag_inactivity'] = 'Inactividad prolongada';
$string['risk_flag_low_participation'] = 'Participación baja';
$string['risk_flag_below_pass_grade'] = 'Bajo nota mínima';
$string['risk_flag_inactive'] = 'Inactivo';
$string['risk_flag_low_recent_activity'] = 'Actividad reciente baja';
$string['risk_flag_behind_expected_progress'] = 'Bajo el progreso esperado';
