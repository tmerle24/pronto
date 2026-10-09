<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $external_id
 * @property string $type
 */
#[Fillable(['provider', 'external_id', 'type'])]
class ProcessedWebhook extends Model
{
    public const UPDATED_AT = null;
}
