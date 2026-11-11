<?php

namespace Tests\Feature;

use App\Models\Chat\ChatConversation;
use App\Models\Chat\ChatConversationMember;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatMessageAttachment;
use App\Models\Chat\ChatUserSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DgtChatTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected User $userC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create([
            'name'         => 'Test User Alpha',
            'is_active'    => true,
            'can_use_chat' => true,
        ]);

        $this->userB = User::factory()->create([
            'name'         => 'Test User Beta',
            'is_active'    => true,
            'can_use_chat' => true,
        ]);

        $this->userC = User::factory()->create([
            'name'         => 'Test User Gamma',
            'is_active'    => true,
            'can_use_chat' => true,
        ]);
    }

    public function test_unauthenticated_user_is_redirected_from_chat(): void
    {
        $response = $this->get('/chat');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_chat_workspace(): void
    {
        $response = $this->actingAs($this->userA)->get('/chat');
        $response->assertStatus(200);
        $response->assertSee('DGT Chat');
        $response->assertSee('chat-composer-textarea');
        $response->assertSee('telegram-actions-layer');
        $response->assertSee('telegram-action-btn-pin');
        $response->assertSee('telegram-action-btn-delete');
        $response->assertSee('telegram-action-btn-archive');
        $response->assertSee('telegram-chat-card');
        $response->assertSee('telegram-context-menu');
        $response->assertSee('Pin to Top');
        $response->assertSee('Delete Chat');
        $response->assertSee('Archive Chat');
    }

    public function test_user_can_start_private_chat(): void
    {
        $response = $this->actingAs($this->userA)->postJson('/chat/conversations/private', [
            'user_id' => $this->userB->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $convId = $response->json('conversation.id');
        $this->assertNotNull($convId);

        $conversation = ChatConversation::find($convId);
        $this->assertEquals('private', $conversation->type);
        $this->assertCount(2, $conversation->members);
    }

    public function test_existing_private_chat_is_reused_without_duplication(): void
    {
        // First creation
        $res1 = $this->actingAs($this->userA)->postJson('/chat/conversations/private', [
            'user_id' => $this->userB->id,
        ]);
        $id1 = $res1->json('conversation.id');

        // Second attempt by User B towards User A
        $res2 = $this->actingAs($this->userB)->postJson('/chat/conversations/private', [
            'user_id' => $this->userA->id,
        ]);
        $id2 = $res2->json('conversation.id');

        $this->assertEquals($id1, $id2, 'Private conversations between same 2 users must be reused and not duplicated');
    }

    public function test_user_can_create_group_chat_with_members(): void
    {
        $response = $this->actingAs($this->userA)->postJson('/chat/conversations/group', [
            'title'       => 'Digital Production Unit',
            'description' => 'Coordination for all production sprints',
            'user_ids'    => [$this->userB->id, $this->userC->id],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $convId = $response->json('conversation.id');
        $conversation = ChatConversation::find($convId);

        $this->assertEquals('group', $conversation->type);
        $this->assertEquals('Digital Production Unit', $conversation->title);
        // User A (creator) + User B + User C = 3 members
        $this->assertCount(3, $conversation->members);

        // Check creator is assigned admin role
        $memberA = $conversation->members->where('user_id', $this->userA->id)->first();
        $this->assertEquals('admin', $memberA->role);
    }

    public function test_user_can_send_text_message_to_conversation(): void
    {
        // Create conversation
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $response = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/messages", [
            'body' => 'Hello User B, this is a real-time message!',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals('Hello User B, this is a real-time message!', $response->json('message.body'));

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'content'         => 'Hello User B, this is a real-time message!',
        ]);
    }

    public function test_user_can_send_message_with_attachments(): void
    {
        Storage::fake('public');

        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $file = UploadedFile::fake()->image('test_screenshot.png');

        $response = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/messages", [
            'body'        => 'Check this screenshot',
            'attachments' => [$file],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $messageId = $response->json('message.id');
        $att = ChatMessageAttachment::where('message_id', $messageId)->first();
        $this->assertNotNull($att);
        $this->assertTrue(str_starts_with($att->file_name, 'test_screenshot'));
    }

    public function test_unauthorized_user_cannot_access_conversation_messages(): void
    {
        // Conversation between A and B
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        // User C (not in conversation) tries to access
        $response = $this->actingAs($this->userC)->getJson("/chat/conversations/{$conv->id}/messages");
        $response->assertStatus(403);
    }

    public function test_sync_endpoint_returns_only_new_messages_after_id(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $msg1 = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'body'            => 'Message 1',
        ]);

        $msg2 = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userB->id,
            'body'            => 'Message 2',
        ]);

        // Sync with after_id = msg1->id should only return msg2
        $response = $this->actingAs($this->userA)->getJson("/chat/conversations/{$conv->id}/sync?after_id={$msg1->id}");
        $response->assertStatus(200);

        $newMessages = $response->json('new_messages');
        $this->assertCount(1, $newMessages);
        $this->assertEquals($msg2->id, $newMessages[0]['id']);
        $this->assertEquals('Message 2', $newMessages[0]['body']);
    }

    public function test_unread_count_endpoint_accurately_counts_unread_messages(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        // User A sends 3 messages to User B
        ChatMessage::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'body' => 'Msg 1']);
        ChatMessage::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'body' => 'Msg 2']);
        ChatMessage::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'body' => 'Msg 3']);

        // User B checks unread count
        $response = $this->actingAs($this->userB)->getJson('/chat/unread-count');
        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('unread_count'));
    }

    public function test_user_can_toggle_message_reaction(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'body'            => 'React to this!',
        ]);

        // Toggle Reaction Add (❤️)
        $resAdd = $this->actingAs($this->userB)->postJson("/chat/messages/{$msg->id}/reactions", [
            'emoji' => '❤️',
        ]);
        $resAdd->assertStatus(200);
        $this->assertDatabaseHas('chat_message_reactions', [
            'message_id' => $msg->id,
            'user_id'    => $this->userB->id,
            'reaction'   => '❤️',
        ]);

        // Toggle Reaction Remove
        $resRemove = $this->actingAs($this->userB)->postJson("/chat/messages/{$msg->id}/reactions", [
            'emoji' => '❤️',
        ]);
        $resRemove->assertStatus(200);
        $this->assertDatabaseMissing('chat_message_reactions', [
            'message_id' => $msg->id,
            'user_id'    => $this->userB->id,
            'reaction'   => '❤️',
        ]);
    }

    public function test_user_can_edit_own_message(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'content'         => 'Original message text',
        ]);

        $response = $this->actingAs($this->userA)->patchJson("/chat/messages/{$msg->id}", [
            'body' => 'Edited message text',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('chat_messages', [
            'id'        => $msg->id,
            'content'   => 'Edited message text',
            'is_edited' => true,
        ]);
    }

    public function test_user_cannot_edit_another_users_message(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'body'            => 'Message by User A',
        ]);

        $response = $this->actingAs($this->userB)->patchJson("/chat/messages/{$msg->id}", [
            'body' => 'Tampered text',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_own_message(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'body'            => 'Delete me',
        ]);

        $response = $this->actingAs($this->userA)->deleteJson("/chat/messages/{$msg->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('chat_messages', ['id' => $msg->id]);
    }

    public function test_user_can_pin_and_mute_conversation(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $memberA = $conv->members()->create(['user_id' => $this->userA->id]);

        // Toggle Pin
        $resPin = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/pin");
        $resPin->assertStatus(200);
        $this->assertTrue($resPin->json('is_pinned'));

        // Toggle Mute
        $resMute = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/mute");
        $resMute->assertStatus(200);
        $this->assertTrue($resMute->json('is_muted'));
    }

    public function test_user_can_toggle_archive_and_filter_by_archived_tab(): void
    {
        $chatService = app(\App\Services\Chat\DgtChatService::class);
        $conv = $chatService->getOrCreatePrivateChat($this->userA, $this->userB->id);

        // 1. Initial state: not archived
        $listRes = $this->actingAs($this->userA)->getJson('/chat/conversations');
        $item = collect($listRes->json('conversations'))->firstWhere('id', $conv->id);
        $this->assertNotNull($item);
        $this->assertFalse($item['is_archived']);

        // 2. Toggle Archive -> Archived = true
        $resArchive = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/archive");
        $resArchive->assertStatus(200);
        $this->assertTrue($resArchive->json('is_archived'));

        $this->assertDatabaseHas('chat_conversation_members', [
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'is_archived'     => true,
        ]);

        // User B's archive status is independent and unchanged
        $this->assertDatabaseHas('chat_conversation_members', [
            'conversation_id' => $conv->id,
            'user_id'         => $this->userB->id,
            'is_archived'     => false,
        ]);

        // 3. Requesting tab=archived returns the archived conversation
        $archivedRes = $this->actingAs($this->userA)->getJson('/chat/conversations?tab=archived');
        $archivedItem = collect($archivedRes->json('conversations'))->firstWhere('id', $conv->id);
        $this->assertNotNull($archivedItem);
        $this->assertTrue($archivedItem['is_archived']);

        // 4. Requesting tab=unarchived excludes the archived conversation
        $unarchivedRes = $this->actingAs($this->userA)->getJson('/chat/conversations?tab=unarchived');
        $unarchivedItem = collect($unarchivedRes->json('conversations'))->firstWhere('id', $conv->id);
        $this->assertNull($unarchivedItem);

        // 5. Unarchive -> is_archived becomes false
        $resUnarchive = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/archive");
        $resUnarchive->assertStatus(200);
        $this->assertFalse($resUnarchive->json('is_archived'));

        $this->assertDatabaseHas('chat_conversation_members', [
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'is_archived'     => false,
        ]);
    }

    public function test_user_can_archive_virtual_colleague_contact(): void
    {
        // Colleague user C who has no prior messages with User A
        $res = $this->actingAs($this->userA)->postJson("/chat/conversations/member-{$this->userC->id}/archive");
        $res->assertStatus(200);
        $this->assertTrue($res->json('is_archived'));
        $this->assertNotNull($res->json('conversation_id'));

        $convId = $res->json('conversation_id');
        $this->assertDatabaseHas('chat_conversation_members', [
            'conversation_id' => $convId,
            'user_id'         => $this->userA->id,
            'is_archived'     => true,
        ]);
    }

    public function test_archived_conversations_do_not_count_towards_global_unread(): void
    {
        $chatService = app(\App\Services\Chat\DgtChatService::class);
        $conv = $chatService->getOrCreatePrivateChat($this->userA, $this->userB->id);

        // Send a message from User B to User A
        $chatService->sendMessage($this->userB, $conv->id, ['content' => 'Hello unread test']);

        // Initial unread count for User A is 1
        $countRes = $this->actingAs($this->userA)->getJson('/chat/unread-count');
        $countRes->assertStatus(200);
        $this->assertEquals(1, $countRes->json('unread_count'));

        // User A archives the conversation
        $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/archive");

        // Now global unread count for User A ignores the archived conversation -> 0
        $countRes2 = $this->actingAs($this->userA)->getJson('/chat/unread-count');
        $countRes2->assertStatus(200);
        $this->assertEquals(0, $countRes2->json('unread_count'));
    }

    public function test_user_can_customize_and_save_chat_settings(): void
    {
        $response = $this->actingAs($this->userA)->postJson('/chat/settings', [
            'theme'              => 'dark',
            'bubble_color'       => '#059669',
            'wallpaper'          => 'mesh',
            'font_size'          => 'large',
            'notification_sound' => true,
            'status_message'     => 'In a client strategy meeting',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('chat_user_settings', [
            'user_id'        => $this->userA->id,
            'theme'          => 'dark',
            'bubble_color'   => '#059669',
            'wallpaper'      => 'mesh',
            'status_message' => 'In a client strategy meeting',
        ]);
    }

    public function test_unauthorized_user_cannot_download_private_attachment(): void
    {
        Storage::fake('public');

        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'body'            => 'Attachment test',
        ]);

        $att = ChatMessageAttachment::create([
            'message_id' => $msg->id,
            'file_name'  => 'financials.pdf',
            'file_path'  => 'chat_attachments/test.pdf',
            'file_type'  => 'application/pdf',
            'file_size'  => 1024,
        ]);

        // User C (not in conversation) tries to download attachment
        $response = $this->actingAs($this->userC)->getJson("/chat/attachments/{$att->id}/download");
        $response->assertStatus(403);
    }

    public function test_user_can_access_or_create_saved_messages(): void
    {
        $response = $this->actingAs($this->userA)->getJson('/chat/saved-messages');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'conversation' => [
                'title' => 'Saved Messages',
            ],
        ]);

        $convId = $response->json('conversation_id');
        $this->assertDatabaseHas('chat_conversations', [
            'id'         => $convId,
            'title'      => 'Saved Messages',
            'created_by' => $this->userA->id,
        ]);
    }

    public function test_user_can_create_poll_in_conversation(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $response = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/poll", [
            'question'         => 'What design framework should we adopt?',
            'options'          => ['Tailwind CSS', 'Vanilla CSS', 'Bootstrap'],
            'multiple_answers' => false,
            'is_anonymous'     => false,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals('What design framework should we adopt?', $response->json('message.poll.question'));
        $this->assertCount(3, $response->json('message.poll.options'));

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
        ]);
    }

    public function test_user_can_vote_on_poll(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $pollRes = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/poll", [
            'question' => 'Launch date?',
            'options'  => ['Monday', 'Friday'],
        ]);
        $msgId = $pollRes->json('message.id');
        $optionId = $pollRes->json('message.poll.options.0.id');

        $voteRes = $this->actingAs($this->userB)->postJson("/chat/messages/{$msgId}/vote", [
            'option_id' => $optionId,
        ]);

        $voteRes->assertStatus(200);
        $voteRes->assertJson(['success' => true]);
        $this->assertEquals(1, $voteRes->json('message.poll.total_votes'));
        $this->assertEquals(100, $voteRes->json('message.poll.options.0.percentage'));
        $this->assertTrue($voteRes->json('message.poll.user_has_voted'));
    }

    public function test_user_can_unsend_own_message(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'body'            => 'Secret confidential note',
        ]);

        $response = $this->actingAs($this->userA)->postJson("/chat/messages/{$msg->id}/unsend");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertTrue($response->json('message.is_unsent'));
        $this->assertEquals('🚫 This message was unsent', $response->json('message.body'));

        $this->assertDatabaseHas('chat_messages', [
            'id'        => $msg->id,
            'is_unsent' => 1,
            'content'   => '🚫 This message was unsent',
        ]);
    }

    public function test_user_cannot_unsend_other_user_message(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id, 'role' => 'member']);
        $conv->members()->create(['user_id' => $this->userB->id, 'role' => 'member']);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'body'            => 'User A original statement',
        ]);

        $response = $this->actingAs($this->userB)->postJson("/chat/messages/{$msg->id}/unsend");
        $response->assertStatus(403);
    }

    public function test_user_can_forward_message_to_another_conversation(): void
    {
        // Conv 1: A and B
        $conv1 = ChatConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        $conv1->members()->create(['user_id' => $this->userA->id]);
        $conv1->members()->create(['user_id' => $this->userB->id]);

        // Conv 2: A and C
        $conv2 = ChatConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        $conv2->members()->create(['user_id' => $this->userA->id]);
        $conv2->members()->create(['user_id' => $this->userC->id]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv1->id,
            'user_id'         => $this->userB->id,
            'body'            => 'Quarterly roadmap details',
        ]);

        $response = $this->actingAs($this->userA)->postJson("/chat/messages/{$msg->id}/forward", [
            'target_conversation_id' => $conv2->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals('Quarterly roadmap details', $response->json('message.body'));
        $this->assertNotNull($response->json('message.forward_from'));
        $this->assertEquals($this->userB->name, $response->json('message.forward_from.author_name'));

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv2->id,
            'user_id'         => $this->userA->id,
            'content'         => 'Quarterly roadmap details',
        ]);
    }

    public function test_user_can_view_attachment_inline(): void
    {
        Storage::fake('public');

        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'body'            => 'See attachment',
        ]);

        Storage::disk('public')->put('chat_attachments/preview_test.png', 'fake-image-bytes');

        $att = ChatMessageAttachment::create([
            'message_id' => $msg->id,
            'file_name'  => 'preview_test.png',
            'file_path'  => 'chat_attachments/preview_test.png',
            'file_type'  => 'image/png',
            'file_size'  => 16,
        ]);

        $response = $this->actingAs($this->userA)->get("/chat/attachments/{$att->id}/view");
        $response->assertStatus(200);
        $this->assertEquals('image/png', $response->headers->get('Content-Type'));
    }

    public function test_dara_can_manage_and_toggle_chat_permissions(): void
    {
        $daraUser = User::factory()->create([
            'name'         => 'Mr. Dara QC',
            'username'     => 'dara',
            'is_active'    => true,
            'can_use_chat' => true,
        ]);

        $this->assertTrue($daraUser->canManageChatPermissions());

        // Dara fetches users list
        $resList = $this->actingAs($daraUser)->getJson('/chat/admin/users-permissions');
        $resList->assertStatus(200);
        $resList->assertJson(['success' => true]);

        // Dara disables chat for User B
        $resToggle = $this->actingAs($daraUser)->postJson('/chat/admin/toggle-user-chat', [
            'user_id'      => $this->userB->id,
            'can_use_chat' => false,
        ]);

        $resToggle->assertStatus(200);
        $resToggle->assertJson(['success' => true, 'can_use_chat' => false]);
        $this->assertFalse((bool) $this->userB->fresh()->can_use_chat);
    }

    public function test_non_manager_cannot_toggle_chat_permissions(): void
    {
        // Normal user A attempts to toggle user B
        $response = $this->actingAs($this->userA)->postJson('/chat/admin/toggle-user-chat', [
            'user_id'      => $this->userB->id,
            'can_use_chat' => false,
        ]);

        $response->assertStatus(403);
    }

    public function test_disabled_chat_user_is_redirected_from_chat(): void
    {
        $disabledUser = User::factory()->create([
            'name'         => 'Restricted Member',
            'username'     => 'restricted_user',
            'is_active'    => true,
            'can_use_chat' => false,
        ]);

        $this->assertFalse($disabledUser->isChatEnabled());

        // Visiting /chat redirects to dashboard with error notice
        $response = $this->actingAs($disabledUser)->get('/chat');
        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');

        // Unread count returns 0
        $resUnread = $this->actingAs($disabledUser)->getJson('/chat/unread-count');
        $resUnread->assertStatus(200);
        $this->assertEquals(0, $resUnread->json('unread_count'));
    }

    public function test_chat_convert_images_to_webp_command(): void
    {
        Storage::fake('public');

        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);

        $msg = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'body'            => 'Photo to compress',
        ]);

        // Create a real small PNG using GD
        $im = imagecreatetruecolor(10, 10);
        $bgColor = imagecolorallocate($im, 255, 0, 0);
        imagefill($im, 0, 0, $bgColor);
        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();
        imagedestroy($im);

        Storage::disk('public')->put('chat_attachments/sample.png', $pngData);

        $att = ChatMessageAttachment::create([
            'message_id' => $msg->id,
            'file_name'  => 'sample.png',
            'file_path'  => 'chat_attachments/sample.png',
            'file_type'  => 'image/png',
            'file_size'  => strlen($pngData),
        ]);

        // Run conversion command with force flag
        $exitCode = \Illuminate\Support\Facades\Artisan::call('chat:convert-images-webp', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        $att->refresh();
        $this->assertEquals('image/webp', $att->file_type);
        $this->assertStringEndsWith('.webp', $att->file_path);
        $this->assertTrue(Storage::disk('public')->exists($att->file_path));
    }

    public function test_user_can_send_telegram_style_voice_message(): void
    {
        Storage::fake('public');

        $conv = ChatConversation::create(['type' => 'private']);
        ChatConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id]);
        ChatConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id]);

        $audioFile = UploadedFile::fake()->create('voice_sample.webm', 256, 'audio/webm');
        $waveformData = [25, 45, 60, 80, 55, 30, 70, 90, 40, 20];

        $response = $this->actingAs($this->userA)->post('/chat/messages/voice', [
            'conversation_id' => $conv->id,
            'voice_file'      => $audioFile,
            'duration'        => 8,
            'waveform_data'   => json_encode($waveformData),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $msgId = $response->json('message.id');
        $message = ChatMessage::with('attachments')->find($msgId);

        $this->assertNotNull($message);
        $this->assertEquals('voice', $message->type);
        $this->assertCount(1, $message->attachments);

        $attachment = $message->attachments->first();
        $this->assertEquals(8, $attachment->duration_seconds);
        $this->assertEquals($waveformData, $attachment->waveform_data);
    }

    public function test_user_can_initiate_signaling_and_manage_voice_calls(): void
    {
        $conv = ChatConversation::create(['type' => 'private']);
        ChatConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id]);
        ChatConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id]);

        // 1. User A initiates voice call to User B
        $initRes = $this->actingAs($this->userA)->postJson('/chat/calls/initiate', [
            'conversation_id' => $conv->id,
            'receiver_id'     => $this->userB->id,
        ]);

        $initRes->assertStatus(200);
        $initRes->assertJson(['success' => true]);
        $callId = $initRes->json('call.id');
        $this->assertNotNull($callId);

        // 2. User B checks active call
        $activeRes = $this->actingAs($this->userB)->getJson('/chat/calls/active');
        $activeRes->assertStatus(200);
        $activeRes->assertJson(['success' => true, 'active' => true]);
        $this->assertEquals($callId, $activeRes->json('call.id'));

        // 3. User A sends WebRTC SDP offer signal
        $signalRes = $this->actingAs($this->userA)->postJson("/chat/calls/{$callId}/signal", [
            'type'    => 'offer',
            'payload' => ['sdp' => 'v=0\r\no=mock 1 1 IN IP4 127.0.0.1...'],
        ]);
        $signalRes->assertStatus(200);
        $signalRes->assertJson(['success' => true]);

        // 4. User B polls signals
        $pollRes = $this->actingAs($this->userB)->getJson("/chat/calls/{$callId}/signals");
        $pollRes->assertStatus(200);
        $this->assertCount(1, $pollRes->json('signals'));
        $this->assertEquals('offer', $pollRes->json('signals.0.type'));

        // 5. User B accepts the call
        $acceptRes = $this->actingAs($this->userB)->postJson("/chat/calls/{$callId}/accept");
        $acceptRes->assertStatus(200);
        $acceptRes->assertJson(['success' => true]);

        // 6. User A ends the call
        $endRes = $this->actingAs($this->userA)->postJson("/chat/calls/{$callId}/end", [
            'reason' => 'normal',
        ]);
        $endRes->assertStatus(200);
        $endRes->assertJson(['success' => true]);

        // 7. Verify a call event message was logged in the conversation
        $callEventMsg = ChatMessage::where('conversation_id', $conv->id)
            ->where('call_id', $callId)
            ->first();
        $this->assertNotNull($callEventMsg);
        $this->assertEquals('call_event', $callEventMsg->type);
    }

    public function test_user_can_delete_conversation(): void
    {
        $conv = ChatConversation::create(['type' => 'private']);
        ChatConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id]);
        ChatConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id]);

        $res = $this->actingAs($this->userA)->deleteJson("/chat/conversations/{$conv->id}");
        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $this->assertDatabaseMissing('chat_conversation_members', [
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
        ]);
    }

    public function test_user_can_update_wallpaper_image_url(): void
    {
        $customUrl = 'https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-dark.webp';

        $res = $this->actingAs($this->userA)->postJson('/chat/settings', [
            'wallpaper'       => 'custom',
            'wallpaper_image' => $customUrl,
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $settings = $this->actingAs($this->userA)->getJson('/chat/settings');
        $settings->assertStatus(200);
        $this->assertEquals('custom', $settings->json('settings.wallpaper'));
        $this->assertEquals($customUrl, $settings->json('settings.wallpaper_image'));
    }

    public function test_user_can_set_and_clear_conversation_nickname(): void
    {
        $conv = ChatConversation::create(['type' => 'private']);
        ChatConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id]);
        ChatConversationMember::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id]);

        // 1. Set nickname
        $res = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/nickname", [
            'nickname' => 'My Best Colleague',
        ]);
        $res->assertStatus(200);
        $res->assertJson([
            'success'  => true,
            'nickname' => 'My Best Colleague',
        ]);

        $this->assertDatabaseHas('chat_conversation_members', [
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'custom_nickname' => 'My Best Colleague',
        ]);

        // Verify conversation listing uses nickname
        $listRes = $this->actingAs($this->userA)->getJson('/chat/conversations');
        $listRes->assertStatus(200);
        $matching = collect($listRes->json('conversations'))->firstWhere('id', $conv->id);
        $this->assertNotNull($matching);
        $this->assertEquals('My Best Colleague', $matching['nickname']);
        $this->assertEquals('My Best Colleague', $matching['display_name']);

        // User B sees the original user A's name, not User A's private nickname
        $listResB = $this->actingAs($this->userB)->getJson('/chat/conversations');
        $matchingB = collect($listResB->json('conversations'))->firstWhere('id', $conv->id);
        $this->assertNull($matchingB['nickname']);
        $this->assertEquals($this->userA->name, $matchingB['display_name']);

        // 2. Clear nickname
        $clearRes = $this->actingAs($this->userA)->postJson("/chat/conversations/{$conv->id}/nickname", [
            'nickname' => '',
        ]);
        $clearRes->assertStatus(200);
        $clearRes->assertJson([
            'success'  => true,
            'nickname' => null,
        ]);

        $this->assertDatabaseHas('chat_conversation_members', [
            'conversation_id' => $conv->id,
            'user_id'         => $this->userA->id,
            'custom_nickname' => null,
        ]);
    }

    public function test_user_typing_event_broadcasts_on_conversation_and_user_channels(): void
    {
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $event = new \App\Events\Chat\UserTypingEvent($conv->id, $this->userA->id, $this->userA->name, true);
        $channels = $event->broadcastOn();

        $this->assertNotEmpty($channels);
        $channelNames = array_map(fn($c) => $c->name, $channels);

        $this->assertContains("private-chat.conversation.{$conv->id}", $channelNames);
        $this->assertContains("private-chat.user.{$this->userB->id}", $channelNames);
    }

    public function test_user_can_get_stickers_and_create_custom_sticker(): void
    {
        $this->actingAs($this->userA);

        // 1. Get Stickers
        $res = $this->getJson(route('chat.stickers.index'));
        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        // 2. Create Custom Sticker
        \Illuminate\Support\Facades\Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->image('custom_sticker.png', 200, 200);

        $createRes = $this->postJson(route('chat.stickers.store'), [
            'name'          => 'My Cool Sticker',
            'sticker_image' => $file,
        ]);
        $createRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'sticker' => [
                    'name' => 'My Cool Sticker',
                ]
            ]);

        $this->assertDatabaseHas('chat_stickers', [
            'user_id' => $this->userA->id,
            'name'    => 'My Cool Sticker',
        ]);

        // User A sees their own custom sticker in GET /chat/stickers
        $userAStickers = $this->getJson(route('chat.stickers.index'))->json('stickers');
        $this->assertTrue(collect($userAStickers)->contains('name', 'My Cool Sticker'));

        // User B MUST NOT see User A's custom sticker (Strict Privacy)
        $this->actingAs($this->userB);
        $userBStickers = $this->getJson(route('chat.stickers.index'))->json('stickers');
        $this->assertFalse(collect($userBStickers)->contains('name', 'My Cool Sticker'));

        // 3. User B saves the sticker to their own private collection (like in WhatsApp)
        $saveUrlRes = $this->postJson(route('chat.stickers.store'), [
            'name'      => 'My Cool Sticker',
            'image_url' => '/images/stickers/thank_kim.svg',
            'category'  => 'saved',
        ]);
        $saveUrlRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $savedStickerId = $saveUrlRes->json('sticker.id');
        $this->assertDatabaseHas('chat_stickers', [
            'id'      => $savedStickerId,
            'user_id' => $this->userB->id,
        ]);

        // Now User B has it in their private sticker drawer
        $userBStickersAfterSave = $this->getJson(route('chat.stickers.index'))->json('stickers');
        $this->assertTrue(collect($userBStickersAfterSave)->contains('id', $savedStickerId));

        // 4. User B removes/deletes their copy
        $delRes = $this->deleteJson(route('chat.stickers.destroy', $savedStickerId));
        $delRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('chat_stickers', [
            'id' => $savedStickerId,
        ]);

        // User A's original sticker is still intact and private to User A
        $this->actingAs($this->userA);
        $this->assertDatabaseHas('chat_stickers', [
            'user_id' => $this->userA->id,
            'name'    => 'My Cool Sticker',
        ]);
    }

    public function test_user_can_send_sticker_and_gif_messages(): void
    {
        $this->actingAs($this->userA);
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        // Send Sticker
        $stkRes = $this->postJson(route('chat.conversations.send', $conv->id), [
            'content' => '/images/stickers/thank_kim.svg',
            'type'    => 'sticker',
        ]);
        $stkRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => [
                    'type' => 'sticker',
                ]
            ]);

        // Send GIF
        $gifRes = $this->postJson(route('chat.conversations.send', $conv->id), [
            'content' => 'https://media.giphy.com/media/ICOgUNjpvO0PC/giphy.gif',
            'type'    => 'gif',
        ]);
        $gifRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => [
                    'type' => 'gif',
                ]
            ]);
    }

    public function test_user_can_view_conversation_media_profile(): void
    {
        $this->actingAs($this->userA);
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $res = $this->getJson(route('chat.conversations.media', $conv->id));
        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'media',
                'docs',
                'links',
                'counts',
            ]);
    }

    public function test_user_can_send_all_image_formats_including_svg_webp_png(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userA);
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $pngFile = UploadedFile::fake()->image('screenshot.png', 640, 480);
        $svgFile = UploadedFile::fake()->create('icon.svg', 120, 'image/svg+xml');

        $res = $this->postJson(route('chat.conversations.send', $conv->id), [
            'attachments' => [$pngFile, $svgFile],
        ]);

        $res->assertStatus(200);
        $this->assertEquals('image', $res->json('message.type'));
        $this->assertCount(2, $res->json('message.attachments'));
    }

    public function test_user_can_send_voice_message_with_waveform_and_duration(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userA);
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $voiceFile = UploadedFile::fake()->create('voice_recording.webm', 256, 'audio/webm');

        $res = $this->postJson(route('chat.conversations.send', $conv->id), [
            'type'             => 'voice',
            'duration_seconds' => 15,
            'waveform_data'    => json_encode([30, 45, 80, 60, 20, 90, 40]),
            'attachments'      => [$voiceFile],
        ]);

        $res->assertStatus(200);
        $this->assertEquals('voice', $res->json('message.type'));
        $att = $res->json('message.attachments.0');
        $this->assertEquals('audio/webm', $att['file_type']);
        $this->assertEquals(15, $att['duration_seconds']);
        $this->assertIsArray($att['waveform_data']);
    }

    public function test_send_voice_message_via_dedicated_endpoint(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userA);
        $conv = ChatConversation::create([
            'type'       => 'private',
            'created_by' => $this->userA->id,
        ]);
        $conv->members()->create(['user_id' => $this->userA->id]);
        $conv->members()->create(['user_id' => $this->userB->id]);

        $voiceFile = UploadedFile::fake()->create('voice_recording.webm', 256, 'audio/webm');

        $res = $this->postJson('/chat/messages/voice', [
            'conversation_id'  => $conv->id,
            'client_temp_id'   => 'temp_voice_99999',
            'duration'         => 18,
            'waveform_data'    => json_encode([10, 20, 30, 40, 50]),
            'voice_file'       => $voiceFile,
        ]);

        $res->assertStatus(200);
        $this->assertTrue($res->json('success'));
        $this->assertEquals('temp_voice_99999', $res->json('message.client_temp_id'));
        $this->assertEquals('voice', $res->json('message.type'));
        $att = $res->json('message.attachments.0');
        $this->assertNotNull($att);
        $this->assertEquals('audio/webm', $att['file_type']);
        $this->assertEquals(18, $att['duration_seconds']);
    }
}

