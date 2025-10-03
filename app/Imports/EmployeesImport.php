<?php

namespace App\Imports;

use App\Models\User;
use App\Models\ShiftWindow;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class EmployeesImport implements
    ToModel,
    WithHeadingRow,
    WithUpserts,
    WithBatchInserts,
    WithChunkReading
{
    use Importable;

    /**
     * Expected headings (case-insensitive, spaces/underscores allowed):
     * - email (required for upsert unless school_id provided)
     * - temp_password (optional on update; required on create if email/school_id does not exist)
     * - school_id (optional, used as fallback key if email is blank)
     * - zkteco_user_id (optional)
     * - last_name, first_name, middle_name (preferred)
     * - OR "name" (fallback, e.g., "Dela Cruz, Juan Santos")
     * - department_id (FK; optional)
     * - shift_window_id (FK; optional)
     * - shift_window (name; optional, used when id is blank)
     * - flexi_start, flexi_end (HH:MM; optional)
     * - active (1/0/true/false/yes/no; optional; default 1)
     */

    /** Default password for created users when temp_password is empty. */
    protected string $defaultTempPassword = 'ChangeMe123!';

    public function model(array $row)
    {
        $row = $this->normalizeRow($row);

        // Resolve/Default shift
        $shiftId = $this->resolveShiftId(
            $row['shift_window_id'] ?? null,
            $row['shift_window']    ?? null
        );

        // Find existing user by email first, else by school_id (if provided)
        $existing = null;
        if (!empty($row['email'])) {
            $existing = User::where('email', $row['email'])->first();
        }
        if (!$existing && !empty($row['school_id'])) {
            $existing = User::where('school_id', $row['school_id'])->first();
        }

        // Map name fields (supports either split names or a single "name" column)
        [$last, $first, $middle] = $this->resolveNames(
            $row['last_name']  ?? null,
            $row['first_name'] ?? null,
            $row['middle_name']?? null,
            $row['name']       ?? null
        );

        // Active flag
        $active = $this->toBool($row['active'] ?? 1);

        // Build payload
        $payload = [
            'school_id'       => $row['school_id']      ?? ($existing->school_id  ?? null),
            'zkteco_user_id'  => $row['zkteco_user_id'] ?? ($existing->zkteco_user_id ?? null),

            'last_name'       => $last   ?? ($existing->last_name   ?? null),
            'first_name'      => $first  ?? ($existing->first_name  ?? null),
            'middle_name'     => $middle ?? ($existing->middle_name ?? null),

            'department_id'   => $row['department_id'] ?? ($existing->department_id ?? null),
            'shift_window_id' => $shiftId ?? ($existing->shift_window_id ?? null),

            'flexi_start'     => $this->toTime($row['flexi_start'] ?? null) ?? ($existing->flexi_start ?? null),
            'flexi_end'       => $this->toTime($row['flexi_end']   ?? null) ?? ($existing->flexi_end   ?? null),

            'active'          => is_null($active) ? ($existing->active ?? 1) : (int)$active,
        ];

        // Create vs Update
        if ($existing) {
            // Update existing—only overwrite fields that are provided (payload already handles fallbacks)
            $existing->fill($payload);

            // If a new temp_password is given, update password
            if (!empty($row['temp_password'])) {
                $existing->password = Hash::make($row['temp_password']);
            }

            // If email provided (and different), update email (ensuring uniqueness at DB level)
            if (!empty($row['email']) && $row['email'] !== $existing->email) {
                $existing->email = $row['email'];
            }

            $existing->save();
            return $existing;
        }

        // Creating new user: need a unique email OR a unique school_id (email strongly recommended)
        $email = $row['email'] ?? null;
        $temp  = $row['temp_password'] ?? $this->defaultTempPassword;

        $user = new User();
        $user->email           = $email;
        $user->password        = Hash::make($temp);

        $user->school_id       = $payload['school_id'];
        $user->zkteco_user_id  = $payload['zkteco_user_id'];

        $user->last_name       = $payload['last_name'];
        $user->first_name      = $payload['first_name'];
        $user->middle_name     = $payload['middle_name'];

        $user->department_id   = $payload['department_id'];
        $user->shift_window_id = $payload['shift_window_id'];
        $user->flexi_start     = $payload['flexi_start'];
        $user->flexi_end       = $payload['flexi_end'];
        $user->active          = $payload['active'] ?? 1;

        $user->save();
        return $user;
    }

    /** Upsert key(s) for Maatwebsite (used when batching); we key by email. */
    public function uniqueBy()
    {
        return 'email';
    }

    /** Batch size (DB upserts per insert) */
    public function batchSize(): int
    {
        return 1000;
    }

    /** Chunk size (rows read from file per chunk) */
    public function chunkSize(): int
    {
        return 1000;
    }

    /** ---------- Helpers ---------- */

    /** Normalize row: trim keys/values, standardize key names (snake_case). */
    protected function normalizeRow(array $row): array
    {
        $norm = [];
        foreach ($row as $k => $v) {
            if (is_string($k)) {
                $key = Str::of($k)->lower()->replace([' ', '-'], '_')->value();
            } else {
                $key = $k;
            }
            $norm[$key] = is_string($v) ? trim($v) : $v;
        }

        // Lowercase email
        if (!empty($norm['email']) && is_string($norm['email'])) {
            $norm['email'] = strtolower($norm['email']);
        }

        return $norm;
    }

    /**
     * Resolve shift_window_id from:
     * 1) explicit id (preferred),
     * 2) shift_window name (exact match),
     * 3) default to first shift.
     */
    protected function resolveShiftId($shiftId, $shiftName): ?int
    {
        if (!empty($shiftId)) {
            return (int) $shiftId;
        }
        if (!empty($shiftName)) {
            $byName = ShiftWindow::where('name', $shiftName)->value('id');
            if ($byName) return (int) $byName;
        }
        // default to first shift
        return ShiftWindow::orderBy('id')->value('id');
    }

    /**
     * Resolve names. Accepts split fields OR a single "name" (e.g., "Dela Cruz, Juan Santos").
     * Returns: [last, first, middle]
     */
    protected function resolveNames($last, $first, $middle, $name): array
    {
        if ($last || $first || $middle) {
            return [$last, $first, $middle];
        }
        if ($name) {
            // Try "Last, First Middle"
            if (str_contains($name, ',')) {
                [$l, $rest] = array_map('trim', explode(',', $name, 2));
                $parts = preg_split('/\s+/', $rest);
                $f = array_shift($parts) ?? null;
                $m = $parts ? implode(' ', $parts) : null;
                return [$l ?: null, $f ?: null, $m ?: null];
            }
            // Fallback "First Middle Last" (last token is Last)
            $parts = preg_split('/\s+/', trim($name));
            $l = array_pop($parts);
            $f = array_shift($parts) ?? null;
            $m = $parts ? implode(' ', $parts) : null;
            return [$l ?: null, $f ?: null, $m ?: null];
        }
        return [null, null, null];
    }

    /** Convert various truthy/falsey strings to boolean (or null if unknown). */
    protected function toBool($val): ?bool
    {
        if (is_null($val) || $val === '') return null;
        if (is_bool($val)) return $val;
        $v = strtolower((string)$val);
        if (in_array($v, ['1','true','yes','y','active'])) return true;
        if (in_array($v, ['0','false','no','n','inactive'])) return false;
        return null;
    }

    /** Normalize HH:MM (24h). Accepts Excel time, "8:00", "08:00", "8", etc. */
    protected function toTime($val): ?string
    {
        if (empty($val) && $val !== 0) return null;

        // If numeric (Excel time), convert from days to time
        if (is_numeric($val)) {
            $minutes = (float)$val * 24 * 60;
            $h = floor($minutes / 60);
            $m = round($minutes - ($h * 60));
            return sprintf('%02d:%02d', $h % 24, $m % 60);
        }

        $str = trim((string)$val);

        // "8" or "8:30" → normalize
        if (preg_match('/^\d{1,2}(:\d{1,2})?$/', $str)) {
            [$h, $m] = array_pad(explode(':', $str, 2), 2, '00');
            return sprintf('%02d:%02d', (int)$h % 24, (int)$m % 60);
        }

        // Try Carbon parse
        try {
            $t = Carbon::parse($str);
            return $t->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
