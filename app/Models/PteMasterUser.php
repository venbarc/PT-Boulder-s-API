<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteMasterUser extends Model
{
    /**
     * Export/display columns from pte_master_users.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_user_id',
        'full_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'title',
        'status',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_master_users';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'is_active' => 'boolean',
    ];
}
