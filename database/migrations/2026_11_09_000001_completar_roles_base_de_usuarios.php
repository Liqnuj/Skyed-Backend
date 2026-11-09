<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El registro es uno solo para toda la plataforma, pero antes solo daba el rol
 * del módulo desde el que se registraba (casi siempre participante/deportivo).
 * Por eso en el panel Social los usuarios salían como "Participante · Deportivo".
 *
 * Esta migración completa a los usuarios que ya existen: quien tiene uno de los
 * roles base (participante o cliente) recibe también el otro. No toca a los
 * administradores ni a los asistentes que no tienen rol base.
 */
return new class extends Migration
{
    private const ROLES_BASE = ['participante' => 'deportivo', 'cliente' => 'social'];

    public function up(): void
    {
        $ids = [];
        foreach (array_keys(self::ROLES_BASE) as $nombre) {
            $id = DB::table('rol')->where('nombre_rol', $nombre)->value('id_rol');
            $ids[$nombre] = $id ?: DB::table('rol')->insertGetId(['nombre_rol' => $nombre, 'created_at' => now(), 'updated_at' => now()], 'id_rol');
        }

        $usuarios = DB::table('usuario_contexto_rol')
            ->whereIn('id_rol', array_values($ids))
            ->distinct()
            ->pluck('id_u');

        $ahora = now();
        foreach ($usuarios as $idU) {
            foreach (self::ROLES_BASE as $nombre => $contexto) {
                $existe = DB::table('usuario_contexto_rol')
                    ->where('id_u', $idU)
                    ->where('id_rol', $ids[$nombre])
                    ->where('contexto', $contexto)
                    ->exists();

                if (! $existe) {
                    DB::table('usuario_contexto_rol')->insert([
                        'id_u' => $idU,
                        'id_rol' => $ids[$nombre],
                        'contexto' => $contexto,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // No se deshace: no se puede saber cuál de los dos roles tenía antes.
    }
};
