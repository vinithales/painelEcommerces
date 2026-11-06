<?php

namespace App\Services\Checks\Executors;

use App\Models\Store;
use App\Support\Redactor;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class SshExecutor
{
    public function execute(Store $store, array $configuration): array
    {
        $profile = $store->ssh_profile;
        if (! $profile && ! $store->hasSshPasswordConnection()) {
            throw new RuntimeException('Configure a conexão SSH informando IP, usuário e senha no cadastro da loja ou uma chave privada em Diagnóstico.');
        }
        $profile ??= ['host' => $store->ssh_ip, 'user' => $store->ssh_user, 'port' => 22];

        $usePassword = $store->hasSshPasswordFor($profile['host'], $profile['user']);
        if (! $usePassword && empty($profile['private_key'])) {
            throw new RuntimeException('Confira o IP, o usuário e a senha SSH no cadastro e no diagnóstico da loja.');
        }

        $directory = sys_get_temp_dir().'/check-ssh-'.bin2hex(random_bytes(16));
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Falha ao preparar a conexão SSH.');
        }

        try {
            $identity = $directory.'/identity';
            $hosts = $directory.'/hosts';
            $askpass = $directory.'/askpass';
            if (isset($profile['host_key'])) {
                if (file_put_contents($hosts, 'check-target '.$profile['host_key']."\n") === false) {
                    throw new RuntimeException('Falha ao preparar a chave do servidor.');
                }
                $trust = ['-o', 'StrictHostKeyChecking=yes', '-o', 'HostKeyAlias=check-target', '-o', 'UserKnownHostsFile='.$hosts];
            } else {
                $trustDirectory = storage_path('app/ssh-known-hosts');
                if (! is_dir($trustDirectory) && ! @mkdir($trustDirectory, 0700, true) && ! is_dir($trustDirectory)) {
                    throw new RuntimeException('Não foi possível preparar o registro de servidores SSH.');
                }
                $knownHosts = $trustDirectory.'/'.hash('sha256', $profile['host'].':'.$profile['port']);
                if (! touch($knownHosts) || ! is_writable($knownHosts)) {
                    throw new RuntimeException('Não foi possível persistir a identificação do servidor SSH.');
                }
                $trust = ['-o', 'StrictHostKeyChecking=accept-new', '-o', 'UserKnownHostsFile='.$knownHosts];
            }
            $env = null;
            if ($usePassword) {
                $helper = "#!/bin/sh\n".'printf \'%s\n\' "$CHECK_SSH_PASSWORD"'."\n";
                if (file_put_contents($askpass, $helper) === false || ! chmod($askpass, 0700)) {
                    throw new RuntimeException('Falha ao preparar a autenticação SSH.');
                }
                $env = ['SSH_ASKPASS' => $askpass, 'SSH_ASKPASS_REQUIRE' => 'force', 'CHECK_SSH_PASSWORD' => $store->ssh_password];
                $auth = ['-o', 'PreferredAuthentications=password', '-o', 'NumberOfPasswordPrompts=1'];
            } else {
                if (file_put_contents($identity, str_replace("\r", '', trim($profile['private_key']))."\n") === false || ! chmod($identity, 0600)) {
                    throw new RuntimeException('Falha ao preparar a chave SSH.');
                }
                $auth = ['-o', 'BatchMode=yes', '-i', $identity];
            }

            $process = new Process([
                'ssh', '-F', '/dev/null', '-T', ...$auth, '-o', 'IdentitiesOnly=yes',
                ...$trust, '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'LogLevel=ERROR',
                '-p', (string) $profile['port'], '-l', $profile['user'],
                '--', $profile['host'], 'sh -s',
            ], null, $env);
            $process->setTimeout($configuration['timeout']);
            $process->setInput(str_replace("\r\n", "\n", $configuration['script'])."\n");
            $start = microtime(true);
            $output = ['out' => '', 'err' => ''];
            $process->start();
            try {
                foreach ($process as $stream => $chunk) {
                    if (strlen($output[$stream]) + strlen($chunk) > 65536) {
                        throw new RuntimeException('A saída SSH excedeu 64 KiB.');
                    }
                    $output[$stream] .= $chunk;
                }
            } finally {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
            if ($process->getExitCode() === 255) {
                $reason = match (true) {
                    str_contains($output['err'], 'Host key verification failed'), str_contains($output['err'], 'HOST IDENTIFICATION HAS CHANGED') => 'A chave do servidor SSH diverge da esperada. Confirme a chave pública com o administrador e atualize-a em Diagnóstico.',
                    str_contains($output['err'], 'Permission denied') => 'O servidor recusou a autenticação SSH. Confira o usuário e a senha ou chave privada.',
                    str_contains($output['err'], 'Connection refused') => 'O servidor recusou a conexão SSH. Confira o IP e a porta.',
                    default => 'Falha na sessão SSH. Confira o endereço, a porta e a disponibilidade do servidor.',
                };
                throw new RuntimeException($reason);
            }

            return [
                'exit_code' => $process->getExitCode(),
                'stdout' => Redactor::clean(mb_scrub($output['out'], 'UTF-8')),
                'stderr' => Redactor::clean(mb_scrub($output['err'], 'UTF-8')),
                'response_time' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (ProcessTimedOutException) {
            throw new RuntimeException('Tempo limite da sessão SSH excedido.');
        } finally {
            foreach ([$identity, $hosts, $askpass] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }
}
