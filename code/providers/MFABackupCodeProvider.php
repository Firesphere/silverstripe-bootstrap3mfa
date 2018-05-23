<?php

class MFABackupCodeProvider implements MFAProvider
{
    /**
     * @var Member
     */
    protected $member;

    /**
     * @param string $token
     * @param ValidationResult $result
     * @return Member|bool
     * @throws Exception
     */
    public function verifyToken($token, ValidationResult $result)
    {
        if (!$result) {
            $result = new ValidationResult();
        }
        $member = $this->getMember();
        /** @var MFAAuthenticator $authenticator */
        $authenticator = Injector::inst()->get(MFAAuthenticator::class);
        $authenticator->setMember($member);

        return $authenticator->validateBackupCode($member, $token, $result);
    }

    /**
     * @return Member|null
     */
    public function getMember()
    {
        return $this->member;
    }

    /**
     * @param Member $member
     */
    public function setMember($member)
    {
        $this->member = $member;
    }

    /**
     * @throws Exception
     */
    public function updateTokens()
    {
        // Clear any possible tokens in the session, just to be sure
        Session::clear('tokens');

        if ($member = $this->getMember()) {
            /** @var DataList|MFABackupCode[] $expiredCodes */
            $expiredCodes = MFABackupCode::get()->filter(['MemberID' => $member->ID]);
            $expiredCodes->removeAll();

            MFABackupCode::generateTokensForMember($member);
        }
        // Fail silently
    }
}
