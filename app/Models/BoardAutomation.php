<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardAutomation extends Model
{
    protected $fillable = [
        'board_id',
        'trigger_type',
        'trigger_word',
        'trigger_board_id',
        'trigger_list_id',
        'target_board_id',
        'target_list_id',
        'target_assignee_id',
        'target_assignee_role',
        'action_type',
    ];

    public function board()
    {
        return $this->belongsTo(Board::class, 'board_id');
    }

    public function triggerBoard()
    {
        return $this->belongsTo(Board::class, 'trigger_board_id');
    }

    public function triggerList()
    {
        return $this->belongsTo(\App\Models\BoardList::class, 'trigger_list_id');
    }

    public function targetBoard()
    {
        return $this->belongsTo(Board::class, 'target_board_id');
    }

    public function targetList()
    {
        return $this->belongsTo(\App\Models\BoardList::class, 'target_list_id');
    }

    public function targetAssignee()
    {
        return $this->belongsTo(\App\Models\User::class, 'target_assignee_id');
    }
}
