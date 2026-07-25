<?php

namespace App\Models;

use App\BudgetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'amount', 'type', 'user_id'])]
class Budget extends Model
{
     protected $casts = [
        'type' => BudgetType::class
     ];
  

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isGeneral() : bool
    {
        return $this->type === BudgetType::General;
    }

    public function isGoal() : bool

    {
       return $this->type === BudgetType::Goal;
    }
}
