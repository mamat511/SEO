<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\ForProject;
use App\Models\BacklinkImport;
use App\Services\BacklinkCsvImporter;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class Backlinks extends Component
{
    use ForProject, WithFileUploads, WithPagination;

    public $file;

    #[Url]
    public string $kind = 'domains';

    /** all | new | lost */
    #[Url]
    public string $filter = 'all';

    #[Url]
    public string $search = '';

    public function updated($name): void
    {
        if (in_array($name, ['kind', 'filter', 'search'])) {
            $this->resetPage();
        }
    }

    public function import(BacklinkCsvImporter $importer): void
    {
        $this->validate(['file' => 'required|file|mimes:csv,txt|max:20480'], attributes: ['file' => 'fichier']);

        try {
            $import = $importer->import($this->project, $this->file->getRealPath(), $this->file->getClientOriginalName());
        } catch (Throwable $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->reset('file');
        $this->kind = $import->kind;
        $this->filter = 'all';
        $label = $import->kind === 'domains' ? 'domaines référents' : 'liens';
        session()->flash('success', "Import terminé : {$import->rows_count} $label.");
    }

    public function deleteImport(int $id): void
    {
        $this->project->backlinkImports()->whereKey($id)->delete();
    }

    /**
     * Compare un import au précédent du même type.
     *
     * @return array{new:list<string>, lost:list<string>, previous:?BacklinkImport}
     */
    public static function diff(BacklinkImport $import): array
    {
        $previous = $import->previous();
        if (! $previous) {
            return ['new' => [], 'lost' => [], 'previous' => null];
        }

        $current = $import->backlinks()->pluck('source')->all();
        $before = $previous->backlinks()->pluck('source')->all();

        return [
            'new' => array_values(array_diff($current, $before)),
            'lost' => array_values(array_diff($before, $current)),
            'previous' => $previous,
        ];
    }

    public function render()
    {
        $imports = $this->project->backlinkImports()->latest('id')->get();
        $latest = $imports->firstWhere('kind', $this->kind);
        $diff = $latest ? self::diff($latest) : ['new' => [], 'lost' => [], 'previous' => null];

        $rows = null;
        if ($latest) {
            $base = $this->filter === 'lost' && $diff['previous'] ? $diff['previous'] : $latest;
            $query = $base->backlinks()
                ->when($this->filter === 'new', fn ($q) => $q->whereIn('source', $diff['new'] ?: ['']))
                ->when($this->filter === 'lost', fn ($q) => $q->whereIn('source', $diff['lost'] ?: ['']))
                ->when($this->search, fn ($q) => $q->where('source', 'like', '%'.$this->search.'%'));

            $rows = $this->kind === 'domains'
                ? $query->orderByDesc('linking_pages')->orderBy('source')->paginate(50)
                : $query->orderByDesc('last_crawled')->orderBy('source')->paginate(50);
        }

        // Évolution du nombre de domaines référents d'un import à l'autre.
        $history = $imports->where('kind', 'domains')->sortBy('id')->values();

        return view('livewire.projects.backlinks', [
            'imports' => $imports,
            'latest' => $latest,
            'diff' => $diff,
            'rows' => $rows,
            'chart' => [
                'labels' => $history->map(fn ($i) => $i->created_at->format('d/m/Y'))->all(),
                'domains' => $history->pluck('rows_count')->all(),
            ],
        ])->title('Backlinks · '.$this->project->name);
    }
}
