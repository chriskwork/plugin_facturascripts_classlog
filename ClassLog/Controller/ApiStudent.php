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
    
    // 1. 학생의 수강 과목 찾기
    $matriculaModel = new Matricula();
    $matriculas = $matriculaModel->all();
    
    $courseIds = [];
    foreach($matriculas as $mat) {
        if($mat->usuario_id == $studentId && $mat->activo) {
            $courseIds[] = $mat->curso_id;
        }
    }
    
    // 2. 오늘 수업 일정
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
    
    // 3. 오늘 출석
    $todayAttendance = [];
    $asistenciaModel = new Asistencia();
    $asistencias = $asistenciaModel->all();
    
    foreach($asistencias as $asist) {
        if($asist->usuario_id == $studentId && $asist->fecha == $today) {
            // 어느 과목인지 찾기
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
    
    // 4. 다가오는 이벤트
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
    
    echo json_encode([
        'success' => true,
        'data' => [
            'student_id' => $studentId,
            'today' => $today,
            'day_of_week' => $dayOfWeek,
            'today_schedule' => $todaySchedule,
            'today_attendance' => $todayAttendance,
            'upcoming_events' => $upcomingEvents
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
}
