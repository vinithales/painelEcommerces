<?php

namespace App\Console\Commands;

use App\Actions\Checks\CheckDefinitionValidator;
use App\Jobs\RunCheckAssignmentJob;
use App\Models\CheckDefinition;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallStoreDiagnostics extends Command
{
    protected $signature = 'checks:install-store-diagnostics {--run : Enfileirar uma execucao inicial em cada loja} {--executor= : Limitar execucao inicial a http ou browser}';

    protected $description = 'Cadastra verificacoes de X-Cache e console em todas as lojas, sem duplicar registros.';

    public function handle(CheckDefinitionValidator $validator): int
    {
        if ($this->option('executor') && ! in_array($this->option('executor'), ['http', 'browser'], true)) {
            $this->error('Executor deve ser http ou browser.');

            return self::FAILURE;
        }
        $definitions = [
            [
                'name' => 'Cache da pagina inicial (HIT apos aquecimento)', 'level' => 'SITE', 'executor_type' => 'http',
                'configuration' => ['path' => '/', 'method' => 'GET', 'timeout' => 15, 'follow_redirects' => true, 'samples' => 3],
                'assertions' => [
                    ['field' => 'status_code', 'operator' => 'equals', 'expected' => 200],
                    ['field' => 'cache_status', 'operator' => 'equals', 'expected' => 'HIT'],
                ],
            ],
            [
                'name' => 'Erros de console e JavaScript da pagina inicial', 'level' => 'SITE', 'executor_type' => 'browser',
                'configuration' => ['path' => '/', 'timeout' => 30, 'wait_ms' => 5000],
                'assertions' => [
                    ['field' => 'status_code', 'operator' => 'equals', 'expected' => 200],
                    ['field' => 'console_error_count', 'operator' => 'equals', 'expected' => 0],
                    ['field' => 'page_error_count', 'operator' => 'equals', 'expected' => 0],
                ],
            ],
        ];
        $assignments = DB::transaction(function () use ($definitions, $validator) {
            $assignments = collect();
            foreach ($definitions as $data) {
                $validated = $validator->validate($data);
                $definition = CheckDefinition::firstOrCreate(['name' => $data['name']], $validated);
                foreach (Store::orderBy('id')->get(['id']) as $store) {
                    $assignment = $definition->assignments()->firstOrCreate(['store_id' => $store->id], [
                        'interval_minutes' => 1440, 'enabled' => true, 'next_run_at' => now()->addDay(),
                    ]);
                    $assignments->push($assignment);
                }
                $this->line("#{$definition->id}: {$definition->name}");
            }

            return $assignments;
        });
        $this->info($assignments->count().' associacoes cadastradas ou preservadas. Intervalo inicial: 24 horas.');
        if ($this->option('run')) {
            foreach ($assignments->where('enabled', true) as $assignment) {
                if ($this->option('executor') && $assignment->checkDefinition->executor_type !== $this->option('executor')) {
                    continue;
                }
                $execution = $assignment->executions()->create([
                    'definition_snapshot' => $assignment->checkDefinition->only(['name', 'level', 'executor_type', 'configuration', 'assertions']),
                    'status' => 'pending',
                ]);
                RunCheckAssignmentJob::dispatch($assignment->id, $execution->id);
                $this->line("Loja #{$assignment->store_id}: execucao #{$execution->id} enfileirada.");
            }
        }

        return self::SUCCESS;
    }
}
