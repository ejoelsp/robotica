<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegistrarBackup extends Command
{
    protected $signature = 'backup:registrar {archivo} {hash} {bytes} {estado=OK}';

    protected $description = 'Registra un backup en la tabla sistema.backups_log';

    public function handle()
    {
        $archivo = $this->argument('archivo');
        $hash    = $this->argument('hash');
        $bytes   = $this->argument('bytes');
        $estado  = $this->argument('estado');

        DB::table('sistema.backups_log')->insert([
            'archivo'    => $archivo,
            'hash'       => $hash,
            'bytes'      => $bytes,
            'estado'     => $estado,
            'creado_en'  => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("Backup registrado correctamente");
    }
}
