<?php

namespace App\Livewire\Concerns;

use App\Models\Project;

/** Charge le projet de l'URL et vérifie qu'il appartient à l'utilisateur connecté. */
trait ForProject
{
    public Project $project;

    public function mount(Project $project): void
    {
        abort_unless($project->user_id === auth()->id(), 404);
        $this->project = $project;

        if (method_exists($this, 'init')) {
            $this->init();
        }
    }
}
