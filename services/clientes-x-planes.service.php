<?php
class ClientesXPlanes
{

    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.listado');

        $db = Flight::db();
        $sentence = $db->prepare("select exg.id, exg.anio, exg.id_cliente, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_plan, g.nombre nombre_plan,
        e.activo, e.alimentacion, e.permanente, e.anno
        from clientes_x_planes exg
        inner join clientes e on exg.id_cliente = e.id 
        inner join personas p on e.id_persona = p.id 
        inner join planes g on exg.id_plan = g.id 
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
        $sentence = $db->prepare("select exg.id, exg.anio, exg.id_cliente, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_plan, g.nombre nombre_plan,
        e.activo, e.alimentacion, e.permanente, e.anno
        from clientes_x_planes exg
        inner join clientes e on exg.id_cliente = e.id 
        inner join personas p on e.id_persona = p.id 
        inner join planes g on exg.id_plan = g.id 
        where e.activo = 1 and exg.activo = 1
        and exg.id_tenant = :id_tenant
        order by g.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPlan($idPlan)
    {
        if ($idPlan == 0) {
            self::getAll();
        } else {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'clientes.listado');

            $db = Flight::db();
            $sentence = $db->prepare("select exg.id, exg.anio, exg.id_cliente, 
            p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
            exg.id_plan, g.nombre nombre_plan,
            exg.activo, e.alimentacion, e.permanente, e.anno
            from clientes_x_planes exg
            inner join clientes e on exg.id_cliente = e.id 
            inner join personas p on e.id_persona = p.id 
            inner join planes g on exg.id_plan = g.id 
            where exg.activo = 1
            and g.id = :id_plan
            and exg.id_tenant = :id_tenant");
            $sentence->bindParam(':id_plan', $idPlan);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        }
    }

    /**
     * Listado filtrado del módulo de clientes.
     * Todos los filtros son opcionales y llegan por query string; los que vengan
     * vacíos no se aplican (sin filtros => trae todos los activos del tenant).
     *   id_plan   => filtra por plan
     *   estado     => 'activo' | 'inactivo' (sobre e.activo)
     *   permanente => '1' | '0' (sobre e.permanente)
     *   nombre     => coincidencia parcial sobre el nombre completo
     */
    public static function getPorFiltros()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.listado');

        $db = Flight::db();

        $idPlan    = isset(Flight::request()->query['id_plan']) ? trim(Flight::request()->query['id_plan']) : '';
        $estado     = isset(Flight::request()->query['estado']) ? trim(Flight::request()->query['estado']) : '';
        $permanente = isset(Flight::request()->query['permanente']) ? trim(Flight::request()->query['permanente']) : '';
        $nombre     = isset(Flight::request()->query['nombre']) ? trim(Flight::request()->query['nombre']) : '';

        $sql = "select exg.id, exg.anio, exg.id_cliente, 
            p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
            exg.id_plan, g.nombre nombre_plan,
            e.activo, e.alimentacion, e.permanente, e.anno
            from clientes_x_planes exg
            inner join clientes e on exg.id_cliente = e.id 
            inner join personas p on e.id_persona = p.id 
            inner join planes g on exg.id_plan = g.id 
            where exg.activo = 1
            and exg.id_tenant = :id_tenant";

        $params = [];

        if ($idPlan !== '') {
            $sql .= " and g.id = :id_plan";
            $params[':id_plan'] = $idPlan;
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

    public static function getByCliente($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exg.id, exg.anio, exg.id_cliente, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_plan, g.nombre nombre_plan,
        e.activo, e.alimentacion, e.permanente, e.anno,
        e.telefono_emergencia, e.eps, e.fecha_ingreso
        FROM clientes_x_planes exg
        INNER JOIN clientes e ON exg.id_cliente = e.id 
        INNER JOIN personas p ON e.id_persona = p.id 
        INNER JOIN planes g ON exg.id_plan = g.id 
        WHERE exg.id_cliente = :id AND exg.activo = 1 AND exg.id_tenant = :id_tenant
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
        $sentence = $db->prepare("select exg.id, exg.anio, exg.id_cliente, 
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        exg.id_plan, g.nombre nombre_plan,
        exg.activo, exg.id id_cliente_plan, e.alimentacion, e.permanente, e.anno
        from clientes_x_planes exg
        inner join clientes e on exg.id_cliente = e.id 
        inner join personas p on e.id_persona = p.id 
        inner join planes g on exg.id_plan = g.id 
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
        PermisosService::validarAlguno($userData, ['clientes.cambio_plan', 'clientes.administrar']);

        $db = Flight::db();
        $anio = Flight::request()->data['anio'];
        $id_cliente = Flight::request()->data['id_cliente'];
        $id_plan = Flight::request()->data['id_plan'];
        $idNew = Uuid::generar();
        $sentence = $db->prepare("insert into clientes_x_planes(id, id_tenant, anio, id_cliente, id_plan, activo) values (:id, :id_tenant, :anio, :id_cliente, :id_plan, 1)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindParam(':id_cliente', $id_cliente);
        $sentence->bindParam(':id_plan', $id_plan);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validarAlguno($userData, ['clientes.cambio_plan', 'clientes.administrar']);

            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $sentence = $db->prepare("update clientes_x_planes set activo = 0 where id = :id and id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            self::getById($id);
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()));
        }
    }

    public static function cambioPlanMasivo()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $clientes = Flight::request()->data['clientes'];
            $id_plan_nuevo = Flight::request()->data['id_plan'];

            if (empty($clientes) || empty($id_plan_nuevo)) {
                Flight::json(array('success' => false, 'message' => 'Datos incompletos'), 400);
                return;
            }

            $actualizados = 0;

            foreach ($clientes as $est) {
                $id_cliente_plan = $est['id_cliente_plan'];
                $id_cliente = $est['id_cliente'];
                $anno = $est['anno'];
                // Permite override por cliente, si no usa el global

                // Inactivar registro actual
                $sentenceInactivar = $db->prepare("UPDATE clientes_x_planes SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
                $sentenceInactivar->bindParam(':id', $id_cliente_plan);
                $sentenceInactivar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceInactivar->execute();

                // Crear nuevo registro con el año del cliente
                $sentenceNuevo = $db->prepare("INSERT INTO clientes_x_planes (id_tenant, anio, id_cliente, id_plan, activo) VALUES (:id_tenant, :anio, :id_cliente, :id_plan, 1)");
                $sentenceNuevo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceNuevo->bindParam(':anio', $anno);
                $sentenceNuevo->bindParam(':id_cliente', $id_cliente);
                $sentenceNuevo->bindParam(':id_plan', $id_plan_nuevo);
                $sentenceNuevo->execute();

                $actualizados++;
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'actualizados' => $actualizados,
                'message' => 'Cambio de plan completado'
            ));

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en cambioPlanMasivo: " . $e->getMessage());
            Flight::json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }
}