<?php

namespace App\Http\Controllers;

use App\Services\SearchConsole;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(Request $request)
    {
        if (! config('services.google.client_id')) {
            return redirect()->route('dashboard')->with('error', 'GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET ne sont pas renseignés dans le fichier .env.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        $client = SearchConsole::client();
        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        if (! hash_equals((string) $request->session()->pull('google_oauth_state'), (string) $request->query('state'))) {
            return redirect()->route('dashboard')->with('error', 'Connexion Google refusée : état OAuth invalide.');
        }

        if ($request->has('error') || ! $request->has('code')) {
            return redirect()->route('dashboard')->with('error', 'Connexion Google annulée.');
        }

        try {
            $account = SearchConsole::connect($request->user(), $request->query('code'));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('dashboard')->with('error', 'Connexion Google impossible : '.$e->getMessage());
        }

        return redirect()->route('dashboard')->with('success', "Compte Google {$account->email} connecté.");
    }

    public function disconnect(Request $request)
    {
        $account = $request->user()->googleAccount;

        if ($account) {
            try {
                SearchConsole::client()->revokeToken($account->refresh_token ?: $account->access_token);
            } catch (Throwable) {
                // Le jeton est peut-être déjà invalide : on supprime quand même.
            }
            $account->delete();
        }

        return redirect()->route('dashboard')->with('success', 'Compte Google déconnecté.');
    }
}
