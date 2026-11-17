<?php

return [

    /*
    | Database connection to back up (defaults to the app's default connection).
    */
    'connection' => env('BACKUP_CONNECTION'),

    /*
    | Folder the compressed dumps are written to. Keep it outside public/.
    */
    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),

    /*
    | Full path to mysqldump. Leave empty to search PATH and the usual
    | Homebrew / Linux install locations (cron runs with a minimal PATH).
    */
    'mysqldump' => env('BACKUP_MYSQLDUMP_PATH'),

    /*
    | Optional filesystem disk (see config/filesystems.php, e.g. "s3") that each
    | backup is also copied to, so a copy survives if this server is lost.
    */
    'copy_to_disk' => env('BACKUP_COPY_DISK'),

    /*
    | Backups older than this many days are deleted, but the newest
    | "keep_min" backups are always kept regardless of age.
    */
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
    'keep_min' => (int) env('BACKUP_KEEP_MIN', 7),

    /*
    | Daily schedule time (24h) in the given timezone, and dump timeout in seconds.
    | The app itself runs on UTC, so the timezone keeps 23:59 at local midnight.
    */
    'daily_at' => env('BACKUP_DAILY_AT', '23:59'),
    'timezone' => env('BACKUP_TIMEZONE', 'Africa/Harare'),
    'timeout' => (int) env('BACKUP_TIMEOUT', 1800),

];
