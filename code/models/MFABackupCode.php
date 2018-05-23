<?php

/**
 * Class MFABackupCode
 *
 * @property string $Token
 * @property boolean $IsUsed
 * @property int $MemberID
 * @method Member Member()
 */
class MFABackupCode extends DataObject
{
    private static $db = array(
        'Token'  => 'Varchar(6)',
        'IsUsed' => 'Boolean',
    );

    private static $has_one = array(
        'Member' => 'Member',
    );

    private static $indexes = array(
        'MemberTokens' => array(
            'type'  => 'index',
            'value' => '"MemberID","Token"',
        ),
    );

    /**
     * @param Member $member
     * @return DataList|static[]
     */
    public static function getValidTokensForMember($member)
    {
        return static::get()->filter([
            'IsUsed'   => false,
            'MemberID' => $member->ID
        ]);
    }

    /**
     * @param Member $member
     * @throws Exception
     */
    public static function generateTokensForMember($member)
    {
        if (Member::currentUser() && (int)Member::currentUserID() !== $member->ID) {
            self::sendWarningEmail($member);
        } else {
            $message = _t(
                'MFABackupCode.SESSIONMESSAGE_START',
                '<p>Here are your tokens, please store them securily. ' .
                'They are stored encrypted and can not be recovered, only reset.</p><p>'
            );

            $limit = static::config()->get('token_limit');
            for ($i = 0; $i < $limit; ++$i) {
                $token = static::createCode($member);
                $message .= sprintf('%s<br />', $token);
            }
            $message .= '</p>';
            Session::set('tokens', $message);
        }
    }

    /**
     * @param $member
     */
    public static function sendWarningEmail($member)
    {
        /** @var Email $mail */
        $mail = Email::create();
        $mail->setTo($member->Email);
        $mail->setFrom(Config::inst()->get(Email::class, 'admin_email'));
        $mail->setSubject(_t('MFABackupCode.REGENERATIONMAIL', 'Your backup tokens need to be regenerated'));
        $mail->setBody(_t(
            'MFABackupCode.REGENERATIONREQUIRED',
            _t(
                '<p>Your backup codes for multi factor authentication have been requested to regenerate by someone that is not you.' .
                'Please visit the <a href="{url}/{segment}">website to regenerate your backupcodes</a></p>',
                [
                    'url'     => Director::absoluteBaseURL(),
                    'segment' => Security::config()->get('lost_password_url')
                ]
            )
        ));
        $mail->send();
    }

    /**
     * @param $member
     * @return string
     * @throws ValidationException
     */
    private static function createCode($member)
    {
        $code = static::create();
        $code->MemberID = $member->ID;
        $token = $code->Token;
        $code->write();
        $code->destroy();

        return $token;
    }

    public function populateDefaults()
    {
        $this->Token = $this->generateToken();

        return parent::populateDefaults();
    }

    /**
     * @return mixed
     */
    protected function generateToken()
    {
        $config = Config::inst()->get(CodeGenerator::class, 'settings');
        /** @var CodeGenerator $generator */
        $generator = Injector::inst()->get(CodeGenerator::class)
            ->setLength($config['length']);
        switch ($config['type']) {
            case 'mixed':
                $generator->alphanumeric();
                break;
            case 'numeric':
                $generator->numbersonly();
                break;
            case 'characters':
                $generator->charactersonly();
                break;
            default:
                $generator->numbersonly();
        }
        switch ($config['case']) {
            case 'upper':
                $generator->uppercase();
                break;
            case 'lower':
                $generator->lowercase();
                break;
            case 'mixed':
                $generator->mixedcase();
                break;
            default:
                $generator->mixedcase();
        }

        return $generator->generate();
    }

    /**
     * @throws Exception
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        // Encrypt a new temporary key before writing to the database
        if (!$this->Used) {
            $member = $this->Member();
            $this->Token = $member->encryptWithUserSettings($this->Token);
        }
    }

    public function expire()
    {
        $this->IsUsed = true;
        $this->write();

        return $this;
    }
}
