<?php
namespace FacturaScripts\Plugins\ClassLog\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\ClassLog\Model\Horario;
use FacturaScripts\Plugins\ClassLog\Model\Curso;
use FacturaScripts\Plugins\ClassLog\Model\Matricula;
use FacturaScripts\Plugins\ClassLog\Model\Asistencia;

class EditAsistencia extends Controller
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'ClassLog 🧾';
        $data['title'] = 'Pasar Asistencia';
        $data['icon'] = 'fas fa-user-check';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $action = $this->request->request->get('action', '');

        if ($action === 'marcar-asistencia') {
            $this->marcarAsistenciaAction();
        }

        $this->setTemplate('EditAsistencia');
    }

    private function marcarAsistenciaAction(): void
    {
        $horarioId = filter_input(INPUT_POST, 'horario_id');
        $estudiantesPresentes = filter_input(INPUT_POST, 'estudiantes', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $fecha = date('Y-m-d');

        if (empty($horarioId)) {
            Tools::log()->error('no-horario-specified');
            return;
        }

        if (!is_array($estudiantesPresentes)) {
            $estudiantesPresentes = [];
        }

        // 1. Obtener TODOS los estudiantes del curso
        $todosEstudiantes = $this->getEstudiantesList();

        // 2. Para cada estudiante del curso
        foreach ($todosEstudiantes as $estudiante) {
            $usuarioId = $estudiante['id'];
            $asistencia = new Asistencia();

            // Verificar si ya existe registro para hoy
            $where = [
                new DataBaseWhere('usuario_id', $usuarioId),
                new DataBaseWhere('horario_id', $horarioId),
                new DataBaseWhere('fecha', $fecha)
            ];

            $existing = $asistencia->all($where, [], 0, 1);

            // Determinar el estado según si está en el array de presentes
            $nuevoEstado = in_array($usuarioId, $estudiantesPresentes) ? 'presente' : 'ausente';

            if (!empty($existing)) {
                // Ya existe → actualizar solo si cambió
                $asistencia = $existing[0];
                if ($asistencia->estado !== $nuevoEstado) {
                    $asistencia->estado = $nuevoEstado;
                    $asistencia->save();
                }
            } else {
                // No existe → crear nuevo solo si está presente
                // (ausencias no se guardan automáticamente en el primer guardado)
                if ($nuevoEstado === 'presente') {
                    $asistencia->usuario_id = $usuarioId;
                    $asistencia->horario_id = $horarioId;
                    $asistencia->fecha = $fecha;
                    $asistencia->estado = $nuevoEstado;
                    $asistencia->save();
                }
            }
        }

        Tools::log()->notice('record-updated-correctly');
    }

    public function getEstudiantesList(): array
    {
        $horarioId = filter_input(INPUT_GET, 'code');

        if (empty($horarioId)) {
            return [];
        }

        // horario 정보 가져오기
        $horario = new Horario();
        $horarios = $horario->all([new DataBaseWhere('id', $horarioId)], [], 0, 1);

        if (empty($horarios)) {
            return [];
        }

        $horario = $horarios[0];

        // curso의 학생들 가져오기
        $matriculaModel = new Matricula();
        $where = [
            new DataBaseWhere('curso_id', $horario->curso_id),
            new DataBaseWhere('activo', true)
        ];

        $matriculas = $matriculaModel->all($where, [], 0, 0);

        $estudiantes = [];
        foreach ($matriculas as $matricula) {
            // 학생 정보 가져오기
            $sql = "SELECT u.id, u.nombre, u.apellidos, u.email
                    FROM cl_usuarios u
                    WHERE u.id = " . intval($matricula->usuario_id);

            $db = $this->dataBase;
            $result = $db->select($sql);

            if (!empty($result)) {
                $estudiante = $result[0];

                // 오늘 이미 출석 체크했는지 확인
                $asistenciaModel = new Asistencia();
                $whereAsistencia = [
                    new DataBaseWhere('usuario_id', $estudiante['id']),
                    new DataBaseWhere('horario_id', $horarioId),
                    new DataBaseWhere('fecha', date('Y-m-d'))
                ];

                $asistencias = $asistenciaModel->all($whereAsistencia, [], 0, 1);
                $yaRegistrado = !empty($asistencias);
                $presente = $yaRegistrado && $asistencias[0]->estado === 'presente';

                $estudiantes[] = [
                    'id' => $estudiante['id'],
                    'nombre' => $estudiante['nombre'],
                    'apellidos' => $estudiante['apellidos'],
                    'email' => $estudiante['email'],
                    'presente' => $presente
                ];
            }
        }

        return $estudiantes;
    }

    public function getHorarioInfo(): ?Horario
    {
        $horarioId = filter_input(INPUT_GET, 'code');

        if (empty($horarioId)) {
            return null;
        }

        $horario = new Horario();
        $horarios = $horario->all([new DataBaseWhere('id', $horarioId)], [], 0, 1);

        if (!empty($horarios)) {
            return $horarios[0];
        }

        return null;
    }

    public function getCursoInfo(): ?Curso
    {
        $horario = $this->getHorarioInfo();

        if ($horario === null) {
            return null;
        }

        $curso = new Curso();
        $cursos = $curso->all([new DataBaseWhere('id', $horario->curso_id)], [], 0, 1);

        if (!empty($cursos)) {
            return $cursos[0];
        }

        return null;
    }
}