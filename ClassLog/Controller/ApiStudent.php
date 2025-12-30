<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Plugins\ClassLog\Model\Usuario;
use FacturaScripts\Plugins\ClassLog\Model\Curso;
use FacturaScripts\Plugins\ClassLog\Model\Matricula;
use FacturaScripts\Plugins\ClassLog\Model\Asistencia;
use FacturaScripts\Plugins\ClassLog\Model\Horario;
use FacturaScripts\Plugins\ClassLog\Model\Evento;
use FacturaScripts\Plugins\ClassLog\Model\Calificacion;

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
    $today = date('Y-m-d');
    $dayOfWeek = $this->getDayOfWeekLetter(date('N'));
    
    // 📜📜 Matricula
    $matriculaModel = new Matricula();
    $matriculas = $matriculaModel->all();
    
    $courseIds = [];
    foreach($matriculas as $mat) {
        if($mat->usuario_id == $studentId && $mat->activo) {
            $courseIds[] = $mat->curso_id;
        }
    }
    
    // 📜📜 Clases de hoy
    $todaySchedule = [];
    $horarioModel = new Horario();
    $horarios = $horarioModel->all();
    
    $cursoModel = new Curso();
    $cursos = $cursoModel->all();
    
    foreach($horarios as $horario) {
        if(in_array($horario->curso_id, $courseIds) && $horario->dia_semana == $dayOfWeek) {
            // 과목명 찾기
            $cursoNombre = '';
            foreach($cursos as $curso) {
                if($curso->id == $horario->curso_id) {
                    $cursoNombre = $curso->nombre;
                    break;
                }
            }
            
            $todaySchedule[] = [
                'curso_nombre' => $cursoNombre,
                'hora_inicio' => $horario->hora_inicio,
                'hora_fin' => $horario->hora_fin,
                'aula' => $horario->aula
            ];
        }
    }
    
    // 📜📜 Asistencia
    $todayAttendance = [];
    $asistenciaModel = new Asistencia();
    $asistencias = $asistenciaModel->all();
    
    foreach($asistencias as $asist) {
        if($asist->usuario_id == $studentId && $asist->fecha == $today) {
            
            $horarioId = $asist->horario_id;
            $cursoNombre = '';
            
            foreach($horarios as $h) {
                if($h->id == $horarioId) {
                    foreach($cursos as $c) {
                        if($c->id == $h->curso_id) {
                            $cursoNombre = $c->nombre;
                            break;
                        }
                    }
                    break;
                }
            }
            
            $todayAttendance[] = [
                'curso_nombre' => $cursoNombre,
                'estado' => $asist->estado
            ];
        }
    }


    
    // 📜📜 Proximas ..limite
    $upcomingEvents = [];
    $eventoModel = new Evento();
    $eventos = $eventoModel->all();
    
    $now = date('Y-m-d H:i:s');
    foreach($eventos as $evento) {
        if(in_array($evento->curso_id, $courseIds) && 
           $evento->fecha_limite >= $now && 
           !$evento->completado) {
            $upcomingEvents[] = [
                'titulo' => $evento->titulo,
                'tipo' => $evento->tipo,
                'fecha_limite' => $evento->fecha_limite
            ];
        }
    }
    


    // 📜📜 Porcentaje de asistencia
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
        $asistenciaModel = new Asistencia();
        $asistencias = $asistenciaModel->all();
        
        $totalClasses = 0;
        $presentCount = 0;
        $tardeCount = 0; 
        $ausenteCount = 0; 
        
        foreach($asistencias as $asist) {
            if($asist->usuario_id == $studentId) {
                $totalClasses++;
                
                switch($asist->estado) {
                    case 'presente':
                        $presentCount++;
                        break;
                    case 'tarde':
                        $tardeCount++;
                        break;
                    case 'ausente':
                        $ausenteCount++;
                        break;
                }
            }
        }
        
        $percentage = $totalClasses > 0 
            ? round(($presentCount / $totalClasses) * 100) 
            : 0;
        
        return [
            'percentage' => $percentage,
            'present' => $presentCount,
            'late' => $tardeCount,
            'absent' => $ausenteCount,
            'total' => $totalClasses
        ];
    }
}
