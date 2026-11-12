<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

/** A message sent from the public website's contact form. */
class Enquiry extends Model
{
    public const TOPICS = [
        'sales' => 'Plans and pricing',
        'demo' => 'Book a demo',
        'enterprise' => 'Enterprise plan',
        'support' => 'Help with my account',
        'billing' => 'Billing question',
        'partnership' => 'Partnership',
        'other' => 'Something else',
    ];

    protected $table = 'platform_enquiries';

    protected $fillable = ['name', 'email', 'company', 'phone', 'topic', 'message', 'ip_address', 'handled_at', 'handled_by'];

    protected $casts = ['handled_at' => 'datetime'];

    public function topicLabel(): string
    {
        return self::TOPICS[$this->topic] ?? ucfirst($this->topic);
    }
}
