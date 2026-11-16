<?php

namespace App\Console\Commands;

use App\Models\Platform\FiscalDevice;
use App\Modules\Accounting\Services\ZimraFdmsService;
use App\Services\Fiscal\FdmsException;
use Illuminate\Console\Command;

/** Closes open ZIMRA fiscal days each night for devices with automatic closing on. */
class CloseFiscalDays extends Command
{
    protected $signature = 'zimra:close-days {--company= : Only this company id}';

    protected $description = 'Close the open ZIMRA FDMS fiscal day for every active device';

    public function handle(ZimraFdmsService $fdms): int
    {
        FiscalDevice::where('enabled', true)->whereNotNull('registered_at')->where('auto_close_day', true)
            ->when($this->option('company'), fn ($q, $id) => $q->where('company_id', $id))
            ->each(function (FiscalDevice $device) use ($fdms) {
                try {
                    $device = $fdms->refresh($device);
                    if (!$device->dayIsOpen()) {
                        return;
                    }
                    $fdms->closeDay($device);
                    $this->info('Company '.$device->company_id.': fiscal day '.$device->fiscal_day_no.' closed.');
                } catch (FdmsException $e) {
                    $device->forceFill(['last_error' => 'Nightly close failed: '.$e->getMessage()])->save();
                    $this->error('Company '.$device->company_id.': '.$e->getMessage());
                }
            });

        return self::SUCCESS;
    }
}
