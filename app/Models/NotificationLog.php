<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['channel', 'recipient', 'template', 'body', 'status', 'error'])]
class NotificationLog extends Model {}
