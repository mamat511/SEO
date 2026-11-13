<?php

namespace App\Support;

/**
 * Catalogue des problèmes détectés par l'audit technique.
 */
class AuditIssues
{
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';

    /** @var array<string, array{label:string, severity:string, help:string}> */
    public const ALL = [
        'http_4xx' => ['label' => 'Page en erreur 4xx', 'severity' => self::ERROR, 'help' => 'La page renvoie une erreur client (404, 410…). Corrige les liens qui y mènent ou redirige-la.'],
        'http_5xx' => ['label' => 'Page en erreur 5xx', 'severity' => self::ERROR, 'help' => 'Le serveur a renvoyé une erreur. Google peut désindexer la page si cela persiste.'],
        'unreachable' => ['label' => 'Page injoignable', 'severity' => self::ERROR, 'help' => 'Délai dépassé ou erreur réseau.'],
        'links_to_broken' => ['label' => 'Contient des liens internes cassés', 'severity' => self::ERROR, 'help' => 'La page fait un lien vers une page interne en erreur.'],
        'title_missing' => ['label' => 'Balise title manquante', 'severity' => self::ERROR, 'help' => 'Chaque page indexable doit avoir une balise <title> unique.'],
        'redirect' => ['label' => 'Redirection', 'severity' => self::WARNING, 'help' => 'L\'URL est redirigée. Fais pointer les liens internes directement vers la destination.'],
        'title_duplicate' => ['label' => 'Title en double', 'severity' => self::WARNING, 'help' => 'Plusieurs pages ont le même title.'],
        'title_too_long' => ['label' => 'Title trop long (> 60 caractères)', 'severity' => self::WARNING, 'help' => 'Google risque de le tronquer dans les résultats.'],
        'title_too_short' => ['label' => 'Title trop court (< 20 caractères)', 'severity' => self::NOTICE, 'help' => 'Un title plus descriptif peut améliorer le taux de clic.'],
        'meta_missing' => ['label' => 'Meta description manquante', 'severity' => self::WARNING, 'help' => 'Google génèrera un extrait lui-même.'],
        'meta_duplicate' => ['label' => 'Meta description en double', 'severity' => self::WARNING, 'help' => 'Plusieurs pages ont la même meta description.'],
        'meta_too_long' => ['label' => 'Meta description trop longue (> 160 caractères)', 'severity' => self::NOTICE, 'help' => 'Elle risque d\'être tronquée.'],
        'h1_missing' => ['label' => 'H1 manquant', 'severity' => self::WARNING, 'help' => 'La page n\'a pas de titre principal <h1>.'],
        'h1_multiple' => ['label' => 'Plusieurs H1', 'severity' => self::NOTICE, 'help' => 'Un seul H1 par page est recommandé.'],
        'slow' => ['label' => 'Réponse lente (> 1,5 s)', 'severity' => self::WARNING, 'help' => 'Le serveur met du temps à répondre (temps jusqu\'au premier octet + téléchargement).'],
        'thin_content' => ['label' => 'Contenu faible (< 200 mots)', 'severity' => self::NOTICE, 'help' => 'Peu de texte sur la page.'],
        'noindex' => ['label' => 'Page en noindex', 'severity' => self::NOTICE, 'help' => 'La page demande à ne pas être indexée. Vérifie que c\'est voulu.'],
        'canonical_other' => ['label' => 'Canonical vers une autre URL', 'severity' => self::NOTICE, 'help' => 'La page désigne une autre URL comme version principale.'],
    ];

    public static function label(string $code): string
    {
        return self::ALL[$code]['label'] ?? $code;
    }

    public static function severity(string $code): string
    {
        return self::ALL[$code]['severity'] ?? self::NOTICE;
    }
}
