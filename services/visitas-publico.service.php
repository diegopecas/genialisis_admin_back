<?php
/**
 * Flujo publico del taller. Son las unicas rutas del modulo que NO exigen
 * sesion: quien responde es el equipo del jardin, que no tiene usuario.
 *
 * La credencial es el token de la visita, y toda entrada pasa por
 * resolverVisita(): si el token no existe o la visita esta cerrada, no se
 * avanza. Para las operaciones que escriben sobre un participante se valida
 * ademas que ese participante sea de esa visita.
 */
class VisitasPublico
{
    /**
     * Resuelve el token o corta la peticion. Devuelve la visita.
     * Un token invalido y una visita cerrada responden lo mismo a proposito:
     * no hay por que decirle a quien prueba tokens cual de las dos fue.
     */
    private static function resolverVisita($token)
    {
        if (empty($token)) {
            Flight::json(array('error' => 'Enlace inválido'), 404);
            exit;
        }

        $visita = Visitas::porToken($token);

        if (!$visita) {
            Flight::json(array('error' => 'Este enlace no está disponible'), 404);
            exit;
        }

        return $visita;
    }

    // Valida que el participante sea de la visita, o corta.
    private static function exigirParticipante($idParticipante, $idVisita)
    {
        if (empty($idParticipante) || !VisitasParticipantes::perteneceAVisita($idParticipante, $idVisita)) {
            Flight::json(array('error' => 'No encontramos tus datos en esta visita'), 404);
            exit;
        }
    }

    /**
     * Portada del enlace: datos de la visita y participantes ya registrados.
     * No devuelve el token ni el id del cliente.
     */
    public static function getVisita($token)
    {
        try {
            $visita = self::resolverVisita($token);

            // No se devuelve el listado de participantes: con el documento
            // como identidad, publicar quien esta registrado seria darle a
            // cualquiera la mitad del camino para entrar como otro.
            Flight::json(array(
                'id'             => $visita['id'],
                'nombre'         => $visita['nombre'],
                'fecha'          => $visita['fecha'],
                'nombre_cliente' => $visita['nombre_cliente'],
            ));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::getVisita: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al abrir el enlace'), 500);
        }
    }

    /**
     * Identificacion por documento. Es la puerta de entrada del enlace:
     * si el documento ya esta en la visita devuelve al participante con lo
     * que lleva respondido; si no, avisa que toca registrarse.
     *
     * Se responde 200 en los dos casos: que exista o no un documento no es
     * un error, es el resultado de la busqueda.
     */
    public static function identificar()
    {
        try {
            $token = Flight::request()->data['token'] ?? null;
            $visita = self::resolverVisita($token);

            $documento = Flight::request()->data['numero_documento'] ?? '';
            $documento = trim($documento);

            if ($documento === '') {
                Flight::json(array('error' => 'Escribe tu número de documento'), 400);
                return;
            }

            $idParticipante = VisitasParticipantes::buscarPorDocumento($visita['id'], $documento);

            if (!$idParticipante) {
                Flight::json(array('encontrado' => false));
                return;
            }

            $participante = null;
            foreach (VisitasParticipantes::listar($visita['id']) as $fila) {
                if ($fila['id'] === $idParticipante) {
                    $participante = $fila;
                    break;
                }
            }

            Flight::json(array(
                'encontrado'   => true,
                'participante' => $participante,
                'estado'       => VisitasParticipantes::estado($idParticipante),
            ));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::identificar: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al identificarte'), 500);
        }
    }

