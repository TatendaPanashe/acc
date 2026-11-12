<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantModel;
use App\Modules\Accounting\Models\Concerns\Auditable;

abstract class AccountingModel extends TenantModel
{
    use Auditable;

    /** Whether create/update/delete is written to the audit trail automatically. */
    protected static bool $audited = true;
}
