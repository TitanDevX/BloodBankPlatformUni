<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'display_name', 'scope'])]
class Role extends Model
{
    public const Administrator = 'administrator';

    public const System = 'SYSTEM';

    public const Bank = 'BANK';

    public const Hospital = 'HOSPITAL';
}
