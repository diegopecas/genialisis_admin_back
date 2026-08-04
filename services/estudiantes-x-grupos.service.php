<?php
class EstudiantesXGrupos
{

    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.listado');

        $db = Flight::db();
        $sentence = $db->prepare("select exg.id, exg.anio, exg.id_estudiante, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_grupo, g.nombre nombre_grupo,
        e.activo, e.alimentacion, e.permanente, e.anno
        from estudiantes_x_grupos exg
        inner join estudiantes e on exg.id_estudiante = e.id 
        inner join personas p on e.id_persona = p.id 
        inner join grupos g on exg.id_grupo = g.id 
        where exg.activo = 1
        and exg.id_tenant = :id_tenant
        order by g.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select exg.id, exg.anio, exg.id_estudiante, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_grupo, g.nombre nombre_grupo,
        e.activo, e.alimentacion, e.permanente, e.anno
        from estudiantes_x_grupos exg
        inner join estudiantes e on exg.id_estudiante = e.id 
        inner join personas p on e.id_persona = p.id 
        inner join grupos g on exg.id_grupo = g.id 
        where e.activo = 1 and exg.activo = 1
        and exg.id_tenant = :id_tenant
        order by g.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGrupo($idGrupo)
    {
        if ($idGrupo == 0) {
            self::getAll();
        } else {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'estudiantes.listado');

            $db = Flight::db();
            $sentence = $db->prepare("select exg.id, exg.anio, exg.id_estudiante, 
            p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
            exg.id_grupo, g.nombre nombre_grupo,
            exg.activo, e.alimentacion, e.permanente, e.anno
            from estudiantes_x_grupos exg
            inner join estudiantes e on exg.id_estudiante = e.id 
            inner join personas p on e.id_persona = p.id 
            inner join grupos g on exg.id_grupo = g.id 
            where exg.activo = 1
            and g.id = :id_grupo
            and exg.id_tenant = :id_tenant");
            $sentence->bindParam(':id_grupo', $idGrupo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        }
    }

    /**
     * Listado filtrado del módulo de estudiantes.
     * Todos los filtros son opcionales y llegan por query string; los que vengan
     * vacíos no se aplican (sin filtros => trae todos los activos del tenant).
     *   id_grupo   => filtra por grupo
     *   estado     => 'activo' | 'inactivo' (sobre e.activo)
     *   permanente => '1' | '0' (sobre e.permanente)
     *   nombre     => coincidencia parcial sobre el nombre completo
     */
    public static function getPorFiltros()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.listado');

        $db = Flight::db();

        $idGrupo    = isset(Flight::request()->query['id_grupo']) ? trim(Flight::request()->query['id_grupo']) : '';
        $estado     = isset(Flight::request()->query['estado']) ? trim(Flight::request()->query['estado']) : '';
        $permanente = isset(Flight::request()->query['permanente']) ? trim(Flight::request()->query['permanente']) : '';
        $nombre     = isset(Flight::request()->query['nombre']) ? trim(Flight::request()->query['nombre']) : '';

        $sql = "select exg.id, exg.anio, exg.id_estudiante, 
            p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
            exg.id_grupo, g.nombre nombre_grupo,
            e.activo, e.alimentacion, e.permanente, e.anno
            from estudiantes_x_grupos exg
            inner join estudiantes e on exg.id_estudiante = e.id 
            inner join personas p on e.id_persona = p.id 
            inner join grupos g on exg.id_grupo = g.id 
            where exg.activo = 1
            and exg.id_tenant = :id_tenant";

        $params = [];

        if ($idGrupo !== '') {
            $sql .= " and g.id = :id_grupo";
            $params[':id_grupo'] = $idGrupo;
        }

        if ($estado === 'activo') {
            $sql .= " and e.activo = 1";
        } elseif ($estado === 'inactivo') {
            $sql .= " and e.activo = 0";
        }

        if ($permanente === '1' || $permanente === '0') {
            $sql .= " and e.permanente = :permanente";
            $params[':permanente'] = (int) $permanente;
        }

        if ($nombre !== '') {
            $sql .= " and concat_ws(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) like :nombre";
            $params[':nombre'] = '%' . $nombre . '%';
        }

        $sql .= " order by g.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        foreach ($params as $clave => $valor) {
            $sentence->bindValue($clave, $valor);
        }
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstudiante($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exg.id, exg.anio, exg.id_estudiante, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_grupo, g.nombre nombre_grupo,
        e.activo, e.alimentacion, e.permanente, e.anno,
        e.telefono_emergencia, e.eps, e.fecha_ingreso
        FROM estudiantes_x_grupos exg
        INNER JOIN estudiantes e ON exg.id_estudiante = e.id 
        INNER JOIN personas p ON e.id_persona = p.id 
        INNER JOIN grupos g ON exg.id_grupo = g.id 
        WHERE exg.id_estudiante = :id AND exg.activo = 1 AND exg.id_tenant = :id_tenant
        ORDER BY g.orden");

        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select exg.id, exg.anio, exg.id_estudiante, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_grupo, g.nombre nombre_grupo,
        exg.activo, exg.id id_estudiante_grupo, e.alimentacion, e.permanente, e.anno
        from estudiantes_x_grupos exg
        inner join estudiantes e on exg.id_estudiante = e.id 
        inner join personas p on e.id_persona = p.id 
        inner join grupos g on exg.id_grupo = g.id 
        where exg.activo = 1
        and exg.id = :id
        and exg.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validarAlguno($userData, ['estudiantes.cambio_grupo', 'estudiantes.administrar']);

        $db = Flight::db();
        $anio = Flight::request()->data['anio'];
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $id_grupo = Flight::request()->data['id_grupo'];
        $idNew = Uuid::generar();
        $sentence = $db->prepare("insert into estudiantes_x_grupos(id, id_tenant, anio, id_estudiante, id_grupo, activo) values (:id, :id_tenant, :anio, :id_estudiante, :id_grupo, 1)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindParam(':id_grupo', $id_grupo);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validarAlguno($userData, ['estudiantes.cambio_grupo', 'estudiantes.administrar']);

            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $sentence = $db->prepare("update estudiantes_x_grupos set activo = 0 where id = :id and id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            self::getById($id);
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()));
        }
    }

