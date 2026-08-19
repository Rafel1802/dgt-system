<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineReturnLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_return_id', 'user_id', 'status_changed_to', 'note',
    ];

    public function machineReturn()
    {
        return $this->belongsTo(MachineReturn::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
