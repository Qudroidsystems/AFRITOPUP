<?php

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);
    $this->customer = User::where('email', 'customer@afritopup.test')->first();
    $this->support = User::where('email', 'support@afritopup.test')->first();
    $this->finance = User::where('email', 'finance@afritopup.test')->first();
});

it('lets a customer chat with support on the website, with read receipts and unread counts', function () {
    $this->actingAs($this->customer)->get(route('support.index'))->assertOk()->assertSee('Support');
    $conv = SupportConversation::where('user_id', $this->customer->id)->first();
    expect($conv->messages()->where('type', 'event')->count())->toBe(1);   // welcome

    $txn = $this->customer->transactions()->first();
    $this->actingAs($this->customer)->postJson(route('support.send'), ['body' => 'My data has not arrived', 'transaction' => $txn->reference])
        ->assertCreated()->assertJsonPath('message.mine', true)->assertJsonPath('message.transaction.reference', $txn->reference);
    expect($conv->fresh()->staff_unread)->toBe(1);

    // Staff see it, reply; that marks the customer's message read.
    $this->actingAs($this->support)->getJson(route('admin.support.list'))->assertOk()->assertJsonPath('conversations.0.unread', 1);
    $this->actingAs($this->support)->postJson(route('admin.support.send', $conv), ['body' => 'Sorry about that — checking now.'])->assertCreated();
    $conv->refresh();
    expect($conv->staff_unread)->toBe(0)->and($conv->customer_unread)->toBe(1)->and($conv->assigned_to)->toBe($this->support->id);

    // Customer polls: gets the reply, sees their message was read.
    $mine = SupportMessage::where('from_staff', false)->where('type', 'text')->first();
    $res = $this->actingAs($this->customer)->getJson(route('support.messages', ['after' => $mine->id, 'seen' => 1]))->assertOk();
    expect($res->json('messages'))->toHaveCount(1)
        ->and($res->json('messages.0.body'))->toBe('Sorry about that — checking now.')
        ->and($res->json('messages.0.sender'))->toContain('Support')
        ->and($res->json('conversation.read_up_to'))->toBe($mine->id);
    expect($conv->fresh()->customer_unread)->toBe(0);
    $this->actingAs($this->customer)->getJson(route('support.summary'))->assertJsonPath('unread', 0);
});

it('does not let a customer attach someone else\'s payment', function () {
    $agent = User::where('email', 'agent@afritopup.test')->first();
    $theirs = $agent->transactions()->first();
    $this->actingAs($this->customer)->postJson(route('support.send'), ['body' => 'look', 'transaction' => $theirs->reference])
        ->assertCreated()->assertJsonPath('message.transaction', null);
});

it('stores attachments privately and only shows them to the chat owner and support', function () {
    Storage::fake('local');
    $res = $this->actingAs($this->customer)->post(route('support.send'), ['attachment' => UploadedFile::fake()->image('screen.png', 300, 600)], ['Accept' => 'application/json'])->assertCreated();
    $msg = SupportMessage::find($res->json('message.id'));
    Storage::disk('local')->assertExists($msg->attachment_path);
    expect($res->json('message.attachment.image'))->toBeTrue();

    $this->actingAs($this->customer)->get(route('support.attachment', $msg))->assertOk();
    $this->actingAs($this->support)->get(route('support.attachment', $msg))->assertOk();
    $this->actingAs(User::where('email', 'agent@afritopup.test')->first())->get(route('support.attachment', $msg))->assertNotFound();

    $this->actingAs($this->customer)->postJson(route('support.send'), ['attachment' => UploadedFile::fake()->create('virus.exe', 10)])->assertStatus(422);
    $this->actingAs($this->customer)->postJson(route('support.send'), [])->assertStatus(422);
});

it('lets staff assign, solve and re-open; a customer message re-opens a solved chat', function () {
    $this->actingAs($this->customer)->postJson(route('support.send'), ['body' => 'Hello'])->assertCreated();
    $conv = SupportConversation::first();

    $this->actingAs($this->support)->post(route('admin.support.assign', $conv), ['agent' => $this->support->id])->assertRedirect();
    $this->actingAs($this->support)->post(route('admin.support.close', $conv))->assertRedirect();
    expect($conv->fresh()->status)->toBe('closed');

    $this->actingAs($this->customer)->postJson(route('support.send'), ['body' => 'One more thing'])->assertCreated();
    expect($conv->fresh()->status)->toBe('open');

    // Only people allowed to answer support can be assigned, and only they see the inbox.
    $this->actingAs($this->support)->post(route('admin.support.assign', $conv), ['agent' => $this->finance->id])->assertStatus(422);
    $this->actingAs($this->finance)->get(route('admin.support.index'))->assertForbidden();
    $this->actingAs($this->customer)->get(route('admin.support.index'))->assertForbidden();
    $this->actingAs($this->support)->get(route('admin.support.show', $conv))->assertOk()->assertSee($this->customer->name);
});

it('works from the app, including typing and older pages', function () {
    $token = $this->customer->createToken('t')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/support')->assertOk()->assertJsonPath('data.conversation.status', 'open');
    $this->withToken($token)->postJson('/api/v1/support/messages', ['body' => 'msg 1'])->assertCreated();
    $conv = SupportConversation::first();
    foreach (range(2, 45) as $i) SupportMessage::create(['conversation_id' => $conv->id, 'user_id' => $this->customer->id, 'body' => "msg $i"]);

    $first = $this->withToken($token)->getJson('/api/v1/support')->assertOk();
    expect($first->json('data.messages'))->toHaveCount(40)->and($first->json('data.has_more'))->toBeTrue();
    $older = $this->withToken($token)->getJson('/api/v1/support/messages?before=' . $first->json('data.messages.0.id'))->assertOk();
    expect($older->json('data.messages'))->toHaveCount(6);   // 5 messages + the welcome

    $this->withToken($token)->postJson('/api/v1/support/typing')->assertOk();
    $this->actingAs($this->support)->getJson(route('admin.support.messages', $conv))->assertJsonPath('conversation.typing', 'Ada');
});

it('lets someone who has not confirmed their e-mail still reach support', function () {
    $this->customer->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($this->customer->fresh())->get(route('topup.wallet'))->assertRedirect(route('verification.notice'));
    $this->actingAs($this->customer->fresh())->get(route('support.index'))->assertOk();
});

it('serves chat photos to the app with its token', function () {
    Storage::fake('local');
    $token = $this->customer->createToken('t')->plainTextToken;
    $res = $this->withToken($token)->post('/api/v1/support/messages', ['attachment' => UploadedFile::fake()->image('p.jpg')], ['Accept' => 'application/json'])->assertCreated();
    $url = $res->json('data.message.attachment.url');
    expect($url)->toContain('/api/v1/support/files/');
    $this->withToken($token)->get($url)->assertOk();
    $other = User::where('email', 'agent@afritopup.test')->first()->createToken('x')->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($other)->get($url)->assertNotFound();
});
