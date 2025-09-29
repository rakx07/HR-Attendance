<?php


return [
  'cooldown_seconds' => (int) env('ATTENDANCE_COOLDOWN_SECONDS', 60),
  'cap_hours_per_day' => (float) env('ATTENDANCE_CAP_HOURS_PER_DAY', 8),

  // defaults; HR can override by ShiftWindow assignment per user
  'windows' => [
    'am_in'  => ['06:00','10:30'],
    'am_out' => ['11:00','13:30'],
    'pm_in'  => ['12:00','16:00'],
    'pm_out' => ['16:00','22:00'],
    'grace_minutes' => 10,
  ],
];
