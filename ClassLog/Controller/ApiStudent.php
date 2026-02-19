<?php

namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Plugins\ClassLog\Model\Usuario;
use FacturaScripts\Plugins\ClassLog\Model\Curso;
use FacturaScripts\Plugins\ClassLog\Model\Matricula;
use FacturaScripts\Plugins\ClassLog\Model\Asistencia;
use FacturaScripts\Plugins\ClassLog\Model\Horario;
use FacturaScripts\Plugins\ClassLog\Model\Evento;
use FacturaScripts\Plugins\ClassLog\Model\Calificacion;

use function PHPSTORM_META\type;

class ApiStudent extends ApiController {


    protected function runResource(): void {

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Token, Authorization');
        header('Access-Control-Max-Age: 86400');
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $action = $this->request->query->get('action', '');
        $studentId = $this->request->query->get('id', '');

        switch ($action) {
            case 'dashboard':
                $this->getDashboard($studentId);
                break;
            case 'courses':
                $this->getCourses($studentId);
                break;
            case 'calendar':
                $this->getCalendar($studentId);
                break;
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    }

    // obtener los datos de usuario id = x
    private function getDashboard($studentId) {
        $db = new DataBase();
        $today = date('Y-m-d');
        $dayOfWeek = $this->getDayOfWeekLetter(date('N'));

        // validar dia de la semana
        $dayOfWeek = in_array($dayOfWeek, ['L', 'M', 'X', 'J', 'V']) ? $dayOfWeek : 'L';

        // clases de hoy
        $sql = "SELECT
                c.nombre as curso_nombre,
                c.icono,
                c.color,
                TIME_FORMAT(h.hora_inicio, '%H:%i') as hora_inicio,
                TIME_FORMAT(h.hora_fin, '%H:%i') as hora_fin,
                h.aula
            FROM cl_horarios h INNER JOIN cl_cursos c ON c.id = h.curso_id
            INNER JOIN cl_matriculas m ON m.curso_id = c.id
            WHERE m.usuario_id = {$studentId}
            AND h.dia_semana = '{$dayOfWeek}'
            ORDER BY h.hora_inicio";

        $todaySchedule = $db->select($sql);


        // asistencia de hoy
        $sql = "SELECT 
                c.nombre as curso_nombre,
                a.estado
            FROM cl_asistencias a
            INNER JOIN cl_horarios h ON h.id = a.horario_id
            INNER JOIN cl_cursos c ON c.id = h.curso_id
            WHERE a.usuario_id = {$studentId}
            AND a.fecha = '{$today}'";

        $todayAttendance = $db->select($sql);

        // próximos eventos
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT 
                e.titulo,
                e.tipo,
                e.fecha_limite
            FROM cl_eventos e
            INNER JOIN cl_matriculas m ON m.curso_id = e.curso_id
            WHERE m.usuario_id = $studentId
            AND e.fecha_limite >= '{$now}'
            AND e.completado = 0
            ORDER BY e.fecha_limite ASC
            LIMIT 10";

        $upcomingEvents = $db->select($sql);

        // porcentaje de asistencia
        $attendanceStats = $this->getAttendanceStats($studentId);

        echo json_encode([
            'success' => true,
            'data' => [
                'student_id' => $studentId,
                'today' => $today,
                'day_of_week' => $dayOfWeek,
                'today_schedule' => $todaySchedule,
                'today_attendance' => $todayAttendance,
                'upcoming_events' => $upcomingEvents,
                'attendance_stats' => $attendanceStats
            ]
        ]);

        exit;
    }

    private function getCourses($studentId) {
        $db = new DataBase();

        // prevenir sql injection
        $studentId = intval($studentId);

        // cursos con horario y estadísticas
        $sql = "SELECT
                    c.id,
                    c.nombre,
                    c.descripcion,
                    c.icono,
                    c.color,
                    u.nombre as profesor_nombre,
                    GROUP_CONCAT(DISTINCT h.dia_semana ORDER BY h.dia_semana) as dias,
                    MIN(h.hora_inicio) as hora_inicio,
                    MAX(h.hora_fin) as hora_fin,
                    GROUP_CONCAT(DISTINCT h.aula) as aula
                FROM cl_cursos c
                INNER JOIN cl_matriculas m ON m.curso_id = c.id
                LEFT JOIN cl_usuarios u ON u.id = c.profesor_id
                LEFT JOIN cl_horarios h ON h.curso_id = c.id
                WHERE m.usuario_id = {$studentId}
                GROUP BY c.id, c.nombre, c.descripcion, c.icono, c.color, u.nombre";

        $courses = $db->select($sql);

        // añadir estadísticas por curso
        foreach ($courses as &$course) {
            $courseId = $course['id'];

            // estadísticas de asistencia del curso
            $statsSql = "SELECT
                            a.estado,
                            COUNT(*) as count
                        FROM cl_asistencias a
                        INNER JOIN cl_horarios h ON h.id = a.horario_id
                        WHERE h.curso_id = {$courseId}
                        AND a.usuario_id = {$studentId}
                        GROUP BY a.estado";

            $stats = $db->select($statsSql);

            $presente = 0;
            $total = 0;

            foreach ($stats as $stat) {
                $count = (int)$stat['count'];
                $total += $count;
                if ($stat['estado'] === 'presente') {
                    $presente = $count;
                }
            }

            $course['asistencia_percentage'] = $total > 0
                ? round(($presente / $total) * 100)
                : 0;
            $course['asistencia_presente'] = $presente;
            $course['asistencia_total'] = $total;
        }

        echo json_encode([
            'success' => true,
            'data' => $courses
        ]);

        exit;
    }

    private function getDayOfWeekLetter($dayNumber) {
        $days = ['L', 'M', 'X', 'J', 'V'];
        return $days[$dayNumber - 1] ?? 'L';
    }

    private function getAttendanceStats($studentId) {
        $db = new DataBase();

        $studentId = intval($studentId);

        $sql = "SELECT estado, COUNT(*) as count
                FROM cl_asistencias 
                WHERE usuario_id = {$studentId}
                GROUP BY estado";

        $results = $db->select($sql);

        $stats = [
            'presente' => 0,
            'tarde' => 0,
            'ausente' => 0,
            'total' => 0
        ];

        foreach ($results as $row) {
            $count = (int)$row['count'];
            $stats['total'] += $count;

            switch ($row['estado']) {
                case 'presente':
                    $stats['presente'] = $count;
                    break;
                case 'tarde':
                    $stats['tarde'] = $count;
                    break;
                case 'ausente':
                    $stats['ausente'] = $count;
                    break;
            }
        }

        // calcular porcentaje
        // numero de presentado / clases total
        $stats['percentage'] = $stats['total'] > 0
            ? round(($stats['presente'] / $stats['total']) * 100)
            : 0;

        return $stats;
    }

    private function getCalendar($studentId) {
        $db = new DataBase();

        $studentId = intval($studentId);

        // eventos de cursos matriculados
        $sql = "SELECT
                    e.id,
                    e.curso_id,
                    e.tipo,
                    e.titulo,
                    e.descripcion,
                    e.fecha_limite,
                    e.completado,
                    c.nombre as curso_nombre
                FROM cl_eventos e
                INNER JOIN cl_cursos c ON c.id = e.curso_id
                INNER JOIN cl_matriculas m ON m.curso_id = c.id
                WHERE m.usuario_id = {$studentId}
                ORDER BY e.fecha_limite ASC";

        $events = $db->select($sql);

        echo json_encode([
            'success' => true,
            'data' => $events
        ]);

        exit;
    }
}
