<?php

/**
 * Servicio para gestión de Contratos de Colaboradores
 * CRUD sobre la tabla contratos_colaborador.
 *
 * Patrón seguido (igual a contratos_matricula.service.php):
 *  - Métodos estáticos, Flight::db(), Flight::json()
 *  - new() devuelve {id} (lastInsertId)
 *  - Soft delete vía anular() (activo = 0)
 *  - Autenticación JWT + validación de permisos
 *
 * NOTA: el código de permiso usado es 'colaboradores.contratos', análogo a
 * 'estudiantes.contratos'. Ajustar si la nomenclatura real difiere.
 */

class ContratosColaborador
{
    /**
     * Obtener todos los contratos (con datos básicos del colaborador/cargo/tipo)
     * GET /contratos-colaborador
     */
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos');

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cc.*,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS colaborador_nombre,
                       p.numero_identificacion AS colaborador_documento,
                       car.nombre AS cargo_nombre,
                       tc.nombre AS tipo_contrato_nombre,
                       tc.codigo AS tipo_contrato_codigo
                FROM contratos_colaborador cc
                INNER JOIN colaboradores col ON cc.id_colaborador = col.id
                INNER JOIN personas p ON col.id_persona = p.id
                INNER JOIN cargos car ON cc.id_cargo = car.id
                INNER JOIN tipos_contrato tc ON cc.id_tipo_contrato = tc.id
                WHERE cc.activo = 1
                  AND cc.id_tenant = :id_tenant
                ORDER BY cc.anio DESC, cc.numero DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultados = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($resultados);
        } catch (Exception $e) {
            error_log("Error en ContratosColaborador::getAll: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener un contrato por ID
     * GET /contratos-colaborador/:id
     */
    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos');

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cc.*,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS colaborador_nombre,
                       p.numero_identificacion AS colaborador_documento,
                       car.nombre AS cargo_nombre,
                       tc.nombre AS tipo_contrato_nombre,
                       tc.codigo AS tipo_contrato_codigo
                FROM contratos_colaborador cc
                INNER JOIN colaboradores col ON cc.id_colaborador = col.id
                INNER JOIN personas p ON col.id_persona = p.id
                INNER JOIN cargos car ON cc.id_cargo = car.id
                INNER JOIN tipos_contrato tc ON cc.id_tipo_contrato = tc.id
                WHERE cc.id = :id
                  AND cc.id_tenant = :id_tenant
                LIMIT 1
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultado = $sentence->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                Flight::json($resultado);
            } else {
                Flight::json(['error' => 'Contrato no encontrado'], 404);
            }
        } catch (Exception $e) {
            error_log("Error en ContratosColaborador::getById: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener los contratos de un colaborador
     * GET /contratos-colaborador/colaborador/:idColaborador
     */
    public static function getByColaborador($idColaborador)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos');

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cc.*,
                       car.nombre AS cargo_nombre,
                       tc.nombre AS tipo_contrato_nombre,
                       tc.codigo AS tipo_contrato_codigo
                FROM contratos_colaborador cc
                INNER JOIN cargos car ON cc.id_cargo = car.id
                INNER JOIN tipos_contrato tc ON cc.id_tipo_contrato = tc.id
                WHERE cc.id_colaborador = :id_colaborador
                  AND cc.activo = 1
                  AND cc.id_tenant = :id_tenant
                ORDER BY cc.anio DESC, cc.numero DESC
            ");
            $sentence->bindParam(':id_colaborador', $idColaborador);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultados = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($resultados);
        } catch (Exception $e) {
            error_log("Error en ContratosColaborador::getByColaborador: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Datos consolidados para generar el PDF de un contrato.
     * Devuelve el contrato congelado + datos del colaborador (persona) +
     * configuración global (institución y representante legal).
     * GET /contratos-colaborador/datos-pdf/:id
     */
    public static function getDatosContrato($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos');

            $db = Flight::db();

            // Contrato + colaborador + cargo + tipo
            $sentenceContrato = $db->prepare("
                SELECT cc.*,
                       car.nombre AS cargo_nombre,
                       tc.nombre AS tipo_contrato_nombre,
                       tc.codigo AS tipo_contrato_codigo,
                       p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       p.numero_identificacion AS colaborador_documento,
                       p.correo_electronico AS colaborador_email,
                       p.telefono AS colaborador_telefono,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS colaborador_nombre
                FROM contratos_colaborador cc
                INNER JOIN colaboradores col ON cc.id_colaborador = col.id
                INNER JOIN personas p ON col.id_persona = p.id
                INNER JOIN cargos car ON cc.id_cargo = car.id
                INNER JOIN tipos_contrato tc ON cc.id_tipo_contrato = tc.id
                WHERE cc.id = :id
                  AND cc.id_tenant = :id_tenant
                LIMIT 1
            ");
            $sentenceContrato->bindParam(':id', $id);
            $sentenceContrato->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceContrato->execute();
            $contrato = $sentenceContrato->fetch(PDO::FETCH_ASSOC);

            if (!$contrato) {
                Flight::json(['error' => 'Contrato no encontrado'], 404);
                return;
            }

            // Configuración global (institución + representante legal)
            $sentenceConfig = $db->prepare("
                SELECT clave, valor_texto
                FROM configuracion_global
                WHERE clave IN (
                    'institucion_nombre', 'institucion_nit', 'institucion_direccion',
                    'institucion_telefono', 'institucion_email', 'institucion_eslogan',
                    'institucion_razon_social',
                    'representante_legal_nombre', 'representante_legal_cedula',
                    'representante_legal_cedula_lugar', 'representante_legal_email'
                )
                AND id_tenant = :id_tenant
            ");
            $sentenceConfig->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceConfig->execute();
            $filasConfig = $sentenceConfig->fetchAll(PDO::FETCH_ASSOC);

            $configuracion = [];
            foreach ($filasConfig as $fila) {
                $configuracion[$fila['clave']] = $fila['valor_texto'];
            }

            Flight::json([
                'contrato' => $contrato,
                'configuracion' => $configuracion
            ]);
        } catch (Exception $e) {
            error_log("Error en ContratosColaborador::getDatosContrato: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear un nuevo contrato.
     * Calcula el consecutivo del año, resuelve la plantilla a partir de
     * (cargo + tipo de contrato) y congela los datos enviados.
     * POST /contratos-colaborador
     *
     * Devuelve {id} (lastInsertId).
     */
    public static function new()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos.administrar');


            $idColaborador  = Flight::request()->data['id_colaborador'] ?? null;
            $idCargo        = Flight::request()->data['id_cargo'] ?? null;
            $idTipoContrato = Flight::request()->data['id_tipo_contrato'] ?? null;
            $anio           = Flight::request()->data['anio'] ?? null;

            if (!$idColaborador || !$idCargo || !$idTipoContrato || !$anio) {
                Flight::json(['error' => 'Faltan datos obligatorios (id_colaborador, id_cargo, id_tipo_contrato, anio)'], 400);
                return;
            }

            // Resolver la plantilla para el par (cargo, tipo de contrato)
            $sentencePlantilla = $db->prepare("
                SELECT id_plantilla
                FROM cargos_plantillas_contratos
                WHERE id_cargo = :id_cargo
                  AND id_tipo_contrato = :id_tipo_contrato
                  AND activo = 1
                  AND id_tenant = :id_tenant
                LIMIT 1
            ");
            $sentencePlantilla->bindParam(':id_cargo', $idCargo);
            $sentencePlantilla->bindParam(':id_tipo_contrato', $idTipoContrato);
            $sentencePlantilla->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentencePlantilla->execute();
            $filaPlantilla = $sentencePlantilla->fetch(PDO::FETCH_ASSOC);

            if (!$filaPlantilla) {
                Flight::json(['error' => 'No existe una plantilla configurada para ese cargo y tipo de contrato'], 422);
                return;
            }
            $idPlantilla = $filaPlantilla['id_plantilla'];

            $db->beginTransaction();

            // Consecutivo del año (bloqueando el cálculo dentro de la transacción)
            $sentenceNumero = $db->prepare("
                SELECT COALESCE(MAX(numero), 0) + 1 AS siguiente
                FROM contratos_colaborador
                WHERE anio = :anio
                  AND id_tenant = :id_tenant
                FOR UPDATE
            ");
            $sentenceNumero->bindParam(':anio', $anio);
            $sentenceNumero->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceNumero->execute();
            $numero = (int) $sentenceNumero->fetch(PDO::FETCH_ASSOC)['siguiente'];

            $sentence = $db->prepare("
                INSERT INTO contratos_colaborador (
                    id, id_tenant, numero, anio, id_colaborador, id_cargo, id_tipo_contrato, id_plantilla,
                    salario_mensual, periodo_pago, fecha_inicio, fecha_fin, periodo_prueba,
                    jornada_horas, lugar_desempeno, lugar_firma, fecha_firma,
                    representante_firma_digital, observaciones, id_usuario_genera
                ) VALUES (
                    :id, :id_tenant, :numero, :anio, :id_colaborador, :id_cargo, :id_tipo_contrato, :id_plantilla,
                    :salario_mensual, :periodo_pago, :fecha_inicio, :fecha_fin, :periodo_prueba,
                    :jornada_horas, :lugar_desempeno, :lugar_firma, :fecha_firma,
                    :representante_firma_digital, :observaciones, :id_usuario_genera
                )
            ");

            $salarioMensual            = Flight::request()->data['salario_mensual'] ?? null;
            $periodoPago               = Flight::request()->data['periodo_pago'] ?? null;
            $fechaInicio               = Flight::request()->data['fecha_inicio'] ?? null;
            $fechaFin                  = Flight::request()->data['fecha_fin'] ?? null;
            $periodoPrueba             = Flight::request()->data['periodo_prueba'] ?? null;
            $jornadaHoras              = Flight::request()->data['jornada_horas'] ?? null;
            $lugarDesempeno            = Flight::request()->data['lugar_desempeno'] ?? null;
            $lugarFirma                = Flight::request()->data['lugar_firma'] ?? null;
            $fechaFirma                = Flight::request()->data['fecha_firma'] ?? null;
            $representanteFirmaDigital = isset(Flight::request()->data['representante_firma_digital']) ? (int) Flight::request()->data['representante_firma_digital'] : 0;
            $observaciones             = Flight::request()->data['observaciones'] ?? null;
            $idUsuarioGenera = is_object($userData)
                ? ($userData->id ?? null)
                : ($userData['id'] ?? null);

            $idContrato = Uuid::generar();
            $sentence->bindValue(':id', $idContrato);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':numero', $numero);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':id_colaborador', $idColaborador);
            $sentence->bindParam(':id_cargo', $idCargo);
            $sentence->bindParam(':id_tipo_contrato', $idTipoContrato);
            $sentence->bindParam(':id_plantilla', $idPlantilla);
            $sentence->bindParam(':salario_mensual', $salarioMensual);
            $sentence->bindParam(':periodo_pago', $periodoPago);
            $sentence->bindParam(':fecha_inicio', $fechaInicio);
            $sentence->bindParam(':fecha_fin', $fechaFin);
            $sentence->bindParam(':periodo_prueba', $periodoPrueba);
            $sentence->bindParam(':jornada_horas', $jornadaHoras);
            $sentence->bindParam(':lugar_desempeno', $lugarDesempeno);
            $sentence->bindParam(':lugar_firma', $lugarFirma);
            $sentence->bindParam(':fecha_firma', $fechaFirma);
            $sentence->bindParam(':representante_firma_digital', $representanteFirmaDigital);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id_usuario_genera', $idUsuarioGenera);

            $sentence->execute();
            $id = $idContrato;

            // Sincronizar datos del colaborador con los del contrato
            // (cargo, tipo de contrato y salario). No se toca fecha_ingreso.
            $sentenceColab = $db->prepare("
                UPDATE colaboradores
                SET id_cargo = :id_cargo,
                    id_tipo_contrato = :id_tipo_contrato,
                    salario_mensual = :salario_mensual
                WHERE id = :id_colaborador
                  AND id_tenant = :id_tenant
            ");
            $sentenceColab->bindParam(':id_cargo', $idCargo);
            $sentenceColab->bindParam(':id_tipo_contrato', $idTipoContrato);
            $sentenceColab->bindParam(':salario_mensual', $salarioMensual);
            $sentenceColab->bindParam(':id_colaborador', $idColaborador);
            $sentenceColab->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceColab->execute();

            $db->commit();

            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en ContratosColaborador::new: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar un contrato existente.
     * No recalcula numero/anio ni re-resuelve la plantilla (datos congelados).
     * PUT /contratos-colaborador
     */
    public static function replace()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos.administrar');

            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(['error' => 'Falta el id del contrato'], 400);
                return;
            }

            $db->beginTransaction();

            $idCargo        = Flight::request()->data['id_cargo'] ?? null;
            $idTipoContrato = Flight::request()->data['id_tipo_contrato'] ?? null;

            // Resolver la plantilla según el cargo y tipo de contrato actuales
            $sentencePlantilla = $db->prepare("
                SELECT id_plantilla
                FROM cargos_plantillas_contratos
                WHERE id_cargo = :id_cargo
                  AND id_tipo_contrato = :id_tipo_contrato
                  AND activo = 1
                  AND id_tenant = :id_tenant
                LIMIT 1
            ");
            $sentencePlantilla->bindParam(':id_cargo', $idCargo);
            $sentencePlantilla->bindParam(':id_tipo_contrato', $idTipoContrato);
            $sentencePlantilla->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentencePlantilla->execute();
            $plantilla = $sentencePlantilla->fetch(PDO::FETCH_ASSOC);

            if (!$plantilla) {
                $db->rollBack();
                Flight::json(['error' => 'No existe una plantilla configurada para ese cargo y tipo de contrato'], 400);
                return;
            }
            $idPlantilla = $plantilla['id_plantilla'];

            $sentence = $db->prepare("
                UPDATE contratos_colaborador SET
                    id_cargo = :id_cargo,
                    id_tipo_contrato = :id_tipo_contrato,
                    id_plantilla = :id_plantilla,
                    salario_mensual = :salario_mensual,
                    periodo_pago = :periodo_pago,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    periodo_prueba = :periodo_prueba,
                    jornada_horas = :jornada_horas,
                    lugar_desempeno = :lugar_desempeno,
                    lugar_firma = :lugar_firma,
                    fecha_firma = :fecha_firma,
                    representante_firma_digital = :representante_firma_digital,
                    observaciones = :observaciones
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $salarioMensual            = Flight::request()->data['salario_mensual'] ?? null;
            $periodoPago               = Flight::request()->data['periodo_pago'] ?? null;
            $fechaInicio               = Flight::request()->data['fecha_inicio'] ?? null;
            $fechaFin                  = Flight::request()->data['fecha_fin'] ?? null;
            $periodoPrueba             = Flight::request()->data['periodo_prueba'] ?? null;
            $jornadaHoras              = Flight::request()->data['jornada_horas'] ?? null;
            $lugarDesempeno            = Flight::request()->data['lugar_desempeno'] ?? null;
            $lugarFirma                = Flight::request()->data['lugar_firma'] ?? null;
            $fechaFirma                = Flight::request()->data['fecha_firma'] ?? null;
            $representanteFirmaDigital = isset(Flight::request()->data['representante_firma_digital']) ? (int) Flight::request()->data['representante_firma_digital'] : 0;
            $observaciones             = Flight::request()->data['observaciones'] ?? null;

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_cargo', $idCargo);
            $sentence->bindParam(':id_tipo_contrato', $idTipoContrato);
            $sentence->bindParam(':id_plantilla', $idPlantilla);
            $sentence->bindParam(':salario_mensual', $salarioMensual);
            $sentence->bindParam(':periodo_pago', $periodoPago);
            $sentence->bindParam(':fecha_inicio', $fechaInicio);
            $sentence->bindParam(':fecha_fin', $fechaFin);
            $sentence->bindParam(':periodo_prueba', $periodoPrueba);
            $sentence->bindParam(':jornada_horas', $jornadaHoras);
            $sentence->bindParam(':lugar_desempeno', $lugarDesempeno);
            $sentence->bindParam(':lugar_firma', $lugarFirma);
            $sentence->bindParam(':fecha_firma', $fechaFirma);
            $sentence->bindParam(':representante_firma_digital', $representanteFirmaDigital);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            // Sincronizar datos del colaborador con los del contrato (cargo,
            // tipo de contrato y salario). cargo/tipo se toman del contrato
            // (datos congelados). No se toca fecha_ingreso.
            $sentenceColab = $db->prepare("
                UPDATE colaboradores c
                JOIN contratos_colaborador cc ON cc.id = :id_contrato
                SET c.id_cargo = cc.id_cargo,
                    c.id_tipo_contrato = cc.id_tipo_contrato,
                    c.salario_mensual = :salario_mensual
                WHERE c.id = cc.id_colaborador
                  AND cc.id_tenant = :id_tenant
            ");
            $sentenceColab->bindParam(':id_contrato', $id);
            $sentenceColab->bindParam(':salario_mensual', $salarioMensual);
            $sentenceColab->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceColab->execute();

            $db->commit();

            Flight::json(['id' => $id, 'message' => 'Contrato actualizado correctamente']);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en ContratosColaborador::replace: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Marcar un contrato como firmado y guardar la ruta del documento firmado.
     * PUT /contratos-colaborador/marcar-firmado
     */
    public static function marcarFirmado()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos.administrar');

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;
            $rutaDocumento = Flight::request()->data['ruta_documento_firmado'] ?? null;

            if (!$id) {
                Flight::json(['error' => 'Falta el id del contrato'], 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE contratos_colaborador
                SET firmado = 1,
                    ruta_documento_firmado = :ruta
                WHERE id = :id
                  AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':ruta', $rutaDocumento);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(['id' => $id, 'message' => 'Contrato marcado como firmado']);
        } catch (Exception $e) {
            error_log("Error en ContratosColaborador::marcarFirmado: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Desmarcar firmado (revertir).
     * PUT /contratos-colaborador/desmarcar-firmado
     */
    public static function desmarcarFirmado()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos.administrar');

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(['error' => 'Falta el id del contrato'], 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE contratos_colaborador
                SET firmado = 0,
                    ruta_documento_firmado = NULL
                WHERE id = :id
                  AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(['id' => $id, 'message' => 'Contrato desmarcado como firmado']);
        } catch (Exception $e) {
            error_log("Error en ContratosColaborador::desmarcarFirmado: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Anular un contrato (soft delete).
     * PUT /contratos-colaborador/anular
     */
    public static function anular()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos.administrar');

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(['error' => 'Falta el id del contrato'], 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE contratos_colaborador
                SET activo = 0
                WHERE id = :id
                  AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(['id' => $id, 'message' => 'Contrato anulado correctamente']);
        } catch (Exception $e) {
            error_log("Error en ContratosColaborador::anular: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}