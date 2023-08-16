<?php

namespace Laravel\Pulse\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Contracts\SupportsSlowJobs;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\ShouldNotReportUsage;
use Livewire\Component;
use RuntimeException;

class SlowJobs extends Component
{
    use HasPeriod;
    use ShouldNotReportUsage;

    /**
     * Render the component.
     */
    public function render(Storage $storage): Renderable
    {
        if (! $storage instanceof SupportsSlowJobs) {
            // TODO return an "unsupported" card.
            throw new RuntimeException('Storage driver does not support slow jobs.');
        }

        [$slowJobs, $time, $runAt] = $this->slowJobs($storage);

        $this->dispatch('slow-jobs:dataLoaded');

        return view('pulse::livewire.slow-jobs', [
            'time' => $time,
            'runAt' => $runAt,
            'slowJobs' => $slowJobs,
        ]);
    }

    /**
     * Render the placeholder.
     */
    public function placeholder(): Renderable
    {
        return view('pulse::components.placeholder', ['class' => 'col-span-3']);
    }

    /**
     * The slow jobs.
     */
    protected function slowJobs(Storage&SupportsSlowJobs $storage): array
    {
        return Cache::remember("illuminate:pulse:slow-jobs:{$this->period}", $this->periodCacheDuration(), function () use ($storage) {
            $now = new CarbonImmutable;

            $start = hrtime(true);

            $slowJobs = $storage->slowJobs($this->periodAsInterval());

            // $slowJobs = DB::table('pulse_jobs')
            //     ->selectRaw('`job`, COUNT(*) as count, MAX(duration) AS slowest')
            //     ->where('date', '>=', $now->subHours($this->periodAsHours())->toDateTimeString())
            //     ->where('duration', '>=', config('pulse.slow_job_threshold'))
            //     ->groupBy('job')
            //     ->orderByDesc('slowest')
            //     ->get()
            //     ->all();

            $time = (int) ((hrtime(true) - $start) / 1000000);

            return [$slowJobs, $time, $now->toDateTimeString()];
        });
    }
}