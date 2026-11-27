<?php

namespace App\Services\Platform\Fees;

use InvalidArgumentException;

/**
 * An approved concession/refund grant cannot be applied as asked — wrong
 * tenant, wrong student, wrong kind, already fully applied, or the amount
 * requested exceeds what remains. Thrown from inside the fee-receipt/refund
 * transaction so the caller rolls the whole write back rather than saving a
 * receipt with no matching approval.
 */
class ConcessionGrantNotApplicable extends InvalidArgumentException
{
}
