<?php

namespace App\Brain\Authorization;

/**
 * Roles and the permissions each grants.
 *
 * Ported from hp-enterprise-brain/app/Domain/Authorization/Role.php. The grant
 * table below is copied value-for-value; widening or narrowing it is a product
 * decision, not something a port gets to do quietly.
 *
 * WHERE A ROLE COMES FROM IN THIS INSTALLATION. The Brain issues its own tokens
 * carrying a `role` claim. Here there is no second login: the LMS's own token is
 * the credential, and it carries `user_profile_id` / `is_admin` instead. The
 * mapping from an LMS profile to one of these roles lives in config/brain.php
 * so that roles stay administered in one place — the LMS's profile master —
 * rather than being duplicated into a second user table.
 */
final class Role
{
    const VIEWER = 'viewer';
    const ANALYST = 'analyst';
    const MANAGER = 'manager';
    const ADMIN = 'admin';
    const TENANT_ADMIN = 'tenant_admin';

    /**
     * Roles this build recognises. `member` is deliberately absent: it is the
     * frontend's fallback for "no role", and it grants nothing here.
     *
     * @return array<int, string>
     */
    public static function allValues(): array
    {
        return [self::VIEWER, self::ANALYST, self::MANAGER, self::ADMIN, self::TENANT_ADMIN];
    }

    /**
     * @return array<int, string>
     */
    public static function permissions(string $role): array
    {
        switch ($role) {
            // The Analyst persona keeps the Brain honest: it must read
            // everything and curate evidence, but must not approve or execute.
            case self::VIEWER:
                return [Permission::READ];

            case self::ANALYST:
                return [
                    Permission::READ,
                    Permission::CREATE,
                    Permission::UPDATE,
                    Permission::EVIDENCE_CURATE,
                ];

            // The Manager persona receives a diagnosis, chooses an intervention
            // and approves consequential decisions.
            case self::MANAGER:
                return [
                    Permission::READ,
                    Permission::CREATE,
                    Permission::UPDATE,
                    Permission::EVIDENCE_CURATE,
                    Permission::DECISION_APPROVE,
                    Permission::ESO_EXECUTE,
                ];

            case self::ADMIN:
            case self::TENANT_ADMIN:
                return Permission::allValues();
        }

        return [];
    }

    public static function grants(string $role, string $permission): bool
    {
        return in_array($permission, self::permissions($role), true);
    }

    /**
     * The canonical role name, or null when it is not one this build knows.
     *
     * Null is what makes the gate fail closed — an unrecognised role denies
     * rather than falling through to a default.
     */
    public static function tryFromName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $lower = strtolower($name);

        return in_array($lower, self::allValues(), true) ? $lower : null;
    }
}
