# oc-revisionable-behavior
https://octobercms.com/docs/database/model#extending-models

```
User::extend(function ($model) {
	$model->implement[] = 'Iweb.Behaviors.RevisionableModel';

	$revisionable = [
        'name',
        'email'
    ];

    $model->addDynamicProperty('revisionable', $revisionable);
    $model->addDynamicProperty('revisionableLimit', 200);
    $model->addDynamicMethod('getRevisionableUser', function () {
        if (!\BackendAuth::check()) {
        	return null;
        }

        return \BackendAuth::getUser()->id;
    });

});

```