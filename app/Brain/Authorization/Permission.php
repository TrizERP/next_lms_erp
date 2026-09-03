<?php

namespace App\Brain\Authorization;

/**
 * The permissions the Enterprise Brain route table is written against.
 *
 * Ported from hp-enterprise-brain/app/Domain/Authorization/Permission.php,
 * which is a PHP 8.1 backed enum. This project targets PHP 8.0 (composer.json
 * declares "php": "^8.0" and nothing in app/ uses enums, readonly properties or
 * first-class callables), so the same closed set is expressed as class
 * constants. The string values are byte-identical to the reference, because
 * they are what every route and every stored audit row names.
 *
 * The names are verb-scoped rather than table-scoped on purpose: the sensitive
 * operations here are not CRUD. Approving a decision and executing an ESO are
 * governance acts and have to be separable from ordinary writes.
 */
final class Permission
{
    const READ = 'read';
    const CREATE = 'create';
    const UPDATE = 'update';
    const DELETE = 'delete';
    const EVIDENCE_CURATE = 'evidence.curate';
    const DECISION_APPROVE = 'decision.approve';
    const ESO_EXECUTE = 'eso.execute';
    const SETTINGS_MANAGE = 'settings.manage';
    const APIKEY_MANAGE = 'apikey.manage';
    const EVENTS_MANAGE = 'events.manage';
    const TENANT_MANAGE = 'tenant.manage';

    /** @return array<int, string> */
    public static function allValues(): array
    {
        return [
            self::READ,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
            self::EVIDENCE_CURATE,
            self::DECISION_APPROVE,
            self::ESO_EXECUTE,
            self::SETTINGS_MANAGE,
            self::APIKEY_MANAGE,
            self::EVENTS_MANAGE,
            self::TENANT_MANAGE,
        ];
    }

    /**
     * The permission name, or null if it is not one this build knows.
     *
     * Returning null rather than throwing is what lets the gate fail closed on a
     * typo in a route definition instead of 500-ing.
     */
    public static function tryFrom(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return in_array($name, self::allValues(), true) ? $name : null;
    }
}