    /**
     * Hoja de presentacion: crea o actualiza al participante.
     * Devuelve el id, que el celular guarda para no volver a identificarse.
     */
    public static function guardarParticipante()
    {
        try {
            $token = Flight::request()->data['token'] ?? null;
            $visita = self::resolverVisita($token);

            $idExistente = Flight::request()->data['id_participante'] ?? null;

            if ($idExistente) {
                self::exigirParticipante($idExistente, $visita['id']);
            }

            $datos = array(
                'numero_documento' => Flight::request()->data['numero_documento'] ?? '',
                'nombre'       => Flight::request()->data['nombre'] ?? '',
                'sobrenombre'  => Flight::request()->data['sobrenombre'] ?? null,
                'cargo'        => Flight::request()->data['cargo'] ?? null,
                'rol_encuesta' => Flight::request()->data['rol_encuesta'] ?? 'docente',
                'grupos'       => Flight::request()->data['grupos'] ?? array(),
            );

            $id = VisitasParticipantes::guardar($visita['id'], $datos, $idExistente);

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::guardarParticipante: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Estado del participante: sus datos y que cuestionarios ya respondio.
     * Con esto el menu del celular marca pendiente / respondido.
     */
    public static function getParticipante($token, $idParticipante)
    {
        try {
            $visita = self::resolverVisita($token);
            self::exigirParticipante($idParticipante, $visita['id']);

            $participante = null;
            foreach (VisitasParticipantes::listar($visita['id']) as $fila) {
                if ($fila['id'] === $idParticipante) {
                    $participante = $fila;
                    break;
                }
            }

            Flight::json(array(
                'participante' => $participante,
                'estado'       => VisitasParticipantes::estado($idParticipante),
            ));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::getParticipante: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al cargar tus datos'), 500);
        }
    }

    // Encuesta de trabajo: items del rol del participante y lo ya respondido.
    public static function getEncuesta($token, $idParticipante)
    {
        try {
            $visita = self::resolverVisita($token);
            self::exigirParticipante($idParticipante, $visita['id']);

            $rol = 'docente';
            foreach (VisitasParticipantes::listar($visita['id']) as $fila) {
                if ($fila['id'] === $idParticipante) {
                    $rol = $fila['rol_encuesta'];
                    break;
                }
            }

            Flight::json(array(
                'rol'       => $rol,
                'items'     => VisitasEncuestaItems::listar($rol),
                'respuesta' => VisitasRespuestas::encuestaDeParticipante($idParticipante),
            ));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::getEncuesta: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al cargar la encuesta'), 500);
        }
    }

    // Guarda la encuesta de trabajo del participante.
    public static function guardarEncuesta()
    {
        try {
            $token = Flight::request()->data['token'] ?? null;
            $visita = self::resolverVisita($token);

            $idParticipante = Flight::request()->data['id_participante'] ?? null;
            self::exigirParticipante($idParticipante, $visita['id']);

            // El rol sale del participante, no del cuerpo: si viniera del
            // cliente se podrian meter respuestas de secciones ajenas.
            $rol = 'docente';
            foreach (VisitasParticipantes::listar($visita['id']) as $fila) {
                if ($fila['id'] === $idParticipante) {
                    $rol = $fila['rol_encuesta'];
                    break;
                }
            }

            $id = VisitasRespuestas::guardarEncuesta(
                $idParticipante,
                $rol,
                Flight::request()->data['respuestas'] ?? array(),
                Flight::request()->data['extras'] ?? array(),
                Flight::request()->data['comentario_final'] ?? null
            );

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::guardarEncuesta: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Ideas ya guardadas por el participante.
    public static function getIdeas($token, $idParticipante)
    {
        try {
            $visita = self::resolverVisita($token);
            self::exigirParticipante($idParticipante, $visita['id']);

            Flight::json(array(
                'respuesta' => VisitasRespuestas::ideasDeParticipante($idParticipante),
            ));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::getIdeas: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al cargar las ideas'), 500);
        }
    }

    // Guarda las cuatro ideas del participante.
    public static function guardarIdeas()
    {
        try {
            $token = Flight::request()->data['token'] ?? null;
            $visita = self::resolverVisita($token);

            $idParticipante = Flight::request()->data['id_participante'] ?? null;
            self::exigirParticipante($idParticipante, $visita['id']);

            $id = VisitasRespuestas::guardarIdeas($idParticipante, array(
                'idea_directora'   => Flight::request()->data['idea_directora'] ?? null,
                'idea_docentes'    => Flight::request()->data['idea_docentes'] ?? null,
                'idea_estudiantes' => Flight::request()->data['idea_estudiantes'] ?? null,
                'idea_padres'      => Flight::request()->data['idea_padres'] ?? null,
            ));

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::guardarIdeas: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Preguntas de caracter y lo ya respondido.
    public static function getCaracter($token, $idParticipante)
    {
        try {
            $visita = self::resolverVisita($token);
            self::exigirParticipante($idParticipante, $visita['id']);

            Flight::json(array(
                'items'      => VisitasCaracterItems::listar(),
                'respuestas' => VisitasRespuestas::caracterDeParticipante($idParticipante),
            ));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::getCaracter: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al cargar las preguntas'), 500);
        }
    }

    // Guarda las respuestas de caracter.
    public static function guardarCaracter()
    {
        try {
            $token = Flight::request()->data['token'] ?? null;
            $visita = self::resolverVisita($token);

            $idParticipante = Flight::request()->data['id_participante'] ?? null;
            self::exigirParticipante($idParticipante, $visita['id']);

            VisitasRespuestas::guardarCaracter(
                $idParticipante,
                Flight::request()->data['respuestas'] ?? array()
            );

            Flight::json(array('id' => $idParticipante));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::guardarCaracter: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Cuestionario de calificacion: items del catalogo y los colaboradores de
     * la visita, que son el bloque "uno por uno".
     * No pide id de participante: la calificacion es anonima.
     */
    public static function getCalificacion($token)
    {
        try {
            $visita = self::resolverVisita($token);

            Flight::json(array(
                'items'         => VisitasCalificacionItems::listar(),
                'colaboradores' => VisitasColaboradores::listar($visita['id']),
            ));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::getCalificacion: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al cargar la calificación'), 500);
        }
    }

    // Registra una calificacion anonima de la visita.
    public static function guardarCalificacion()
    {
        try {
            $token = Flight::request()->data['token'] ?? null;
            $visita = self::resolverVisita($token);

            $id = VisitasCalificaciones::registrar(
                $visita['id'],
                Flight::request()->data['detalle'] ?? array(),
                Flight::request()->data['colaboradores'] ?? array()
            );

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en VisitasPublico::guardarCalificacion: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
