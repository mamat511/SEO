<?php

namespace App\Services;

use App\Models\Backlink;
use App\Models\BacklinkImport;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Importe un export CSV du rapport « Liens » de la Search Console.
 *
 * Deux formes d'export sont reconnues d'après le contenu de la 1re colonne :
 *  - « domains » : un domaine par ligne (rapport des sites qui renvoient le plus de liens),
 *    suivi de colonnes numériques (pages avec lien, pages cibles) ;
 *  - « links »   : une URL source par ligne (exemples de liens / derniers liens),
 *    éventuellement suivie d'une date de dernière exploration.
 *
 * Les intitulés de colonnes varient selon la langue de l'interface : on ne s'appuie
 * dessus que pour distinguer les deux colonnes numériques, sinon on suit leur ordre.
 */
class BacklinkCsvImporter
{
    public function import(Project $project, string $path, string $filename): BacklinkImport
    {
        $rows = $this->read($path);

        if (count($rows) < 2) {
            throw new RuntimeException('Le fichier est vide ou ne contient pas de lignes de données.');
        }

        $header = array_map(fn ($h) => mb_strtolower(trim($h)), array_shift($rows));
        $sample = trim($rows[0][0] ?? '');
        $kind = preg_match('#^https?://#i', $sample) ? 'links' : 'domains';

        if ($kind === 'domains' && ! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $sample)) {
            throw new RuntimeException('Format non reconnu : la première colonne doit contenir des domaines ou des URL.');
        }

        $targetCol = $this->findColumn($header, ['cible', 'target']);

        return DB::transaction(function () use ($project, $rows, $kind, $filename, $targetCol) {
            $import = BacklinkImport::create([
                'project_id' => $project->id,
                'kind' => $kind,
                'filename' => $filename,
            ]);

            $records = [];
            foreach ($rows as $row) {
                $source = trim($row[0] ?? '');
                if ($source === '') {
                    continue;
                }

                $record = [
                    'backlink_import_id' => $import->id,
                    'source' => mb_substr($source, 0, 255),
                    'domain' => $kind === 'links' ? $this->domainOf($source) : strtolower($source),
                    'linking_pages' => null,
                    'target_pages' => null,
                    'last_crawled' => null,
                ];

                if ($kind === 'domains') {
                    $numbers = array_values(array_filter(array_slice($row, 1), fn ($v) => is_numeric($this->toNumber($v))));
                    $record['linking_pages'] = isset($numbers[0]) ? (int) $this->toNumber($numbers[0]) : null;
                    $record['target_pages'] = $targetCol !== null && isset($row[$targetCol])
                        ? (int) $this->toNumber($row[$targetCol])
                        : (isset($numbers[1]) ? (int) $this->toNumber($numbers[1]) : null);
                } else {
                    $record['last_crawled'] = $this->toDate($row[1] ?? null);
                }

                $records[$record['source']] = $record; // dédoublonnage
            }

            foreach (array_chunk(array_values($records), 500) as $chunk) {
                Backlink::insert($chunk);
            }

            $import->update(['rows_count' => count($records)]);

            return $import;
        });
    }

    /** @return list<list<string>> */
    private function read(string $path): array
    {
        $content = file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // BOM UTF-8

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE, Windows-1252');
        }

        $firstLine = strtok($content, "\n");
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($row !== [null]) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return $rows;
    }

    private function findColumn(array $header, array $needles): ?int
    {
        foreach ($header as $i => $label) {
            foreach ($needles as $needle) {
                if (str_contains($label, $needle)) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function toNumber(?string $value): ?string
    {
        $clean = preg_replace('/[\s\x{00A0}\x{202F},.]/u', '', (string) $value);

        return $clean === '' ? null : $clean;
    }

    private function toDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse(str_replace('/', '-', trim($value)))->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function domainOf(string $url): string
    {
        return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }
}
