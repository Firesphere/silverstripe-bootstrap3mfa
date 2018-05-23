<?php

/**
 * Class MFAAuthenticator
 */
class MFAAuthenticator extends MemberAuthenticator
{
    const SESSION_KEY = 'MFALogin';

    /**
     * @param Controller $controller
     * @return MemberLoginForm|static
     */
    public static function get_login_form(Controller $controller)
    {
        return MFALoginForm::create($controller, 'LoginForm');
    }

    /**
     * @param Member $member
     * @param string $token
     * @param ValidationResult|null $result
     * @return ValidationResult|Member
     * @throws Exception
     */
    public function validateBackupCode($member, $token, &$result = null)
    {
        if (!$result) {
            $result = new ValidationResult();
        }
        $token = $member->encryptWithUserSettings($token);

        /** @var MFABackupCode $backupCode */
        $backupCode = MFABackupCode::getValidTokensForMember($member)
            ->filter(['Token' => $token])
            ->first();

        if ($backupCode && $backupCode->exists()) {
            $backupCode->expire();

            return $member;
        }

        $member->registerFailedLogin();
        $result->error('Invalid token');

        return $result;
    }
}
