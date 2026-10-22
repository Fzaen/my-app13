<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;

class TbmasterUserProvider extends EloquentUserProvider
{
    public function __construct(Hasher $hasher, string $model)
    {
        parent::__construct($hasher, $model);
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $password = $credentials['password'] ?? null;
        $storedPassword = $user->getAuthPassword();

        if (! is_string($password)) {
            return false;
        }

        if (preg_match('/^[a-f0-9]{32}$/i', $storedPassword) === 1) {
            return hash_equals(strtolower($storedPassword), md5($password));
        }

        return hash_equals($storedPassword, $password);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void {}

    /** @return Model */
    public function createModel()
    {
        $model = parent::createModel();
        $model->setTable(config('database.legacy_user.table', 'tbmaster_user'));
        $model->setKeyName(config('database.legacy_user.id_column', 'userid'));
        $databaseKey = request()->attributes->get(
            'selected_database',
            session('selected_database', config('database.default_site')),
        );
        $connection = config("database.sites.{$databaseKey}.connection");

        if (is_string($connection)) {
            $model->setConnection($connection);
        }

        return $model;
    }
}
