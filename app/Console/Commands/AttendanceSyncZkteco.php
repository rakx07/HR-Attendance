<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use Jmrashed\Zkteco\Lib\ZKTeco;

class AttendanceSyncZkteco extends Command
{
    protected $signature = 'attendance:sync-zkteco
        {--ip= : Device IP (defaults to ZKTECO_IP/.env)}
        {--port= : Device port (defaults to ZKTECO_PORT/.env)}
        {--from= : From date (YYYY-MM-DD)}
        {--to= : To date (YYYY-MM-DD)}
        {--check : Only check and count logs, no DB write}
        {--summary : Show list of user IDs and timestamps}
        {--wipe=0 : If 1, clear logs on device after sync}';

    protected $description = 'Pull or check attendance logs from ZKTeco device with date filtering and summary options';

    public function handle()
    {
        $ip   = $this->option('ip') ?: env('ZKTECO_IP', '192.168.1.2');
        $port = (int)($this->option('port') ?: env('ZKTECO_PORT', 4370));
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $to   = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null;

        $zk = new ZKTeco($ip, $port);

        $this->info("🔌 Connecting to device $ip:$port ...");
        if (!$zk->connect()) {
            $this->error("❌ Could not connect to device.");
            return self::FAILURE;
        }

        $zk->disableDevice();
        $logs = $zk->getAttendance() ?: [];
        $zk->enableDevice();
        if ((int)$this->option('wipe') === 1) {
            $zk->clearAttendance();
            $this->warn("🧹 Device logs cleared after sync.");
        }
        $zk->disconnect();

        if (empty($logs)) {
            $this->warn("⚠️ No logs found on device.");
            return self::SUCCESS;
        }

        // Filter by date range
        $filtered = collect($logs)->filter(function ($log) use ($from, $to) {
            $ts = Carbon::parse($log['timestamp']);
            if ($from && $ts->lt($from)) return false;
            if ($to && $ts->gt($to)) return false;
            return true;
        })->values();

        $total = count($logs);
        $range = count($filtered);

        $this->line("🕒 Total logs on device: $total");
        if ($from || $to) {
            $this->line("📅 Logs within range: $range");
        }

        // Show summary
        if ($this->option('summary')) {
            $this->line("───────────────────────────────");
            foreach ($filtered as $log) {
                $id = $log['id'] ?? $log['uid'] ?? 'N/A';
                $time = Carbon::parse($log['timestamp'])->format('Y-m-d H:i:s');
                $this->line("👤 UserID: $id | 🕓 $time");
            }
            $this->line("───────────────────────────────");
        }

        if ($this->option('check') || $this->option('summary')) {
            $this->info("✅ Check complete — no data saved to database.");
            return self::SUCCESS;
        }

        // Write logs to DB if not in check/summary mode
        $map = User::whereNotNull('zkteco_user_id')->pluck('id', 'zkteco_user_id');
        $inserted = 0;
        foreach ($filtered as $log) {
            $devId = (int)($log['id'] ?? $log['uid'] ?? 0);
            $ts = Carbon::parse($log['timestamp']);

            DB::table('attendance_raw')->updateOrInsert(
                ['device_user_id' => $devId, 'punched_at' => $ts],
                [
                    'user_id'    => $map[$devId] ?? null,
                    'state'      => $log['state'] ?? null,
                    'device_ip'  => $ip,
                    'source'     => 'zkteco',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $inserted++;
        }

        $this->info("✅ Synced {$inserted} log(s) into attendance_raw.");
        return self::SUCCESS;
    }
}
