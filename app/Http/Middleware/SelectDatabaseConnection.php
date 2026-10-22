<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SelectDatabaseConnection
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $fallbackConnection = config('database.fallback_connection');
        DB::setDefaultConnection($fallbackConnection);

        try {
            $isGuestEntryPage = $request->isMethod('get')
                && ($request->is('login') || $request->is('/'));

            if ($isGuestEntryPage) {
                return $next($request);
            }

            $selectedDatabase = $request->session()->get('selected_database');

            if ($request->isMethod('post') && $request->is('login')) {
                $requestedDatabase = $request->input('database');

                if (is_string($requestedDatabase) && isset(config('database.sites')[$requestedDatabase])) {
                    $selectedDatabase = $requestedDatabase;
                    $request->attributes->set('selected_database', $selectedDatabase);
                }
            }

            $connectionName = config("database.sites.{$selectedDatabase}.connection");

            if (is_string($connectionName)) {
                DB::setDefaultConnection($connectionName);

                if (config("database.connections.{$connectionName}.driver") === 'pgsql') {
                    try {
                        DB::connection($connectionName)->select('select 1');
                    } catch (QueryException | \PDOException $exception) {
                        if (! $request->isMethod('post') || ! $request->is('login')) {
                            throw $exception;
                        }

                        $cause = $exception->getPrevious() ?? $exception;
                        $message = strtolower($cause->getMessage());
                        $userMessage = str_contains($message, 'password authentication failed')
                            ? 'Database connection credentials were rejected. Verify the selected server account.'
                            : 'The selected database is unavailable or denied this connection.';

                        Log::warning('Selected PostgreSQL connection failed.', [
                            'site' => $selectedDatabase,
                            'cause' => class_basename($cause),
                        ]);

                        return redirect()
                            ->route('login')
                            ->withErrors(['database' => $userMessage])
                            ->withInput($request->only('database', 'userid'));
                    }
                }
            }

            return $next($request);
        } finally {
            DB::setDefaultConnection($fallbackConnection);
        }
    }
}
