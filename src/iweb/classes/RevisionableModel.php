<?php namespace Iweb\Classes;

use Iweb\Behaviors\RevisionableBehavior;

/**
 * Revisionable model extension
 *
 * Class RevisionableModel
 * @package Iweb\Classes
 */
class RevisionableModel extends RevisionableBehavior
{
    public function __construct($model)
    {
        parent::__construct($model);

        $model->morphMany['revision_history'] = [
            \System\Models\Revision::class,
            'name' => 'revisionable'
        ];
    }
}
