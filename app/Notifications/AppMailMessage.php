<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Action;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * MailMessage with the acc263 layout (resources/views/emails/notification.blade.php) and blocks for
 * the facts that matter: a highlighted figure, label/value summaries, line items and callout notes.
 * Lines and blocks added before action() render in the order they were added; lines after it follow the button.
 */
class AppMailMessage extends MailMessage
{
    public $markdown = 'emails.notification';

    public function with($line)
    {
        $beforeAction = !$line instanceof Action && !$this->actionText;
        parent::with($line);
        if ($beforeAction) {
            $this->block('line', ['text' => end($this->introLines)]);
        }

        return $this;
    }

    /** Keeps line breaks (Laravel joins them), so a line can hold a Markdown list. */
    protected function formatLine($line)
    {
        if ($line instanceof Htmlable) {
            return $line;
        }

        return trim(is_array($line) ? implode(' ', array_map('trim', $line)) : (string) $line);
    }

    private function block(string $type, array $data): static
    {
        $this->viewData['blocks'][] = ['type' => $type] + $data;

        return $this;
    }

    /** Brands the email as the company (header, sender name, reply-to, footer contact details). */
    public function brand(?Company $company): static
    {
        $name = $company?->name ?: config('app.name');
        $logo = $company?->logo_path && str_starts_with((string) config('app.url'), 'https://') ? asset('storage/'.$company->logo_path) : null;
        $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('');

        $this->viewData['brand'] = [
            'name' => $name,
            'initials' => $initials ?: 'A',
            'logo' => $logo,
            'url' => config('app.url'),
            'company' => (bool) $company,
            'contact' => $company ? collect([$company->address, $company->phone, $company->email])->filter()->implode(' · ') : null,
        ];

        if ($company) {
            $this->from(config('mail.from.address'), $name);
            if ($company->email) {
                $this->replyTo($company->email, $name);
            }
            $this->salutation('Kind regards,  '."\n".$name);
        }

        return $this;
    }

    /** Small label above the heading, e.g. "Payroll". */
    public function eyebrow(string $text): static
    {
        $this->viewData['eyebrow'] = $text;

        return $this;
    }

    /** Inbox preview text. */
    public function preheader(string $text): static
    {
        $this->viewData['preheader'] = $text;

        return $this;
    }

    /** The headline figure or status. $tone: primary, success, warning, danger. */
    public function highlight(string $label, string $value, ?string $note = null, string $tone = 'primary'): static
    {
        return $this->block('highlight', compact('label', 'value', 'note', 'tone'));
    }

    /** A table of label => value details (empty values are skipped). */
    public function summary(array $rows, ?string $title = null): static
    {
        return $this->block('summary', compact('rows', 'title'));
    }

    /** @param array<int, array{0: string, 1: string}> $rows [description, amount] */
    public function items(array $rows, ?string $total = null, string $totalLabel = 'Total', array $head = ['Description', 'Amount']): static
    {
        return $this->block('items', compact('rows', 'total', 'totalLabel', 'head'));
    }

    /** A callout box. $tone: info, success, warning, danger. */
    public function note(string $text, ?string $title = null, string $tone = 'info'): static
    {
        return $this->block('note', compact('text', 'title', 'tone'));
    }

    /** Why the recipient gets this email (shown in the footer). */
    public function footnote(string $text): static
    {
        $this->viewData['footnote'] = $text;

        return $this;
    }
}
