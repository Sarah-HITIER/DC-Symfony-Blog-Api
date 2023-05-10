<?php

namespace App\Service;

class UserValidator
{
    public function isAdmin($decoded): bool
    {
        if ($decoded->roles != null && in_array('ROLE_ADMIN', $decoded->roles)) {
            return true;
        }
        return false;
    }
}
