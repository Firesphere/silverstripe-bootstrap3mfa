<?php

/**
 * Class MFAForm
 */
abstract class MFAForm extends Form
{

    /**
     * MFAForm constructor.
     * @param Controller $controller
     * @param $name
     */
    public function __construct(
        $controller,
        $name
    ) {
        $fields = $this->getFormFields();
        $actions = $this->getFormActions();
        $validator = $this->getRequiredFields();

        parent::__construct($controller, $name, $fields, $actions, $validator);
    }

    /**
     * @return FieldList
     */
    public function getFormFields()
    {
        $backURL = Session::get('BackURL');

        $list = FieldList::create([
            HiddenField::create('BackURL', 'BackURL', $backURL)
        ]);

        return $list;
    }

    /**
     * @return FieldList
     */
    public function getFormActions()
    {
        $actions = FieldList::create(
            FormAction::create('doChallenge', 'Submit')
        );

        return $actions;
    }

    /**
     * @return mixed
     */
    abstract public function getRequiredFields();
}
