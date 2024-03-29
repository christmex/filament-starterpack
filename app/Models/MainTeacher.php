<?php

namespace App\Models;

use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MainTeacher extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user() :BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function classroom() :BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
    public function company() :BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getFullLabelAttribute()
    {
        return $this->company->name." - ".$this->classroom->name." - ".Helper::getSchoolYearById($this->school_year);
    }
}
