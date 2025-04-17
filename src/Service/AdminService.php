<?php

namespace App\Service;

class AdminService
{
    public function getAdminSports(array $roles): array
    {
        $adminSports = [];

        foreach ($roles as $role) {
            if (str_contains($role, 'ROLE_ADMIN')) {
                $adminSports[] = str_replace("ROLE_ADMIN_", "", $role);
            }
        }

        return $adminSports;
    }
}
