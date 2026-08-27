<?php

namespace Tests\Integration;

use App\Models\Group;
use App\Models\GroupMember;
use App\Enum\GroupMemberRoles;
use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\Integration\Concerns\CreatesEventFixtures;

/**
 * Inviting a team member sends the invitation through accounts, which
 * rate-limits mail per user (20/h, HTTP 429; accounts audit 2026-08-27)
 * and can time out. Those failures used to escape as a 500 after the
 * member row was already saved; now the save is rolled back and the
 * inviter gets a 429 / 503 with a message they can act on.
 */
class GroupInvitationMailTest extends IntegrationTestCase
{
    use CreatesEventFixtures;

    /** @var User */
    private $captain;

    /** @var Group */
    private $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->captain = $this->createUser();

        $this->group = new Group();
        $this->group->name = 'Test team';
        $this->group->save();

        $captainMember = new GroupMember();
        $captainMember->group()->associate($this->group);
        $captainMember->user()->associate($this->captain);
        $captainMember->role = GroupMemberRoles::ADMIN;
        $captainMember->save();
    }

    private function invite(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->captain)->postJson(
            '/api/v1/groups/' . $this->group->id . '/members',
            [ 'name' => 'Invitee', 'email' => 'invitee@example.com' ]
        );
    }

    private function inviteeCount(): int
    {
        return GroupMember::where('group_id', $this->group->id)
            ->where('email', 'invitee@example.com')
            ->count();
    }

    public function testInvitationIsMailedAndMemberSaved()
    {
        $this->invite()->assertSuccessful();

        $this->assertSame(1, $this->inviteeCount());
        $this->assertCount(1, $this->catlabApi->sendEmailCalls);
        $this->assertSame('invitee@example.com', $this->catlabApi->sendEmailCalls[0]['target']);
    }

    public function testRateLimitedInvitationIsRolledBackWith429()
    {
        $this->catlabApi->sendEmailException = new ClientException(
            'Too many messages',
            new Request('POST', 'users/1/mail'),
            new Response(429, [], '{"error":"Too many messages, try again later."}')
        );

        $response = $this->invite();

        $response->assertStatus(429);
        $this->assertStringContainsString('te veel uitnodigingen', $response->json('message'));
        $this->assertSame(0, $this->inviteeCount());
    }

    public function testUnreachableMailServiceIsRolledBackWith503()
    {
        $this->catlabApi->sendEmailException = new ConnectException(
            'Connection timed out',
            new Request('POST', 'users/1/mail')
        );

        $response = $this->invite();

        $response->assertStatus(503);
        $this->assertStringContainsString('kon niet verstuurd worden', $response->json('message'));
        $this->assertSame(0, $this->inviteeCount());
    }
}
