<?php

namespace App\Livewire;

use App\Jobs\SyncSearchConsole;
use App\Models\Project;
use App\Services\SearchConsole;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('Tableau de bord')]
class Dashboard extends Component
{
    public bool $showForm = false;
    public string $name = '';
    public string $url = '';
    public string $gscProperty = '';

    /** Propriétés Search Console disponibles (chargées à l'ouverture du formulaire). */
    public array $properties = [];
    public ?string $propertiesError = null;

    public function openForm(): void
    {
        $this->reset('name', 'url', 'gscProperty', 'propertiesError');
        $this->showForm = true;

        if (auth()->user()->googleAccount) {
            try {
                $this->properties = SearchConsole::for(auth()->user())->properties();
            } catch (Throwable $e) {
                $this->propertiesError = $e->getMessage();
            }
        }
    }

    /** Pré-remplit le nom et l'URL quand on choisit une propriété Search Console. */
    public function updatedGscProperty(string $value): void
    {
        if (! $value) {
            return;
        }
        $host = str_starts_with($value, 'sc-domain:') ? substr($value, 10) : parse_url($value, PHP_URL_HOST);
        $this->url = $this->url ?: (str_starts_with($value, 'sc-domain:') ? "https://$host/" : $value);
        $this->name = $this->name ?: $host;
    }

    public function create()
    {
        $this->url = trim($this->url);
        if ($this->url && ! preg_match('#^https?://#i', $this->url)) {
            $this->url = 'https://'.$this->url;
        }

        $data = $this->validate([
            'name' => 'required|string|max:100',
            'url' => 'required|url:http,https|max:255',
            'gscProperty' => ['nullable', 'string', 'in:'.implode(',', $this->properties)],
        ], attributes: ['name' => 'nom', 'url' => 'URL', 'gscProperty' => 'propriété Search Console']);

        $project = auth()->user()->projects()->create([
            'name' => $data['name'],
            'url' => $data['url'],
            'gsc_property' => $data['gscProperty'] ?: null,
        ]);

        if ($project->gsc_property) {
            SyncSearchConsole::dispatch($project);
        }

        return $this->redirectRoute('projects.overview', $project);
    }

    #[Computed]
    public function projects()
    {
        return auth()->user()->projects()->with('latestCrawl')->withCount('keywords')->orderBy('name')->get()
            ->map(function (Project $project) {
                $project->stats = $project->summary(28);
                $project->sparkline = $project->dailyMetrics()->where('date', '>=', now()->subDays(92))->orderBy('date')->pluck('clicks')->all();
                $project->domainsCount = $project->backlinkImports()->where('kind', 'domains')->latest('id')->value('rows_count');

                return $project;
            });
    }

    public function render()
    {
        return view('livewire.dashboard', ['google' => auth()->user()->googleAccount]);
    }
}
