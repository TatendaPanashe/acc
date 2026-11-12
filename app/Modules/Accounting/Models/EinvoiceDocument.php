<?php

namespace App\Modules\Accounting\Models;

class EinvoiceDocument extends AccountingModel
{
    protected $table = 'accounting_einvoice_documents';

    protected $fillable = [
        'company_id', 'document_number', 'jurisdiction', 'network', 'taxable_amount',
        'tax_amount', 'status', 'authority_reference', 'payload',
    ];

    protected $casts = ['taxable_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'payload' => 'array'];
}
