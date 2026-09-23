<?php

namespace App\Support;

/**
 * The known, available tile fields for each Laravel API a native dynamic
 * page can be configured to read from.
 *
 * A dynamic page's admin picks tiles from THIS list rather than typing a raw
 * JSON key, so a typo can't quietly configure a tile that reads nothing: the
 * only field_key/display_key pairs ever stored are ones this registry says
 * the endpoint actually returns. Extending which endpoints a dynamic page can
 * read from means adding an entry here, matched to that endpoint's real
 * response shape -- not opening the field picker up to arbitrary text.
 */
class MobileDynamicPageFieldRegistry
{
    /**
     * data_endpoint => [field_key => [label, field_type, display_key]].
     *
     * fees-dashboard/summary is FeesDashboardApiController@summary's
     * `summary` object (routes/api.php: POST /api/fees-dashboard/summary,
     * behind api.session + check_permissions -- the dynamic page's live data
     * call inherits that same authorization, unchanged). Keys are dotted
     * ("summary.collected_amount") because the response nests them one level
     * under a `summary` object rather than returning them at the top level.
     *
     * defaulters_count and students_considered are omitted: the controller
     * currently hardcodes both to 0 rather than computing them, so they are
     * not real fields yet.
     */
    private const REGISTRY = [
        'fees-dashboard/summary' => [
            'summary.collected_amount' => ['label' => 'Total Collected', 'field_type' => 'currency', 'display_key' => 'summary.collected_display'],
            'summary.demand_amount' => ['label' => 'Total Payable', 'field_type' => 'currency', 'display_key' => 'summary.demand_display'],
            'summary.outstanding_amount' => ['label' => 'Outstanding', 'field_type' => 'currency', 'display_key' => 'summary.outstanding_display'],
            'summary.collection_rate' => ['label' => 'Collection Rate', 'field_type' => 'percent', 'display_key' => 'summary.collection_rate_display'],
            'summary.receipts_count' => ['label' => 'Receipts Issued', 'field_type' => 'number', 'display_key' => null],
            'summary.fine_amount' => ['label' => 'Fine Collected', 'field_type' => 'currency', 'display_key' => null],
            'summary.discount_amount' => ['label' => 'Discount Given', 'field_type' => 'currency', 'display_key' => null],
        ],
    ];

    public static function endpoints(): array
    {
        return array_keys(self::REGISTRY);
    }

    public static function fieldsFor(string $dataEndpoint): array
    {
        return self::REGISTRY[$dataEndpoint] ?? [];
    }

    public static function isKnownField(string $dataEndpoint, string $fieldKey): bool
    {
        return array_key_exists($fieldKey, self::fieldsFor($dataEndpoint));
    }

    public static function defaultsFor(string $dataEndpoint, string $fieldKey): ?array
    {
        return self::fieldsFor($dataEndpoint)[$fieldKey] ?? null;
    }
}
