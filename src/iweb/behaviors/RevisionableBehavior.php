<?php namespace Iweb\Behaviors;

use DateTime;
use Db;
use Exception;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use October\Rain\Extension\ExtensionBase;

/**
 * Class RevisionableBehavior
 * @package Iweb\Behaviors
 */
abstract class RevisionableBehavior extends ExtensionBase
{
    const REVISION_HISTORY_NAME  = 'revision_history';
    const DEFAULT_REVISION_LIMIT = 500;
    const CLEANUP_REVISION_BATCH = 64;

    /**
     * @var EloquentModel Reference to the extended model.
     */
    protected $model;

    /**
     * @var bool Flag for arbitrarily disabling revision history.
     */
    public $revisionsEnabled = true;

    /**
     * Constructor
     * @param EloquentModel $model The extended model.
     * @throws Exception
     */
    public function __construct(EloquentModel $model)
    {
        $this->model = $model;
        $this->bootRevisionable();
    }

    /**
     * Boot the revisionable trait for a model.
     * @return void
     * @throws Exception
     */
    public function bootRevisionable()
    {
        if (!property_exists(get_class($this->model), 'revisionable') && !$this->model->getDynamicProperties()['revisionable']) {
            throw new Exception(sprintf(
                'You must define a $revisionable property in %s to use the Revisionable behavior.',
                get_class($this->model)
            ));
        }

        $this->model->bindEvent('model.afterUpdate', function () {
            $this->model->revisionableAfterUpdate();
        });

        $this->model->bindEvent('model.afterDelete', function () {
            $this->model->revisionableAfterDelete();
        });
    }

    public function revisionableAfterUpdate()
    {
        if (!$this->revisionsEnabled) {
            return;
        }

        $relation = $this->getRevisionHistoryName();
        $relationObject = $this->model->{$relation}();
        $revisionModel = $relationObject->getRelated();

        $toSave = [];
        $dirty = $this->model->getDirty();
        foreach ($dirty as $attribute => $value) {
            if (!in_array($attribute, $this->model->revisionable)) {
                continue;
            }

            $toSave[] = [
                'field' => $attribute,
                'old_value' => $this->model->getOriginal($attribute),
                'new_value' => $value,
                'revisionable_type' => $relationObject->getMorphClass(),
                'revisionable_id' => $this->model->getKey(),
                'user_id' => $this->revisionableGetUser(),
                'cast' => $this->revisionableGetCastType($attribute),
                'created_at' => new DateTime,
                'updated_at' => new DateTime
            ];
        }

        // Nothing to do
        if (!count($toSave)) {
            return;
        }

        Db::table($revisionModel->getTable())->insert($toSave);
        $this->revisionableCleanUp();
    }

    public function revisionableAfterDelete()
    {
        if (!$this->model->revisionsEnabled) {
            return;
        }

        $softDeletes = in_array(
            'October\Rain\Database\Traits\SoftDelete',
            class_uses_recursive(get_class($this->model))
        );

        if (!$softDeletes) {
            return;
        }

        if (!in_array('deleted_at', $this->model->revisionable)) {
            return;
        }

        $relation = $this->model->getRevisionHistoryName();
        $relationObject = $this->model->{$relation}();
        $revisionModel = $relationObject->getRelated();

        $toSave = [
            'field' => 'deleted_at',
            'old_value' => null,
            'new_value' => $this->model->deleted_at,
            'revisionable_type' => $relationObject->getMorphClass(),
            'revisionable_id' => $this->model->getKey(),
            'user_id' => $this->revisionableGetUser(),
            'created_at' => new DateTime,
            'updated_at' => new DateTime
        ];

        Db::table($revisionModel->getTable())->insert($toSave);
        $this->revisionableCleanUp();
    }

    /*
     * Deletes revision records exceeding the limit.
     */
    protected function revisionableCleanUp()
    {
        $relation = $this->model->getRevisionHistoryName();
        $relationObject = $this->model->{$relation}();

        $revisionLimit = property_exists($this->model, 'revisionableLimit')
            ? (int) $this->model->revisionableLimit
            : self::DEFAULT_REVISION_LIMIT;

        $toDelete = $relationObject
            ->orderBy('id', 'desc')
            ->skip($revisionLimit)
            ->limit(self::CLEANUP_REVISION_BATCH)
            ->get();

        foreach ($toDelete as $record) {
            $record->delete();
        }
    }

    protected function revisionableGetCastType($attribute)
    {
        if (in_array($attribute, $this->model->getDates())) {
            return 'date';
        }

        return null;
    }

    protected function revisionableGetUser()
    {
        if ($this->model->methodExists('getRevisionableUser')) {
            $user = $this->model->getRevisionableUser();

            return $user instanceof EloquentModel
                ? $user->getKey()
                : $user;
        }

        return null;
    }

    /**
     * Get revision history relation name.
     * TODO: get revision history relation name via dynamic method
     * @return string
     */
    public function getRevisionHistoryName()
    {
        return defined($this->model . '::REVISION_HISTORY') ? $this->model::REVISION_HISTORY : self::REVISION_HISTORY_NAME;
    }
}
