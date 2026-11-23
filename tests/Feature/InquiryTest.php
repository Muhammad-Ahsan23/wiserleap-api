<?php

namespace Tests\Feature;

use App\Mail\InquiryReplyMail;
use App\Mail\NewInquiryMail;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class InquiryTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-site-key-0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        config(['contact.site_key' => self::KEY, 'contact.notify_email' => 'hello@wiserleap.com', 'mail.from.address' => 'hello@wiserleap.com']);
    }

    private function submit(array $overrides = [], array $headers = [])
    {
        return $this->postJson('/api/v1/contact', array_merge([
            'name' => 'Sara Khan',
            'email' => 'Sara@Example.com',
            'subject' => 'New website',
            'budget' => '$5k - $10k',
            'message' => 'We need a new marketing website with a blog.',
        ], $overrides), array_merge(['X-Site-Key' => self::KEY, 'X-Visitor-Ip' => '203.0.113.7', 'X-Visitor-Agent' => 'Mozilla/5.0 Test'], $headers));
    }

    public function test_contact_endpoint_is_closed_without_a_configured_key(): void
    {
        config(['contact.site_key' => '']);
        $this->submit()->assertStatus(503);
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_contact_endpoint_rejects_a_missing_or_wrong_key(): void
    {
        $this->submit([], ['X-Site-Key' => 'wrong'])->assertForbidden();
        $this->postJson('/api/v1/contact', ['name' => 'x'])->assertForbidden();
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_inquiry_is_saved_and_the_inbox_is_emailed(): void
    {
        Mail::fake();

        $this->submit()->assertCreated()->assertJsonStructure(['message']);

        $inquiry = Inquiry::sole();
        $this->assertSame('sara@example.com', $inquiry->email);
        $this->assertSame('new', $inquiry->status->value);
        $this->assertSame('203.0.113.7', $inquiry->ip_address);
        $this->assertSame('Mozilla/5.0 Test', $inquiry->user_agent);
        $this->assertNotNull($inquiry->notified_at);

        Mail::assertSent(NewInquiryMail::class, fn (NewInquiryMail $mail) => $mail->hasTo('hello@wiserleap.com')
            && $mail->hasReplyTo('sara@example.com')
            && $mail->hasSubject('New website inquiry: New website'));
    }

    public function test_invalid_visitor_ip_header_falls_back_to_the_request_ip(): void
    {
        Mail::fake();
        $this->submit([], ['X-Visitor-Ip' => 'not-an-ip'])->assertCreated();
        $this->assertSame('127.0.0.1', Inquiry::sole()->ip_address);
    }

    public function test_validation_errors(): void
    {
        $this->submit(['name' => '', 'email' => 'not-an-email', 'message' => 'short'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'message'])
            ->assertJsonPath('errors.name.0', 'Please enter your full name.');

        $this->submit(['message' => str_repeat('a', 5001)])->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_control_characters_are_stripped_from_single_line_fields(): void
    {
        Mail::fake();
        $this->submit(['name' => "Sara\r\nBcc: victim@example.com", 'message' => "Line one\r\nLine two\u{0007}"])->assertCreated();

        $inquiry = Inquiry::sole();
        $this->assertStringNotContainsString("\n", $inquiry->name);
        $this->assertSame("Line one\nLine two", $inquiry->message);
    }

    public function test_resubmitting_the_same_message_does_not_create_a_duplicate(): void
    {
        Mail::fake();
        $this->submit()->assertCreated();
        $this->submit(['message' => "  We need a new marketing   website with a blog. "])->assertCreated();

        $this->assertDatabaseCount('inquiries', 1);
        Mail::assertSentCount(1);
    }

    public function test_inquiry_is_kept_when_the_notification_email_fails(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        $this->submit()->assertCreated();
        $this->assertNull(Inquiry::sole()->notified_at);
    }

    public function test_contact_form_is_rate_limited_per_visitor(): void
    {
        Mail::fake();
        for ($i = 1; $i <= 5; $i++) {
            $this->submit(['email' => "p{$i}@example.com"])->assertCreated();
        }
        $this->submit(['email' => 'p6@example.com'])->assertTooManyRequests();

        // A different visitor is not blocked.
        $this->submit(['email' => 'other@example.com'], ['X-Visitor-Ip' => '198.51.100.20'])->assertCreated();
    }

    public function test_user_input_is_escaped_in_the_html_email(): void
    {
        $inquiry = Inquiry::create([
            'name' => '<b>Eve</b>', 'email' => 'eve@example.com', 'message' => '<script>alert(1)</script> hello there',
            'fingerprint' => str_repeat('a', 64),
        ]);

        $html = (new NewInquiryMail($inquiry))->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_admin_routes_require_login(): void
    {
        $this->getJson('/api/v1/admin/inquiries')->assertUnauthorized();
        $inquiry = Inquiry::create(['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hello there team', 'fingerprint' => str_repeat('b', 64)]);
        $this->postJson("/api/v1/admin/inquiries/{$inquiry->id}/reply", ['message' => 'Hi'])->assertUnauthorized();
    }

    public function test_admin_list_with_counts_search_and_status_filter(): void
    {
        $this->actingAs(User::factory()->create());
        Inquiry::create(['name' => 'Ali', 'email' => 'ali@example.com', 'subject' => 'App', 'message' => 'Mobile app please', 'fingerprint' => str_repeat('c', 64)]);
        Inquiry::create(['name' => 'Bina', 'email' => 'bina@example.com', 'message' => 'Website redesign', 'status' => 'replied', 'fingerprint' => str_repeat('d', 64)]);

        $this->getJson('/api/v1/admin/inquiries')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.counts.all', 2)
            ->assertJsonPath('meta.counts.new', 1)
            ->assertJsonPath('meta.counts.replied', 1)
            ->assertJsonPath('data.0.preview', 'Website redesign')
            ->assertJsonMissingPath('data.0.message')
            ->assertJsonMissingPath('data.0.ipAddress');

        $this->getJson('/api/v1/admin/inquiries?status=new')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ali');
        $this->getJson('/api/v1/admin/inquiries?search=redesign')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bina');
        $this->getJson('/api/v1/admin/inquiries?status=bogus')->assertUnprocessable();

        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('inquiries.new', 1)->assertJsonPath('inquiries.total', 2);
    }

    public function test_opening_marks_read_and_status_can_change(): void
    {
        $this->actingAs(User::factory()->create());
        $inquiry = Inquiry::create(['name' => 'Ali', 'email' => 'ali@example.com', 'message' => 'Mobile app please', 'fingerprint' => str_repeat('e', 64)]);

        $this->getJson("/api/v1/admin/inquiries/{$inquiry->id}")
            ->assertOk()->assertJsonPath('data.status', 'read')->assertJsonPath('data.message', 'Mobile app please')->assertJsonPath('data.replies', []);
        $this->assertNotNull($inquiry->fresh()->read_at);

        $this->patchJson("/api/v1/admin/inquiries/{$inquiry->id}/status", ['status' => 'archived'])->assertOk()->assertJsonPath('data.status', 'archived');
        $this->patchJson("/api/v1/admin/inquiries/{$inquiry->id}/status", ['status' => 'new'])->assertOk()->assertJsonPath('data.readAt', null);
        $this->patchJson("/api/v1/admin/inquiries/{$inquiry->id}/status", ['status' => 'spam'])->assertUnprocessable();
    }

    public function test_reply_emails_the_visitor_and_is_recorded(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['name' => 'Ahsan']);
        $this->actingAs($admin);
        $inquiry = Inquiry::create(['name' => 'Ali', 'email' => 'ali@example.com', 'subject' => 'App', 'message' => 'Mobile app please', 'fingerprint' => str_repeat('f', 64)]);

        $this->postJson("/api/v1/admin/inquiries/{$inquiry->id}/reply", ['message' => "Hi Ali,\nThanks for reaching out."])
            ->assertCreated()
            ->assertJsonPath('data.status', 'replied')
            ->assertJsonPath('data.replies.0.subject', 'Re: App')
            ->assertJsonPath('data.replies.0.by', 'Ahsan')
            ->assertJsonPath('data.replies.0.body', "Hi Ali,\nThanks for reaching out.");

        Mail::assertSent(InquiryReplyMail::class, fn (InquiryReplyMail $mail) => $mail->hasTo('ali@example.com')
            && $mail->hasFrom('hello@wiserleap.com')
            && $mail->hasReplyTo('hello@wiserleap.com')
            && $mail->hasSubject('Re: App'));
        $this->assertNotNull($inquiry->fresh()->replied_at);

        $this->postJson("/api/v1/admin/inquiries/{$inquiry->id}/reply", ['message' => ''])->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_failed_reply_email_is_not_recorded(): void
    {
        $this->actingAs(User::factory()->create());
        $inquiry = Inquiry::create(['name' => 'Ali', 'email' => 'ali@example.com', 'message' => 'Mobile app please', 'fingerprint' => str_repeat('g', 64)]);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        $this->postJson("/api/v1/admin/inquiries/{$inquiry->id}/reply", ['message' => 'Hello Ali'])->assertStatus(502);
        $this->assertDatabaseCount('inquiry_replies', 0);
        $this->assertSame('new', $inquiry->fresh()->status->value);
    }

    public function test_delete_removes_the_inquiry_and_its_replies(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->create());
        $inquiry = Inquiry::create(['name' => 'Ali', 'email' => 'ali@example.com', 'message' => 'Mobile app please', 'fingerprint' => str_repeat('h', 64)]);
        $this->postJson("/api/v1/admin/inquiries/{$inquiry->id}/reply", ['message' => 'Hello Ali'])->assertCreated();

        $this->deleteJson("/api/v1/admin/inquiries/{$inquiry->id}")->assertOk();
        $this->assertDatabaseCount('inquiries', 0);
        $this->assertDatabaseCount('inquiry_replies', 0);
        $this->getJson("/api/v1/admin/inquiries/{$inquiry->id}")->assertNotFound();
    }
}
