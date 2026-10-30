<?php

namespace App\Services\QuestionPapers\Worker;

use Symfony\Component\Process\Process;
use RuntimeException;

/** Reads the operating system's process list with fixed commands (no input from anywhere is part of them). */
class SystemProcessTable implements ProcessTable
{
    public function php(): array
    {
        return PHP_OS_FAMILY === 'Windows' ? $this->windows() : $this->unix();
    }

    public function commandOf(int $pid): ?string
    {
        if ($pid <= 0) {
            return null;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->windowsCommandOf($pid);
        }

        $line = trim($this->run(['ps', '-p', (string) $pid, '-o', 'args='], allowMissingProcess: true));

        return $line === '' ? null : $line;
    }

    /** @return list<array{pid: int, command: string}> */
    private function windows(): array
    {
        try {
            return $this->windowsWmic();
        } catch (\Throwable) {
            return $this->windowsCim();
        }
    }

    /** @return list<array{pid: int, command: string}> */
    private function windowsWmic(): array
    {
        $raw = $this->run(['wmic', 'process', 'where', "name='php.exe'", 'get', 'ProcessId,CommandLine', '/format:csv'], allowMissingProcess: true);
        if (str_contains($raw, "\x00")) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        }

        $out = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'Node,') || $line === 'No Instance(s) Available.') {
                continue;
            }
            $parts = str_getcsv($line);
            if (count($parts) >= 3 && is_numeric($parts[count($parts) - 1])) {
                $pid = (int) $parts[count($parts) - 1];
                $cmd = (string) $parts[1];
                $out[] = ['pid' => $pid, 'command' => $cmd];
            }
        }

        return $out;
    }

    private function windowsCommandOf(int $pid): ?string
    {
        try {
            return $this->windowsWmicCommandOf($pid);
        } catch (\Throwable) {
            $json = trim($this->powershellJson("Get-CimInstance Win32_Process -Filter \"ProcessId={$pid}\" | Select-Object CommandLine | ConvertTo-Json -Compress"));
            $decoded = json_decode($json, true);
            if ($json !== '' && !is_array($decoded)) {
                throw new RuntimeException('The process command line could not be read.');
            }

            return is_array($decoded) && isset($decoded['CommandLine']) ? (string) $decoded['CommandLine'] : null;
        }
    }

    private function windowsWmicCommandOf(int $pid): ?string
    {
        $raw = $this->run(['wmic', 'process', 'where', "ProcessId={$pid}", 'get', 'CommandLine', '/format:csv'], allowMissingProcess: true);
        if (str_contains($raw, "\x00")) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
        }

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'Node,') || $line === 'No Instance(s) Available.') {
                continue;
            }
            $parts = str_getcsv($line);
            if (count($parts) >= 2) {
                return (string) $parts[1];
            }
        }

        return null;
    }

    /** @return list<array{pid: int, command: string}> */
    private function windowsCim(): array
    {
        $query = '$ids = @(Get-Process -Name php -ErrorAction SilentlyContinue | ForEach-Object { $_.Id }); '
            . '$processes = if ($ids.Count -gt 0) { '
            . '$filter = ($ids | ForEach-Object { "ProcessId=$_" }) -join " or "; '
            . 'Get-CimInstance Win32_Process -Filter $filter | Select-Object ProcessId,CommandLine '
            . '}; $processes | ConvertTo-Json -Compress';
        $json = trim($this->powershellJson($query));
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            if ($json !== '') {
                throw new RuntimeException('The PHP process list could not be parsed.');
            }

            return [];
        }
        $rows = isset($decoded['ProcessId']) ? [$decoded] : $decoded; // one process comes back as an object, several as a list
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['ProcessId'], $row['CommandLine'])) {
                $out[] = ['pid' => (int) $row['ProcessId'], 'command' => (string) $row['CommandLine']];
            }
        }

        return $out;
    }

    private function powershellJson(string $command): string
    {
        $utf16 = iconv('UTF-8', 'UTF-16LE', $command);
        if ($utf16 === false) {
            throw new RuntimeException('The Windows process query could not be encoded.');
        }

        return $this->run(['powershell', '-NoProfile', '-NonInteractive', '-EncodedCommand', base64_encode($utf16)]);
    }

    /** @return list<array{pid: int, command: string}> */
    private function unix(): array
    {
        $out = [];
        foreach (preg_split('/\R/', $this->run(['ps', '-eo', 'pid=,args='])) ?: [] as $line) {
            if (preg_match('/^\s*(\d+)\s+(.*)$/', $line, $m) && str_contains(strtolower($m[2]), 'php')) {
                $out[] = ['pid' => (int) $m[1], 'command' => $m[2]];
            }
        }

        return $out;
    }

    private function run(array $command, bool $allowMissingProcess = false): string
    {
        $env = null;
        if (PHP_OS_FAMILY === 'Windows') {
            $userProfile = getenv('USERPROFILE') ?: getenv('HOME') ?: 'C:\Users\Default';
            $env = array_filter([
                'SystemRoot' => getenv('SystemRoot') ?: 'C:\Windows',
                'SystemDrive' => getenv('SystemDrive') ?: 'C:',
                'USERPROFILE' => $userProfile,
                'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: ($userProfile . '\AppData\Local'),
                'APPDATA' => getenv('APPDATA') ?: ($userProfile . '\AppData\Roaming'),
                'TEMP' => getenv('TEMP') ?: 'C:\Windows\Temp',
                'TMP' => getenv('TMP') ?: 'C:\Windows\Temp',
                'PATH' => getenv('PATH') ?: '',
                'ComSpec' => getenv('ComSpec') ?: 'C:\Windows\system32\cmd.exe',
            ]);
        }

        $process = new Process($command, null, $env);
        $process->setTimeout(30);
        $process->run();

        $output = $process->getOutput();
        if (!$process->isSuccessful() && !($allowMissingProcess && ($process->getExitCode() === 1 || $process->getExitCode() === 0) && trim($output) === '')) {
            $detail = trim($output . $process->getErrorOutput());
            throw new RuntimeException('The process list could not be read: ' . ($detail !== '' ? substr($detail, 0, 200) : 'command exited with code ' . $process->getExitCode()));
        }

        return $output;
    }
}
