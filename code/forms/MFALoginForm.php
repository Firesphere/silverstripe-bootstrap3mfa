<?php

/**
 * Class MFALoginForm
 */
abstract class MFALoginForm extends MemberLoginForm
{

    /**
     * @var array
     */
    private static $allowed_actions = array(
        'doLogin',
        'challenge',
        'MFAForm',
    );

    /**
     * @var string
     */
    protected $authenticator_class = 'MFAAuthenticator';

    /**
     * @var array|mixed|null|Session
     */
    private $backURL;

    /**
     * MFALoginForm constructor.
     * @param Controller $controller
     * @param string $name
     * @param FieldList|null $fields
     * @param FieldList|null $actions
     * @param bool $checkCurrentUser
     * @throws Exception
     */
    public function __construct(
        $controller,
        $name,
        FieldList $fields = null,
        FieldList $actions = null,
        $checkCurrentUser = false
    ) {
        if (!$backURL = $controller->getRequest()->requestVar('BackURL')) {
            $backURL = Session::get('BackURL');
        }
        $this->backURL = $backURL;
        if (!$fields) {
            $fields = $this->getFormFields();
        }
        if (!$actions) {
            $actions = $this->getFormActions();
        }
        $validator = $this->getFormValidator();

        //skip the parent construct
        LoginForm::__construct($controller, $name, $fields, $actions, $validator);

        $this->setFormMethod('POST', true);
    }

    /**
     * @return FieldList
     * @throws Exception
     */
    public function getFormFields()
    {
        $label = Injector::inst()->get(Member::class)->fieldLabel(Member::config()->unique_identifier_field);
        $fields = FieldList::create(
            HiddenField::create('AuthenticationMethod', null, $this->authenticator_class, $this),
            $emailField = TextField::create('Email', $label),
            PasswordField::create('Password', _t('Member.PASSWORD', 'Password'))
        );
        if ($this->backURL) {
            $fields->push(HiddenField::create('BackURL', 'BackURL', $this->backURL));
        }
        if (Security::config()->remember_username) {
            $emailField->setValue(Session::get('SessionForms.MemberLoginForm.Email'));
        } else {
            // Some browsers won't respect this attribute unless it's added to the form
            $this->setAttribute('autocomplete', 'off');
            $emailField->setAttribute('autocomplete', 'off');
        }
        if (Security::config()->autologin_enabled) {
            $fields->push(CheckboxField::create(
                'Remember',
                _t('Member.REMEMBERME', 'Remember me next time?')
            ));
        }

        return $fields;
    }

    /**
     * @return FieldList
     */
    public function getFormActions()
    {
        return FieldList::create(
            FormAction::create('dologin', _t('Member.BUTTONLOGIN', "Log in")),
            LiteralField::create(
                'forgotPassword',
                sprintf(
                    '<p id="ForgotPassword"><a href="%s">'
                    . _t('Member.BUTTONLOSTPASSWORD', "I've lost my password") . '</a></p>',
                    Security::config()->get('lost_password_url')
                )
            )
        );
    }

    /**
     * @return RequiredFields
     */
    public function getFormValidator()
    {
        return RequiredFields::create([
            'Email',
            'Password',
        ]);
    }

    /**
     * @param $rawData
     * @param MFALoginForm $form
     * @param SS_HTTPRequest $request
     */
    public function dologin($rawData, $form = null, $request = null)
    {
        $data = $form->getData();
        $member = call_user_func_array([$this->authenticator_class, 'authenticate'], [$data, $form]);
        if (isset($data['BackURL'])) {
            $backURL = $data['BackURL'];
        } else {
            $backURL = null;
        }

        if ($backURL) {
            Session::set('BackURL', $backURL);
        }

        // Show the right tab on failed login
        $loginLink = Director::absoluteURL($this->controller->Link('login'));
        if ($backURL) {
            $loginLink .= '?BackURL=' . urlencode($backURL);
        }
        if (!$member || !$member->exists()) {
            if (array_key_exists('Email', $data)) {
                Session::set('SessionForms.MemberLoginForm.Email', $data['Email']);
                Session::set('SessionForms.MemberLoginForm.Remember', isset($data['Remember']));
            }

            $this->controller->redirect($loginLink . '#' . $this->FormName() . '_tab');
        } else {
            if ($member->hasMFA()) {
                Session::set(MFAAuthenticator::SESSION_KEY . '.MemberID', $member->ID);
                Session::set(MFAAuthenticator::SESSION_KEY . '.loginData', $data);
                $link = $this->Link('challenge') . '?BackURL=' . $backURL;
                $this->getController()->redirect($link);
            } else {
                $this->performLogin($data);
                $this->logInUserAndRedirect($data);
            }
        }
    }

    /**
     * @param null $action
     * @return String
     */
    public function Link($action = null)
    {
        return Controller::join_links(
            $this->getController()->Link(),
            $this->getName(),
            $action
        );
    }

    /**
     * @param $request
     * @return HTMLText
     * @throws Exception
     */
    public function challenge($request)
    {
        return $this->customise(array(
            'Content' => $this->MFAForm(),
        ))->renderWith('Page');
    }

    /**
     * Requires to call `doChallenge` as it's FormAction
     * This action should be implemented on the login form used
     * @return MFAForm
     */
    abstract public function MFAForm();

    /**
     * @param array $data
     * @param Form $form
     * @param SS_HTTPRequest $request
     * @throws Exception
     */
    public function doChallenge($data, $form, $request)
    {
        $memberID = Session::get(MFAAuthenticator::SESSION_KEY . '.MemberID');
        /** @var Member $member */
        $member = Member::get()->byID($memberID);
        $mfaProvider = Injector::inst()->get(MFABackupCodeProvider::class);
        $mfaProvider->setMember($member);
        if ($mfaProvider->verifyToken($data['Token'])) {
            $loginData = Session::get(MFAAuthenticator::SESSION_KEY . '.loginData');
            $member->logIn(isset($loginData['Remember']));
            $this->logInUserAndRedirect($loginData);
        } else {
            $this->setMessage('2 Factor authentication failed', 'bad');
            $this->controller->redirect('/Security/Login');
        }
    }
}
