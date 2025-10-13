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
use Maatwebsite\Excel\Concerns\WithChunkReading;

class EmployeesImport implements
    ToModel,
    WithHeadingRow,
    WithChunkReading
{
    use Importable;

    protected string $defaultTempPassword = 'ChangeMe123!';

    // Counters
    protected int $created = 0;
    protected int $updated = 0;
    protected int $duplicated = 0; // when both email AND school_id match to an existing row in the same file (rare)
    protected int $skipped = 0;    // no email and no school_id
    protected int $failed = 0;
    protected array $failMessages = [];

    public function model(array $row)
    {
        try {
            $row = $this->normalizeRow($row);

            $shiftId = $this->resolveShiftId(
                $row['shift_window_id'] ?? null,
                $row['shift_window']    ?? null
            );

            // Find existing by email, then by school_id
            $existing = null;
            if (!empty($row['email'])) {
                $existing = User::where('email', strtolower($row['email']))->first();
            }
            if (!$existing && !empty($row['school_id'])) {
                $existing = User::where('school_id', $row['school_id'])->first();
            }

            [$last, $first, $middle] = $this->resolveNames(
                $row['last_name']  ?? null,
                $row['first_name'] ?? null,
                $row['middle_name']?? null,
                $row['name']       ?? null
            );

            $active = $this->toBool($row['active'] ?? 1);

            $payload = [
                'school_id'       => $row['school_id']      ?? ($existing->school_id      ?? null),
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

            // UPDATE
            if ($existing) {
                // If both email and school_id match an existing record, we can consider it a “duplicate row” in terms of new data.
                $isExactDuplicate =
                    (empty($row['email']) || strtolower($row['email']) === strtolower((string)$existing->email)) &&
                    (empty($row['school_id']) || (string)$row['school_id'] === (string)$existing->school_id);

                $existing->fill($payload);

                if (!empty($row['temp_password'])) {
                    $existing->password = Hash::make($row['temp_password']);
                }

                if (!empty($row['email']) && strtolower($row['email']) !== strtolower((string)$existing->email)) {
                    $existing->email = strtolower($row['email']);
                }

                $existing->save();

                if ($isExactDuplicate) {
                    $this->duplicated++;
                } else {
                    $this->updated++;
                }

                return $existing;
            }

            // CREATE (allow no email if school_id present)
            $email    = !empty($row['email']) ? strtolower($row['email']) : null;
            $schoolId = $payload['school_id'] ?? null;

            if (empty($email) && empty($schoolId)) {
                // nothing to anchor identity → skip
                $this->skipped++;
                return null;
            }

            $temp = $row['temp_password'] ?? $this->defaultTempPassword;

            $user = new User();
            $user->setRawAttributes([], true); // ensure clean attribute set

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
            $this->created++;

            return $user;

        } catch (\Throwable $e) {
            $this->failed++;
            if (count($this->failMessages) < 50) {
                $who = $row['email'] ?? ($row['school_id'] ?? '[no key]');
                $this->failMessages[] = "{$who}: " . $e->getMessage();
            }
            // skip this row and continue
            return null;
        }
    }

    public function chunkSize(): int { return 1000; }

    public function summary(): array
    {
        return [
            'created'       => $this->created,
            'updated'       => $this->updated,
            'duplicated'    => $this->duplicated,
            'skipped'       => $this->skipped,
            'failed'        => $this->failed,
            'fail_messages' => $this->failMessages,
        ];
    }

    // ---------- helpers ----------

    protected function normalizeRow(array $row): array
    {
        $norm = [];
        foreach ($row as $k => $v) {
            $key = is_string($k)
                ? Str::of($k)->lower()->replace([' ', '-'], '_')->value()
                : $k;
            $norm[$key] = is_string($v) ? trim($v) : $v;
        }

        if (!empty($norm['email']) && is_string($norm['email'])) {
            $norm['email'] = strtolower($norm['email']);
        }

        return $norm;
    }

    protected function resolveShiftId($shiftId, $shiftName): ?int
    {
        if (!empty($shiftId)) return (int) $shiftId;

        if (!empty($shiftName)) {
            $byName = ShiftWindow::where('name', $shiftName)->value('id');
            if ($byName) return (int) $byName;
        }

        // Prefer Shift #2
        $preferTwo = ShiftWindow::where('id', 2)->value('id');
        if ($preferTwo) return (int) $preferTwo;

        // Fallback to first
        return (int) ShiftWindow::orderBy('id')->value('id');
    }

    protected function resolveNames($last, $first, $middle, $name): array
    {
        if ($last || $first || $middle) return [$last, $first, $middle];

        if ($name) {
            if (str_contains($name, ',')) {
                [$l, $rest] = array_map('trim', explode(',', $name, 2));
                $parts = preg_split('/\s+/', $rest);
                $f = array_shift($parts) ?? null;
                $m = $parts ? implode(' ', $parts) : null;
                return [$l ?: null, $f ?: null, $m ?: null];
            }

            $parts = preg_split('/\s+/', trim($name));
            $l = array_pop($parts);
            $f = array_shift($parts) ?? null;
            $m = $parts ? implode(' ', $parts) : null;
            return [$l ?: null, $f ?: null, $m ?: null];
        }

        return [null, null, null];
    }

    protected function toBool($val): ?bool
    {
        if ($val === null || $val === '') return null;
        if (is_bool($val)) return $val;
        $v = strtolower((string)$val);
        if (in_array($v, ['1','true','yes','y','active'])) return true;
        if (in_array($v, ['0','false','no','n','inactive'])) return false;
        return null;
    }

    protected function toTime($val): ?string
    {
        if ($val === '' || $val === null) return null;

        if (is_numeric($val)) {
            $minutes = (float)$val * 24 * 60;
            $h = floor($minutes / 60);
            $m = round($minutes - ($h * 60));
            return sprintf('%02d:%02d', $h % 24, $m % 60);
        }

        $str = trim((string)$val);
        if (preg_match('/^\d{1,2}(:\d{1,2})?$/', $str)) {
            [$h, $m] = array_pad(explode(':', $str, 2), 2, '00');
            return sprintf('%02d:%02d', (int)$h % 24, (int)$m % 60);
        }

        try {
            return Carbon::parse($str)->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
