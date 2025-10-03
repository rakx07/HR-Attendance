<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use App\Models\User;

class AttendanceSyncZKT extends Command
{
    /**
     * Examples:
     *  php artisan attendance:sync-zkteco --ip=192.168.1.10 --port=4370 --device=MAIN --days=1
     *  php artisan attendance:sync-zkteco --mock --days=2                 # seed fake punches (no device)
     *  php artisan attendance:sync-zkteco --since="2025-10-01 00:00:00"   # pull since explicit timestamp
     *  php artisan attendance:sync-zkteco --dry                           # parse logs but do not write
     */
    protected $signature = 'attendance:sync-zkteco
        {--ip= : Device IP (ENV ZKTECO_IP)}
        {--port= : Device port (ENV ZKTECO_PORT, default 4370)}
        {--device=DEFAULT : Device serial/label to store in DB}
        {--since= : Pull since timestamp (Y-m-d H:i:s); overrides --days}
        {--days=1 : Pull logs from now()-N days if --since not given}
        {--dry : Parse and show counts but do not write to DB}
        {--mock : Generate mock punches from zkteco_users/users instead of connecting to device}';

    protected $description = 'Pull attendance logs from ZKTeco (or generate mock logs) into attendance_raw with user mapping';

    public function handle(): int
    {
        $ip       = $this->option('ip')   ?: env('ZKTECO_IP', '192.168.1.2');
        $port     = (int)($this->option('port') ?: env('ZKTECO_PORT', 4370));
        $device   = (string)$this->option('device') ?: 'DEFAULT';
        $mock     = (bool)$this->option('mock');
        $dry      = (bool)$this->option('dry');

        $since = $this->option('since')
            ? CarbonImmutable::parse($this->option('since'))
            : CarbonImmutable::now()->subDays((int)$this->option('days') ?: 1);

        $this->line("Device: {$device} | Window since: {$since->toDateTimeString()} | Mode: " . ($mock ? 'MOCK' : 'LIVE') . ($dry ? ' (dry-run)' : ''));

        // Build map: (string) zkteco_user_id -> users.id  (strings to preserve leading zeros)
        $userMap = User::query()
            ->whereNotNull('zkteco_user_id')
            ->get(['id','zkteco_user_id'])
            ->reduce(function (array $carry, $u) {
                $carry[(string)$u->zkteco_user_id] = (int)$u->id;
                return $carry;
            }, []);

        // Fetch logs
        $logs = $mock
            ? $this->fetchMockLogs($since, $device)
            : $this->fetchDeviceLogs($ip, $port, $since);

        if (!$logs) {
            $this->warn('No logs returned.');
            return self::SUCCESS;
        }

        // Normalize + filter to window
        $prepared = [];
        foreach ($logs as $row) {
            $devId    = $this->stringId($row['uid'] ?? $row['id'] ?? $row['user_id'] ?? null);
            $ts       = $this->parseTs($row['timestamp'] ?? $row['time'] ?? $row['punch_time'] ?? null);
            $state    = $row['state'] ?? $row['status'] ?? null;
            $punchTyp = $row['punch_type'] ?? $row['type'] ?? null;
            if (!$devId || !$ts) continue;
            if ($ts->lt($since)) continue;

            $prepared[] = [
                'device_user_id' => $devId,
                'punched_at'     => $ts->toDateTimeString(),
                'device_sn'      => $device,
                'state'          => $state,
                'punch_type'     => $punchTyp,
            ];
        }

        if (!$prepared) {
            $this->warn('No logs within time window after normalization.');
            return self::SUCCESS;
        }

        // De-dupe by (devId, ts, device)
        $prepared = collect($prepared)
            ->unique(fn ($r) => $r['device_user_id'].'|'.$r['punched_at'].'|'.$r['device_sn'])
            ->values()
            ->all();

        $this->info('Logs to upsert: '.count($prepared));

        if ($dry) {
            $this->line('Dry-run: skipping database writes.');
            return self::SUCCESS;
        }

        // Upsert in chunks
        $now = now();
        $inserted = 0;
        $deviceIp = $mock ? null : $ip;
        $source   = $mock ? 'mock' : 'pull';

        foreach (array_chunk($prepared, 1000) as $chunk) {
            $chunk = array_map(function ($r) use ($userMap, $now, $deviceIp, $source) {
                $userId = $userMap[$this->stringId($r['device_user_id'])] ?? null;
                return [
                    'device_user_id' => $this->stringId($r['device_user_id']),
                    'punched_at'     => $r['punched_at'],
                    'device_sn'      => $r['device_sn'],
                    'user_id'        => $userId,
                    'state'          => $r['state'] ?? null,
                    'punch_type'     => $r['punch_type'] ?? null,
                    'source'         => $source,
                    'device_ip'      => $deviceIp,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }, $chunk);

            DB::table('attendance_raw')->upsert(
                $chunk,
                ['device_user_id','punched_at','device_sn'],
                ['user_id','state','punch_type','source','device_ip','updated_at']
            );

            $inserted += count($chunk);
        }

        // Backfill user_id for any existing raw rows that lacked a mapping earlier
        DB::statement("
            UPDATE attendance_raw ar
            JOIN users u ON u.zkteco_user_id = ar.device_user_id
            SET ar.user_id = u.id
            WHERE ar.user_id IS NULL
        ");

        $this->info("Sync complete. Upserted rows (chunks): {$inserted}.");
        return self::SUCCESS;
    }

    /**
     * LIVE: read from real device using the jmrashed/zkteco-php SDK.
     */
    private function fetchDeviceLogs(string $ip, int $port, CarbonImmutable $since): array
    {
        // Lazy check to avoid errors in environments without the SDK
        if (!class_exists(\Jmrashed\Zkteco\Lib\ZKTeco::class)) {
            $this->error('ZKTeco SDK not installed. Run: composer require jmrashed/zkteco-php');
            return [];
        }

        $logs = [];
        try {
            $zk = new \Jmrashed\Zkteco\Lib\ZKTeco($ip, $port);

            if (!$zk->connect()) {
                $this->error("Cannot connect to {$ip}:{$port}");
                return [];
            }

            $zk->disableDevice();
            $raw = $zk->getAttendance() ?: [];
            $zk->enableDevice();
            $zk->disconnect();

            foreach ($raw as $row) {
                // Typical SDK row: ['uid'=>..., 'state'=>..., 'timestamp'=>'Y-m-d H:i:s']
                $logs[] = [
                    'uid'       => $row['uid'] ?? $row['id'] ?? null,
                    'timestamp' => $row['timestamp'] ?? $row['time'] ?? null,
                    'state'     => $row['state'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            $this->error('ZKTeco read failed: '.$e->getMessage());
            return [];
        }

        return $logs;
    }

    /**
     * MOCK: synthesize punches for testing without a device.
     * Generates AM-in / PM-out for each user (zkteco_user_id) since $since.
     */
    private function fetchMockLogs(CarbonImmutable $since, string $device): array
    {
        $uids = User::query()
            ->whereNotNull('zkteco_user_id')
            ->pluck('zkteco_user_id')
            ->map(fn ($v) => (string)$v)
            ->values()
            ->all();

        // fallback to zkteco_users table if no users mapped yet
        if (!$uids) {
            $uids = DB::table('zkteco_users')->pluck('device_user_id')->map(fn ($v) => (string)$v)->all();
        }

        if (!$uids) {
            $this->warn('MOCK: no users found to generate punches for.');
            return [];
        }

        $logs = [];
        $days  = CarbonImmutable::now()->diffInDays($since) + 1;
        $start = $since->startOfDay();

        foreach ($uids as $uid) {
            for ($d = 0; $d < $days; $d++) {
                $day = $start->addDays($d);
                $amIn  = $day->setTime(8,  0)->addMinutes(random_int(0, 5));
                $pmOut = $day->setTime(17, 0)->subMinutes(random_int(0, 5));
                $logs[] = ['uid' => $uid, 'timestamp' => $amIn->toDateTimeString(),  'state' => 0, 'punch_type' => 'in'];
                $logs[] = ['uid' => $uid, 'timestamp' => $pmOut->toDateTimeString(), 'state' => 0, 'punch_type' => 'out'];
            }
        }

        $this->info('MOCK: generated '.count($logs).' synthetic punches across '.count($uids).' users.');
        return $logs;
    }

    private function parseTs($value): ?Carbon
    {
        if (!$value) return null;
        try { return Carbon::parse($value); } catch (\Throwable) { return null; }
    }

    private function stringId($value): ?string
    {
        return $value === null ? null : (string)$value; // keep as string to preserve leading zeros
    }
}
