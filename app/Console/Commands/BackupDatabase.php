<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cadangan database MySQL dengan mysqldump, dikompres gzip ke disk `local` (storage/app/private/backups),
 * hanya `simker.backup.keep` file terbaru yang disimpan. Dijadwalkan harian (routes/console.php).
 * Kata sandi database ditulis ke file opsi sementara (izin 600), bukan argumen, agar tidak terlihat
 * di daftar proses server. Keluaran mysqldump langsung dialirkan ke gzip tanpa file .sql mentah.
 */
#[Signature('simker:backup-db {--connection= : Koneksi database (bawaan: koneksi utama)}')]
#[Description('Cadangkan database ke storage/app/private/backups (gzip)')]
class BackupDatabase extends Command
{
    public const PREFIX = 'simker-';

    public const EXTENSION = '.sql.gz';

    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $db = config("database.connections.{$connection}");

        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->components->error("Koneksi \"{$connection}\" bukan MySQL/MariaDB. Cadangan hanya untuk MySQL.");

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $folder = config('simker.backup.path');
        $disk->makeDirectory($folder);

        $name = self::PREFIX.now()->format('Y-m-d-His').self::EXTENSION;
        $target = $disk->path($folder.'/'.$name);
        $partial = $target.'.part';
        $options = $disk->path($folder.'/.mysqldump-'.Str::random(16).'.cnf');

        try {
            file_put_contents($options, $this->optionFile($db));
            chmod($options, 0600);

            $gzip = gzopen($partial, 'wb6');
            $result = Process::timeout(config('simker.backup.timeout'))
                ->start($this->command($options, $db), function (string $type, string $output) use ($gzip) {
                    if ($type === 'out') {
                        gzwrite($gzip, $output);
                    }
                })
                ->wait();
            gzclose($gzip);

            if ($result->failed()) {
                @unlink($partial);
                $this->components->error('mysqldump gagal: '.trim($result->errorOutput() ?: $result->output()));
                if ($result->exitCode() === 127) {
                    $this->line('mysqldump tidak ditemukan. Isi SIMKER_MYSQLDUMP dengan path lengkapnya, atau pakai cadangan hPanel (lihat docs/DEPLOY.md).');
                }

                return self::FAILURE;
            }

            rename($partial, $target);
        } catch (Throwable $e) {
            @unlink($partial);
            $this->components->error('Cadangan gagal: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($options);
        }

        $deleted = $this->prune($folder);

        $this->components->info("Cadangan tersimpan: storage/app/private/{$folder}/{$name}");
        if ($deleted > 0) {
            $this->line("{$deleted} cadangan lama dihapus.");
        }

        return self::SUCCESS;
    }

    /** Nilai berisi spasi/tanda khusus diberi kutip ganda; \ dan " di-escape (format file opsi MySQL). */
    private function optionFile(array $db): string
    {
        $quote = fn ($value) => '"'.addcslashes((string) $value, '\\"').'"';

        $lines = ['[client]', 'user='.$quote($db['username']), 'password='.$quote($db['password'] ?? '')];
        if (filled($db['unix_socket'] ?? null)) {
            $lines[] = 'socket='.$quote($db['unix_socket']);
        } else {
            $lines[] = 'host='.$quote($db['host']);
            $lines[] = 'port='.(int) ($db['port'] ?? 3306);
        }

        return implode("\n", $lines)."\n";
    }

    private function command(string $options, array $db): array
    {
        return [
            config('simker.backup.mysqldump'),
            // Harus argumen pertama.
            '--defaults-extra-file='.$options,
            '--single-transaction',
            '--quick',
            // Hosting bersama umumnya tidak memberi hak PROCESS yang dibutuhkan info tablespace.
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            $db['database'],
        ];
    }

    private function prune(string $folder): int
    {
        $disk = Storage::disk('local');

        $old = collect($disk->files($folder))
            ->filter(fn (string $path) => str_starts_with(basename($path), self::PREFIX) && str_ends_with($path, self::EXTENSION))
            ->sortDesc()
            ->slice(max(1, (int) config('simker.backup.keep')));

        $disk->delete($old->all());

        return $old->count();
    }
}
