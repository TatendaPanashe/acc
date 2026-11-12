<?php

namespace Tests\Feature;

use App\Models\Platform\Enquiry;
use App\Models\Platform\Plan;
use App\Notifications\NewEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_four_pages_render_for_visitors(): void
    {
        $this->get('/')->assertOk()->assertSee('Start your 14-day free trial')->assertSee('One login for the whole back office');
        $this->get(route('site.services'))->assertOk()->assertSee('Bank reconciliation')->assertSee('Compare plans');
        $this->get(route('site.how'))->assertOk()->assertSee('Guided setup')->assertSee('Frequently asked questions');
        $this->get(route('site.contact'))->assertOk()->assertSee('Send message');
    }

    public function test_pricing_comes_from_the_plans_table(): void
    {
        Plan::where('code', 'professional')->update(['monthly_price' => 45]);

        $this->get(route('site.services'))
            ->assertSee('$45')
            ->assertSee(route('register', ['plan' => 'professional']), false)
            ->assertSee('Talk to us'); // Enterprise is sold through the team
    }

    public function test_contact_form_saves_and_emails_the_enquiry(): void
    {
        Notification::fake();

        $this->post(route('site.contact.send'), [
            'name' => 'Rudo', 'email' => 'rudo@example.com', 'company' => 'Mopane Traders', 'topic' => 'demo',
            'message' => 'We would like a demo for our three branches.',
        ])->assertRedirect(route('site.contact'))->assertSessionHas('sent');

        $this->assertSame('demo', Enquiry::first()->topic);
        Notification::assertSentTo(new AnonymousNotifiable, NewEnquiry::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === config('site.email'));
    }

    public function test_contact_form_validates_and_ignores_bots(): void
    {
        $this->post(route('site.contact.send'), ['name' => '', 'email' => 'nope', 'topic' => 'sales', 'message' => 'hi'])
            ->assertSessionHasErrors(['name', 'email', 'message']);

        $this->post(route('site.contact.send'), ['name' => 'Bot', 'email' => 'bot@example.com', 'topic' => 'sales', 'message' => 'Buy cheap things now!', 'website' => 'http://spam'])
            ->assertSessionHas('sent');
        $this->assertSame(0, Enquiry::count());
    }

    public function test_signed_in_users_see_a_link_back_into_the_app(): void
    {
        $user = \App\Models\User::create(['name' => 'Root', 'username' => 'root', 'email' => 'root@example.com', 'password' => 'secret-password', 'role' => 'admin', 'is_superuser' => true]);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Open acc263');
        Enquiry::create(['name' => 'Rudo', 'email' => 'rudo@example.com', 'topic' => 'sales', 'message' => 'Pricing question please']);
        $this->get(route('system.enquiries.index'))->assertOk()->assertSee('Pricing question please');
    }
}
