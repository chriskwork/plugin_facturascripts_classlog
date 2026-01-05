<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\ClassLog\Model\Horario;
use FacturaScripts\Plugins\ClassLog\Model\Curso;
use FacturaScripts\Plugins\ClassLog\Model\Profesor;

class ListAsistencia extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'ClassLog 🧾';
        $data['title'] = 'Asistencia';
        $data['icon'] = 'fas fa-calendar-check';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        
        // 커스텀 템플릿 사용
        $this->setTemplate('ListAsistencia');
    }

    /**
     * 오늘 날짜의 수업 목록을 가져옵니다
     */
    public function getHorariosHoy(): array
    {
        $todayNumber = date('N');
        $daysMap = [
            1 => 'L',
            2 => 'M',
            3 => 'X', 
            4 => 'J',
            5 => 'V', 
            6 => 'S', 
            7 => 'D'
        ];

        $todayLetter = $daysMap[$todayNumber];

        $horarioModel = new Horario();
        $where = [new DataBaseWhere('dia_semana', $todayLetter)];

        // 교수인 경우 자기 수업만 보기
        if (!$this->user->admin) {
            $profesorModel = new Profesor();
            $whereProf = [new DataBaseWhere('usuario_id', $this->user->nick)];
            $profesores = $profesorModel->all($whereProf, [], 0, 1);

            if (!empty($profesores)) {
                $profesor = $profesores[0];
                $where[] = new DataBaseWhere(
                    'curso_id',
                    '(SELECT id FROM cl_cursos WHERE profesor_id = ' . $profesor->id . ')',
                    'IN'
                );
            }
        }

        return $horarioModel->all($where, ['hora_inicio' => 'ASC'], 0, 0);
    }

    /**
     * 과목 이름 가져오기
     */
    public function getCursoNombre($cursoId): string
    {
        $cursoModel = new Curso();
        $cursos = $cursoModel->all([new DataBaseWhere('id', $cursoId)], [], 0, 1);
        return !empty($cursos) ? $cursos[0]->nombre : 'Sin nombre';
    }
}