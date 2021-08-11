# oc-revisionable-behavior

**Revisionable behavior** for extending any OctoberCMS models. This can be used instead Revisionable trait when you need to extend a third party model.

**Installation**

Run the command below in the root folder of your project:

`composer require igor-tv/oc-revisionable-behavior`

**Use Example**
```
\RainLab\User\Models\User::extend(function ($model) {
    $model->implement[] = 'Iweb.Behaviors.RevisionableModel';

    $revisionableFields = [
        'name',
        'email'
    ];
    
    //required
    $model->addDynamicProperty('revisionable', $revisionableFields);
    
    //optional, default: 500
    $model->addDynamicProperty('revisionableLimit', 200);
    
    //optional, if you need rename revisions model relation
    $model->addDynamicProperty('revisionHistoryRelationName', 'your_history_relation_name');
    
    //optional, if you need assosiate changes with backend users
    $model->addDynamicMethod('getRevisionableUser', function () {
        if (!\BackendAuth::check()) {
            return null;
        }
  
        return \BackendAuth::getUser()->id;
    });
});
```
For more details see Revisionable trait description https://octobercms.com/docs/database/traits#revisionable

Extending Models in official documentation https://octobercms.com/docs/database/model#extending-models
