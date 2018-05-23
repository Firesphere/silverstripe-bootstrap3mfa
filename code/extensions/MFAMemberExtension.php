<?php

/**
 * Class MFAMemberExtension
 *
 * @property Member|MFAMemberExtension $owner
 * @property boolean $MFAEnabled
 * @method DataList|MFABackupCode[] BackupCodes()
 */
class MFAMemberExtension extends DataExtension
{

    /**
     * @var array
     */
    private static $db = [
        'MFAEnabled' => 'Boolean',
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'BackupCodes' => MFABackupCode::class,
    ];

    /**
     * @return mixed
     */
    public function hasMFA()
    {
        return $this->owner->MFAEnabled;
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $this->updateMFA = 0;

        $fields->insertAfter('Password', CheckboxField::create('MFAEnabled', 'MFA Enabled'));
        $fields->insertAfter('MFAEnabled', CheckboxField::create('updateMFA', 'Reset MFA codes'));
        $fields->removeByName(['BackupCodes']);

        if (Session::get('tokens')) {
            /** @var TabSet $rootTabSet */
            $rootTabSet = $fields->dataFieldByName('Root');
            $field = LiteralField::create('tokens', Session::get('tokens'));
            $tab = Tab::create(
                'BackupTokens',
                'Backup Tokens'
            );
            $rootTabSet->push($tab);
            $fields->addFieldToTab('Root.BackupTokens', $field);
            Session::clear('tokens');
        }
    }

    /**
     * Update MFA tokens if the user wants to
     * @throws Exception
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->owner->updateMFA) {
            /** @var MFABackupCodeProvider $provider */
            $provider = Injector::inst()->get(MFABackupCodeProvider::class);
            $provider->setMember($this->owner);
            $provider->updateTokens();
        }
    }
}
