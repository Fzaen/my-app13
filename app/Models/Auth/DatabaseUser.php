<?php

namespace App\Models\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;

class DatabaseUser extends Authenticatable
{
    protected $table = 'tbmaster_user';

    protected $primaryKey = 'userid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $hidden = ['userpassword'];

    public function getAuthPassword(): string
    {
        return (string) $this->getAttribute(config('database.legacy_user.password_column', 'userpassword'));
    }

    public function getAuthPasswordName(): string
    {
        return config('database.legacy_user.password_column', 'userpassword');
    }

    public function getNameAttribute(): string
    {
        return (string) $this->getAttribute(config('database.legacy_user.id_column', 'userid'));
    }
}
