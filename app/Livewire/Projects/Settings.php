<?php

namespace App\Livewire\Projects;

use App\Jobs\SyncSearchConsole;
use App\Livewire\Concerns\ForProject;
use App\Services\SearchConsole;
use Livewire\Component;
use Throwable;

class Settings extends Component
{
    use ForProject;

    public string $name = '';
    public string $url = '';
    public string $gscProperty = '';
    public array $properties = [];
    public ?string $propertiesError = null;

    public function init(): void
    {
        $this->name = $this->project->name;
        $this->url = $this->project->url;
        $this->gscProperty = (string) $this->project->gsc_property;
        $this->loadProperties();
    }

    public function loadProperties(): void
    {
        if (! auth()->user()->googleAccount) {
            $this->propertiesError = 'Aucun compte Google connecté.';

            return;
        }

        try {
            $this->properties = SearchConsole::for(auth()->user())->properties();
        } catch (Throwable $e) {
            $this->propertiesError = $e->getMessage();
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:100',
            'url' => 'required|url:http,https|max:255',
            'gscProperty' => ['nullable', 'string', 'in:'.implode(',', array_merge($this->properties, [(string) $this->project->gsc_property]))],
        ], attributes: ['name' => 'nom', 'url' => 'URL', 'gscProperty' => 'propriété Search Console']);

        $changed = ($data['gscProperty'] ?: null) !== $this->project->gsc_property;

        if ($changed) {
            // Nouvelle propriété : l'historique de l'ancienne n'a plus de sens.
            $this->project->dailyMetrics()->delete();
            foreach ($this->project->keywords as $keyword) {
                $keyword->metrics()->delete();
            }
        }

        $this->project->update([
            'name' => $data['name'],
            'url' => $data['url'],
            'gsc_property' => $data['gscProperty'] ?: null,
            'gsc_synced_at' => $changed ? null : $this->project->gsc_synced_at,
        ]);

        if ($changed && $this->project->gsc_property) {
            SyncSearchConsole::dispatch($this->project);
        }

        session()->flash('success', 'Réglages enregistrés.');
    }

    public function delete()
    {
        $this->project->delete();

        return $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.projects.settings')->title('Réglages · '.$this->project->name);
    }
}
