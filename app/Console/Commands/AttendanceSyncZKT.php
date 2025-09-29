<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Jmrashed\Zkteco\Lib\ZKTeco;
use Carbon\Carbon;
use App\Models\User;

class AttendanceSyncZKT extends Command
{
    protected $signature = 'attendance:sync-zkteco {--ip=} {--port=}';
    protected $description = 'Pull raw attendance logs from ZKTeco device';

    public function handle()
    {
        $ip   = $this->option('ip')   ?: env('ZKTECO_IP', '192.168.1.2');
        $port = (int)($this->option('port') ?: env('ZKTECO_PORT', 4370));

        $zk = new ZKTeco($ip, $port);
        if (!$zk->connect()) { $this->error("Cannot connect $ip:$port"); return self::FAILURE; }

        $zk->disableDevice();
        $logs = $zk->getAttendance() ?: [];
        $zk->enableDevice();
        $zk->disconnect();

        $map = User::whereNotNull('zkteco_user_id')->pluck('id', 'zkteco_user_id');

        $i = 0;
        foreach ($logs as $log) {
            $devId = (int)($log['id'] ?? $log['uid'] ?? 0);
            $ts = Carbon::parse($log['timestamp']);

            DB::table('attendance_raw')->updateOrInsert(
                ['device_user_id' => $devId, 'punched_at' => $ts],
                [
                    'user_id'   => $map[$devId] ?? null,
                    'state'     => $log['state'] ?? null,
                    'device_ip' => $ip,
                    'updated_at'=> now(), 'created_at' => now(),
                ]
            ) && $i++;
        }

        $this->info("Synced raw logs: $i");
        return self::SUCCESS;
    }
}