    public static function cambioGrupoMasivo()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $estudiantes = Flight::request()->data['estudiantes'];
            $id_grupo_nuevo = Flight::request()->data['id_grupo'];

            if (empty($estudiantes) || empty($id_grupo_nuevo)) {
                Flight::json(array('success' => false, 'message' => 'Datos incompletos'), 400);
                return;
            }

            $actualizados = 0;

            foreach ($estudiantes as $est) {
                $id_estudiante_grupo = $est['id_estudiante_grupo'];
                $id_estudiante = $est['id_estudiante'];
                $anno = $est['anno'];
                // Permite override por estudiante, si no usa el global

                // Inactivar registro actual
                $sentenceInactivar = $db->prepare("UPDATE estudiantes_x_grupos SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
                $sentenceInactivar->bindParam(':id', $id_estudiante_grupo);
                $sentenceInactivar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceInactivar->execute();

                // Crear nuevo registro con el año del estudiante
                $sentenceNuevo = $db->prepare("INSERT INTO estudiantes_x_grupos (id_tenant, anio, id_estudiante, id_grupo, activo) VALUES (:id_tenant, :anio, :id_estudiante, :id_grupo, 1)");
                $sentenceNuevo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceNuevo->bindParam(':anio', $anno);
                $sentenceNuevo->bindParam(':id_estudiante', $id_estudiante);
                $sentenceNuevo->bindParam(':id_grupo', $id_grupo_nuevo);
                $sentenceNuevo->execute();

                $actualizados++;
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'actualizados' => $actualizados,
                'message' => 'Cambio de grupo completado'
            ));

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en cambioGrupoMasivo: " . $e->getMessage());
            Flight::json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }
}