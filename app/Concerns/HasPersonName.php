<?php

namespace App\Concerns;

use App\Support\PersonName;

/**
 * Keeps first_name / last_name and the shown full name column in sync.
 *
 * Forms fill first_name and last_name; older paths (import, public lead form) still fill
 * only the full name, which is then split.
 */
trait HasPersonName
{
    public static function bootHasPersonName(): void
    {
        static::saving(function (self $model): void {
            $model->syncPersonName();
        });
    }

    /**
     * The column that holds the full name shown everywhere.
     */
    public function personNameColumn(): string
    {
        return 'full_name';
    }

    public function syncPersonName(): void
    {
        $column = $this->personNameColumn();

        if ($this->isDirty(['first_name', 'last_name']) && filled($this->getAttribute('first_name'))) {
            $this->setAttribute($column, PersonName::join($this->getAttribute('first_name'), $this->getAttribute('last_name')));

            return;
        }

        if ($this->isDirty($column) || blank($this->getAttribute('first_name'))) {
            [$firstName, $lastName] = PersonName::split($this->getAttribute($column));
            $this->setAttribute('first_name', $firstName);
            $this->setAttribute('last_name', $lastName);
        }
    }
}
