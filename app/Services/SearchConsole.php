<?php

namespace App\Services;

use App\Models\GoogleAccount;
use App\Models\User;
use Google\Client;
use Google\Service\Oauth2;
use Google\Service\SearchConsole as SearchConsoleService;
use Google\Service\SearchConsole\ApiDimensionFilter;
use Google\Service\SearchConsole\ApiDimensionFilterGroup;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use RuntimeException;

/**
 * Accès à l'API Google Search Console pour un utilisateur.
 */
class SearchConsole
{
    public const ROW_LIMIT = 25000; // maximum autorisé par l'API

    public static function client(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setScopes([SearchConsoleService::WEBMASTERS_READONLY, 'openid', 'email']);
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // garantit un refresh_token

        return $client;
    }

    /** Échange le code OAuth contre des jetons et les enregistre. */
    public static function connect(User $user, string $code): GoogleAccount
    {
        $client = self::client();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException($token['error_description'] ?? $token['error']);
        }

        $client->setAccessToken($token);
        $email = (new Oauth2($client))->userinfo->get()->getEmail();

        $existing = $user->googleAccount;

        return GoogleAccount::updateOrCreate(['user_id' => $user->id], [
            'email' => $email,
            'access_token' => $token['access_token'],
            // Google ne renvoie le refresh_token qu'au premier consentement : on garde l'ancien sinon.
            'refresh_token' => $token['refresh_token'] ?? $existing?->refresh_token,
            'expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
        ]);
    }

    public function __construct(private GoogleAccount $account)
    {
    }

    public static function for(User $user): self
    {
        $account = $user->googleAccount ?? throw new RuntimeException('Aucun compte Google connecté.');

        return new self($account);
    }

    private function service(): SearchConsoleService
    {
        $client = self::client();
        $client->setAccessToken([
            'access_token' => $this->account->access_token,
            'refresh_token' => $this->account->refresh_token,
            'expires_in' => max(0, now()->diffInSeconds($this->account->expires_at, false)),
            'created' => now()->timestamp,
        ]);

        if ($client->isAccessTokenExpired()) {
            if (! $this->account->refresh_token) {
                throw new RuntimeException('Jeton Google expiré : reconnecte ton compte Google.');
            }

            $token = $client->fetchAccessTokenWithRefreshToken($this->account->refresh_token);

            if (isset($token['error'])) {
                throw new RuntimeException('Rafraîchissement du jeton Google impossible : '.($token['error_description'] ?? $token['error']));
            }

            $this->account->update([
                'access_token' => $token['access_token'],
                'expires_at' => now()->addSeconds($token['expires_in'] ?? 3600),
            ]);
        }

        return new SearchConsoleService($client);
    }

    /** @return list<string> propriétés auxquelles le compte a accès */
    public function properties(): array
    {
        $sites = $this->service()->sites->listSites()->getSiteEntry() ?? [];

        return collect($sites)
            ->reject(fn ($site) => $site->getPermissionLevel() === 'siteUnverifiedUser')
            ->map(fn ($site) => $site->getSiteUrl())
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Requête Search Analytics paginée.
     *
     * @param  list<string>  $dimensions
     * @param  array<string,string>  $equals  filtres d'égalité, ex. ['query' => 'mot clé']
     * @return \Generator<array{keys:list<string>, clicks:float, impressions:float, ctr:float, position:float}>
     */
    public function query(string $property, string $start, string $end, array $dimensions, array $equals = [], ?int $limit = null): \Generator
    {
        $service = $this->service();
        $startRow = 0;

        do {
            $request = new SearchAnalyticsQueryRequest();
            $request->setStartDate($start);
            $request->setEndDate($end);
            $request->setDimensions($dimensions);
            $request->setRowLimit(min($limit ?? self::ROW_LIMIT, self::ROW_LIMIT));
            $request->setStartRow($startRow);
            $request->setDataState('final');

            if ($equals) {
                $group = new ApiDimensionFilterGroup();
                $group->setGroupType('and');
                $group->setFilters(collect($equals)->map(function ($value, $dimension) {
                    $filter = new ApiDimensionFilter();
                    $filter->setDimension($dimension);
                    $filter->setOperator('equals');
                    $filter->setExpression($value);

                    return $filter;
                })->values()->all());
                $request->setDimensionFilterGroups([$group]);
            }

            $rows = $service->searchanalytics->query($property, $request)->getRows() ?? [];

            foreach ($rows as $row) {
                yield [
                    'keys' => $row->getKeys() ?? [],
                    'clicks' => (float) $row->getClicks(),
                    'impressions' => (float) $row->getImpressions(),
                    'ctr' => (float) $row->getCtr(),
                    'position' => (float) $row->getPosition(),
                ];
            }

            $startRow += self::ROW_LIMIT;
        } while (count($rows) === self::ROW_LIMIT && ($limit === null || $startRow < $limit));
    }
}
