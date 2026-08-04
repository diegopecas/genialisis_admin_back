<?php
/**
 * Generador de identificadores unicos para las PK CHAR(36) de las tablas tenant.
 *
 * El id se genera en PHP para poder devolverlo tras el INSERT (lastInsertId()
 * no aplica a una PK no autoincremental). La VERSION del UUID es un detalle
 * interno de este helper: los services llaman siempre a generar(), de modo que
 * cambiar de version se hace en un solo lugar sin tocar ningun new().
 *
 * Implementacion actual: UUID v7 (time-ordered). El timestamp en milisegundos
 * va al inicio, por lo que el string queda casi secuencial en el tiempo y
 * mejora la localidad del indice frente a un v4 puramente aleatorio. Es
 * compatible con los UUID v1 que MariaDB crea por DEFAULT uuid(): todos son
 * UUID validos y unicos entre si.
 */
class Uuid
{
    /**
     * Devuelve un identificador UUID en formato 8-4-4-4-12 (36 caracteres).
     *
     * @return string
     */
    public static function generar()
    {
        return self::v7();
    }

    /**
     * UUID v7 (RFC 9562): 48 bits de timestamp en milisegundos + version +
     * variante + bits aleatorios. Requiere PHP de 64 bits (el timestamp en ms
     * excede 32 bits).
     *
     * @return string
     */
    private static function v7()
    {
        $ms = (int) round(microtime(true) * 1000);
        $tsHex = str_pad(dechex($ms), 12, '0', STR_PAD_LEFT); // 48 bits -> 12 hex
        $bytes = hex2bin($tsHex) . random_bytes(10);          // 6 + 10 = 16 bytes
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);      // version 7
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);      // variante RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
