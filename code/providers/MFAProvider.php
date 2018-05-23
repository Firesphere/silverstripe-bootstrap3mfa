<?php

interface MFAProvider
{
    /**
     * @param Member $member
     */
    public function setMember($member);

    /**
     * @param string $backupCode
     * @param ValidationResult $result
     * @return bool
     */
    public function verifyToken($backupCode, ValidationResult $result);
}
