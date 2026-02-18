<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PteTherapist extends Model
{
    /**
     * Export/display columns from pte_therapists.
     * raw_data is intentionally excluded.
     *
     * @var array<int, string>
     */
    public const EXPORT_COLUMNS = [
        'id',
        'row_key',
        'pte_therapist_id',
        'therapist_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'title',
        'credentials',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $table = 'pte_therapists';

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'is_active' => 'boolean',
    ];
}
