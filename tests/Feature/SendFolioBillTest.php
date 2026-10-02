<?php

declare(strict_types=1);

use App\Livewire\Folios\Show;
use App\Mail\FolioBillMail;
use App\Models\Folio;
use App\Models\Guest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();

    $this->hotel->update([
        'name' => 'Serengeti Boutique Hotel',
        'phone' => '+255 22 000 0000',
    ]);

    $this->guest = Guest::factory()->for($this->hotel)->create([
        'first_name' => 'Amina',
        'last_name' => 'Hassan',
        'email' => 'amina@example.com',
        'phone' => '0712345678',
    ]);

    $this->folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'guest_id' => $this->guest->id,
        'status' => 'open',
        'total_amount' => 141_600_00,
        'paid_total' => 50_000_00,
    ]);

    config()->set('services.beem', [
        'api_key' => 'test-key',
        'secret_key' => 'test-secret',
        'source_addr' => 'INFO',
        'endpoint' => 'https://apisms.beem.africa/v1/send',
    ]);
});

it('prefills recipient contacts from the guest', function (): void {
    Livewire::test(Show::class, ['folio' => $this->folio])
        ->assertSet('recipient_email', 'amina@example.com')
        ->assertSet('recipient_phone', '0712345678');
});

it('sends the bill by email', function (): void {
    Mail::fake();

    Livewire::test(Show::class, ['folio' => $this->folio])
        ->set('recipient_email', 'guest@example.com')
        ->call('sendByEmail')
        ->assertHasNoErrors();

    Mail::assertSent(FolioBillMail::class, function (FolioBillMail $mail) {
        return $mail->hasTo('guest@example.com')
            && $mail->folio->is($this->folio);
    });
});

it('rejects an invalid email', function (): void {
    Mail::fake();

    Livewire::test(Show::class, ['folio' => $this->folio])
        ->set('recipient_email', 'not-an-email')
        ->call('sendByEmail')
        ->assertHasErrors('recipient_email');

    Mail::assertNothingSent();
});

it('sends the bill by SMS via the Beem endpoint', function (): void {
    Http::fake([
        'apisms.beem.africa/*' => Http::response(['successful' => true, 'request_id' => 42], 200),
    ]);

    Livewire::test(Show::class, ['folio' => $this->folio])
        ->set('recipient_phone', '0712345678')
        ->call('sendBySms')
        ->assertHasNoErrors();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://apisms.beem.africa/v1/send'
            && $request->method() === 'POST'
            && $request['source_addr'] === 'INFO'
            && $request['recipients'][0]['dest_addr'] === '255712345678'
            && str_contains($request['message'], $this->folio->reference);
    });
});

it('shows a toast when the SMS API errors out', function (): void {
    Http::fake([
        'apisms.beem.africa/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    Livewire::test(Show::class, ['folio' => $this->folio])
        ->set('recipient_phone', '0712345678')
        ->call('sendBySms')
        ->assertHasNoErrors();

    Http::assertSentCount(1);
});

it('rejects an empty SMS recipient', function (): void {
    Http::fake();

    Livewire::test(Show::class, ['folio' => $this->folio])
        ->set('recipient_phone', '')
        ->call('sendBySms')
        ->assertHasErrors('recipient_phone');

    Http::assertNothingSent();
});
