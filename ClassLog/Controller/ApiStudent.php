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

class ApiStudent extends ApiController
{
    

    protected function runResource(): void
    {
        $action = $this->request->query->get('action', '');
        $studentId = $this->request->query->get('id', '');
        
        header('Content-Type: application/json');
        
        switch($action) {
            case 'dashboard':
                $this->getDashboard($studentId);
                break;
            case 'courses':
                $this->getCourses($studentId);
                break;
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    }
    
    private function getDashboard($studentId)
{
    $db = new DataBase();
    $today = date('Y-m-d');
    $dayOfWeek = $this->getDayOfWeekLetter(date('N'));

    // asegurar tipo de dato
    $dayOfWeek = in_array($dayOfWeek, ['L','M','X','J','V']) ? $dayOfWeek : 'L'; 

    // 📜 clases de hoy
    $sql = "SELECT
                c.nombre as curso_nombre,
                c.icono,
                c.color,
                h.hora_inicio,
                h.hora_fin,
                h.aula
            FROM cl_horarios h INNER JOIN cl_cursos c ON c.id = h.curso_id
            INNER JOIN cl_matriculas m ON m.curso_id = c.id
            WHERE m.usuario_id = $studentId AND m.activo = 1
            AND h.dia_semana = '$dayOfWeek'
            ORDER BY h.hora_inicio";

    $todaySchedule = $db->select($sql);


    // 📜 estado de asistencia de hoy
    $sql = "SELECT 
                c.nombre as curso_nombre,
                a.estado
            FROM cl_asistencias a
            INNER JOIN cl_horarios h ON h.id = a.horario_id
            INNER JOIN cl_cursos c ON c.id = h.curso_id
            WHERE a.usuario_id = ?
            AND a.fecha = ?";

    $todayAttendance = $db->select($sql, [$studentId, $today]);

    // 📜 proximas.. limites
    $now = date('Y-m-d H:i:s');
    $sql = "SELECT 
                e.titulo,
                e.tipo,
                e.fecha_limite
            FROM cl_eventos e
            INNER JOIN cl_matriculas m ON m.curso_id = e.curso_id
            WHERE m.usuario_id = ?
            AND m.activo = 1
            AND e.fecha_limite >= ?
            AND e.completado = 0
            ORDER BY e.fecha_limite ASC
            LIMIT 10";
    
    $upcomingEvents = $db->select($sql, [$studentId, $now]);

    // 📜 % de asistencia

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
    
    private function getCourses($studentId)
    {
        echo json_encode([
            'success' => true,
            'message' => 'Courses endpoint'
        ]);
    }
    
    private function getDayOfWeekLetter($dayNumber)
    {
        $days = ['L', 'M', 'X', 'J', 'V'];
        return $days[$dayNumber - 1] ?? 'L';
    }

    private function getAttendanceStats($studentId)
    {
        $db = new DataBase();

        $sql = "SELECT estado, COUNT(*) as count
        FROM cl_asistencias WHERE usuario_id = ?
        GROUP BY estado";

        $results = $db->select($sql, [$studentId]);

        $stats = [
            'presente'=>0,
            'late'=>0,
            'absent'=>0,
            'total'=>0
        ];

        foreach($results as $row){
            $count = (int)$row['count'];
            $stats['total']+=$count;

            switch($row['estado']){
                case 'presente':
                    $stats['presente'] = $count;
                    break;
                case 'tarde':
                    $stats['late'] = $count;
                    break;
                case 'ausente':
                    $stats['absent'] = $count;
                    break;
            }
        }

        $stats['percentage'] = $stats['total'] > 0
        ? round(($stats['presente'] / $stats['total']) * 100)
        : 0;


        return $stats;
    }
}
